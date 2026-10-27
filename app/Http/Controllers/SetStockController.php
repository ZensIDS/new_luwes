<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\OwnerStock;
use App\Models\Product;
use App\Models\SetStockDraft;
use App\Models\SetStockDraftItem;
use App\Services\LatestHpp;
use App\Services\OutletStockService;
use App\Services\PriceCalculator;
use App\Support\OutletAccess;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Set Stock (Stok Toko): input stok awal / koreksi stok per toko dengan scan barcode.
 * Draft (keranjang) disimpan di database, SATU per toko dan dipakai bersama (K12).
 */
class SetStockController extends Controller
{
    private const MAX_QTY = 1000000;
    private const MAX_DRAFT_ITEMS = 5000;

    public function index(Request $request)
    {
        $outletId = OutletAccess::id($request, false);

        return view('set-stock.index', [
            'outlets' => OutletAccess::outlets(),
            'outletId' => $outletId,
            'selectedOutlet' => $outletId ? Outlet::find($outletId) : null,
        ]);
    }

    /** Cari produk persis berdasarkan barcode + info untuk kartu produk. */
    public function scan(Request $request, PriceCalculator $calculator, LatestHpp $latestHpp): JsonResponse
    {
        $data = $request->validate([
            'barcode' => ['required', 'string', 'max:100', 'regex:/^[^\x00-\x1F\x7F]+$/'],
        ]);
        $outletId = OutletAccess::id($request);
        $barcode = trim($data['barcode']);

        $product = Product::query()
            ->where('code', $barcode)
            ->with([
                'outletPrices' => fn ($query) => $query->where('outlet_id', $outletId)->currentlyActive(),
            ])
            ->first();

        if (! $product) {
            return response()->json(['message' => 'Barcode "' . $barcode . '" tidak ditemukan.'], 404);
        }

        $hpp = $latestHpp->forProduct($outletId, $product->id);
        $price = $calculator->calculateItem($hpp, $product->outletPrices->first(), $product);
        $stockNow = (int) OwnerStock::where('owner_id', $outletId)
            ->where('product_id', $product->id)
            ->notDeficit()
            ->sum('qty');

        $item = null;
        $draft = SetStockDraft::where('active_outlet_id', $outletId)->first();
        if ($draft) {
            $item = SetStockDraftItem::with('updater:id,name')
                ->where('draft_id', $draft->id)
                ->where('product_id', $product->id)
                ->first();
            if ($item) {
                // Hitung scan ulang tanpa mengubah updated_at / version (bukan perubahan qty).
                DB::table('set_stock_draft_items')->where('id', $item->id)->update([
                    'scan_count' => DB::raw('scan_count + 1'),
                    'last_scanned_at' => now(),
                ]);
                $item->scan_count++;
            }
        }

        return response()->json([
            'product' => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'satuan' => $product->satuan ?: 'pcs',
                'hpp' => (float) $hpp,
                'price_strike' => $calculator->money($price['hpp_setelah_pajak'] + $price['margin_amount']),
                'price_net' => (float) $price['price'],
                'stock_now' => $stockNow,
            ],
            'item' => $item ? $this->itemPayload($item) : null,
        ]);
    }

    /** Isi keranjang (draft aktif toko ini). Dipakai juga untuk memulihkan draft saat halaman dibuka lagi. */
    public function draft(Request $request): JsonResponse
    {
        $outletId = OutletAccess::id($request);
        $draft = SetStockDraft::where('active_outlet_id', $outletId)->first();

        $items = collect();
        if ($draft) {
            $items = SetStockDraftItem::query()
                ->from('set_stock_draft_items as i')
                ->join('products as p', 'p.id', '=', 'i.product_id')
                ->leftJoin('users as u', 'u.id', '=', 'i.updated_by')
                ->where('i.draft_id', $draft->id)
                ->orderByDesc('i.updated_at')
                ->limit(self::MAX_DRAFT_ITEMS)
                ->get([
                    'i.id', 'i.product_id', 'i.qty', 'i.scan_count', 'i.version', 'i.updated_at',
                    'p.code as product_code', 'p.name as product_name', 'u.name as updated_by_name',
                ])
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'product_id' => $row->product_id,
                    'code' => $row->product_code,
                    'name' => $row->product_name,
                    'qty' => (int) $row->qty,
                    'scan_count' => (int) $row->scan_count,
                    'version' => (int) $row->version,
                    'updated_by' => $row->updated_by_name,
                    'updated_at' => $row->updated_at ? \Illuminate\Support\Carbon::parse($row->updated_at)->timezone('Asia/Jakarta')->format('d/m H:i') : null,
                ]);
        }

        return response()->json([
            'draft_id' => $draft?->id,
            'items' => $items->values(),
            'count' => $items->count(),
            'total_qty' => (int) $items->sum('qty'),
        ]);
    }

    /** Tambah / ubah satu item di keranjang (autosave tiap scan / ubah qty). */
    public function storeItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'qty' => ['required', 'integer', 'min:0', 'max:' . self::MAX_QTY],
            'expected_version' => ['nullable', 'integer', 'min:0'],
        ], [
            'qty.required' => 'Qty harus diisi.',
            'qty.integer' => 'Qty harus berupa bilangan bulat.',
            'qty.min' => 'Qty tidak boleh negatif.',
            'qty.max' => 'Qty terlalu besar.',
        ]);
        $outletId = OutletAccess::id($request);
        $expected = (int) ($data['expected_version'] ?? 0);

        $result = DB::transaction(function () use ($data, $outletId, $expected, $request) {
            $draft = $this->lockedActiveDraft($outletId, true);

            $item = SetStockDraftItem::where('draft_id', $draft->id)
                ->where('product_id', $data['product_id'])
                ->lockForUpdate()
                ->first();

            if ($item) {
                // Item sudah ada: hanya boleh ditimpa bila pengirim melihat versi terbaru.
                if ((int) $item->version !== $expected) {
                    return ['conflict' => $item->load('updater:id,name')];
                }
                $item->update([
                    'qty' => (int) $data['qty'],
                    'version' => $item->version + 1,
                    'last_scanned_at' => now(),
                    'updated_by' => $request->user()->id,
                ]);

                return ['item' => $item->load('updater:id,name')];
            }

            if (SetStockDraftItem::where('draft_id', $draft->id)->count() >= self::MAX_DRAFT_ITEMS) {
                abort(422, 'Keranjang sudah penuh. Simpan dulu sebelum menambah item.');
            }

            $item = SetStockDraftItem::create([
                'draft_id' => $draft->id,
                'product_id' => (int) $data['product_id'],
                'qty' => (int) $data['qty'],
                'scan_count' => 1,
                'version' => 1,
                'last_scanned_at' => now(),
                'updated_by' => $request->user()->id,
            ]);

            return ['item' => $item->load('updater:id,name')];
        });

        if (isset($result['conflict'])) {
            return response()->json([
                'message' => 'Item ini baru saja diubah oleh ' . ($result['conflict']->updater?->name ?? 'pengguna lain')
                    . ' (qty sekarang ' . $result['conflict']->qty . '). Periksa lagi sebelum menimpa.',
                'item' => $this->itemPayload($result['conflict']),
            ], 409);
        }

        return response()->json(['item' => $this->itemPayload($result['item'])]);
    }

    public function destroyItem(Request $request, SetStockDraftItem $item): JsonResponse
    {
        $outletId = OutletAccess::id($request);
        $draft = SetStockDraft::where('active_outlet_id', $outletId)->first();
        abort_unless($draft && (int) $item->draft_id === (int) $draft->id, 404, 'Item tidak ditemukan di keranjang toko ini.');

        $item->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Simpan keranjang: SATU transaksi database, semua item atau tidak sama sekali.
     * Draft dikunci selama proses, sehingga klik ganda / 2 pengguna tidak menyimpan dua kali.
     */
    public function save(Request $request, OutletStockService $stockService, LatestHpp $latestHpp): JsonResponse
    {
        $data = $request->validate(['token' => ['nullable', 'string', 'max:64']]);
        $outletId = OutletAccess::id($request);
        $user = $request->user();
        $failures = [];

        @set_time_limit(180);

        try {
            $summary = DB::transaction(function () use ($outletId, $user, $data, $stockService, $latestHpp, &$failures) {
                $draft = $this->lockedActiveDraft($outletId, false);
                if (! $draft) {
                    abort(422, 'Keranjang kosong atau sudah disimpan oleh pengguna lain.');
                }

                $items = SetStockDraftItem::with('product:id,code,name')
                    ->where('draft_id', $draft->id)
                    ->orderBy('id')
                    ->get();
                if ($items->isEmpty()) {
                    abort(422, 'Keranjang masih kosong.');
                }

                $hppMap = $latestHpp->forProducts($outletId, $items->pluck('product_id'));
                $changed = 0;
                $unchanged = 0;

                foreach ($items as $item) {
                    try {
                        $result = $stockService->setInitialStock(
                            $outletId,
                            (int) $item->product_id,
                            (int) $item->qty,
                            (float) ($hppMap[$item->product_id] ?? 0),
                            (int) $draft->id,
                            $user
                        );
                        $result ? $changed++ : $unchanged++;
                    } catch (Throwable $e) {
                        $failures[] = ($item->product?->code ?? '#' . $item->product_id) . ' - '
                            . ($item->product?->name ?? 'produk') . ': ' . $e->getMessage();
                    }
                }

                // Hitung ulang: jumlah item tersimpan harus sama dengan jumlah item di draft.
                if ($failures || ($changed + $unchanged) !== $items->count()) {
                    throw new RuntimeException('SET_STOCK_FAILED');
                }

                $draft->update([
                    'status' => 'done',
                    'active_outlet_id' => null,
                    'save_token' => $data['token'] ?? null,
                    'saved_by' => $user->id,
                    'saved_at' => now(),
                ]);

                return ['total' => $items->count(), 'changed' => $changed, 'unchanged' => $unchanged];
            });
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== 'SET_STOCK_FAILED') {
                throw $e;
            }

            return response()->json([
                'message' => 'Set Stock dibatalkan, tidak ada stok yang berubah. Keranjang tetap tersimpan. Perbaiki item berikut lalu simpan lagi.',
                'failures' => $failures,
            ], 422);
        }

        return response()->json([
            'message' => 'Set Stock tersimpan: ' . $summary['total'] . ' produk (' . $summary['changed']
                . ' berubah, ' . $summary['unchanged'] . ' qty sudah sama).',
            'summary' => $summary,
        ]);
    }

    /** Draft aktif toko, dikunci. $create = buat bila belum ada. */
    private function lockedActiveDraft(int $outletId, bool $create): ?SetStockDraft
    {
        $find = fn () => SetStockDraft::where('active_outlet_id', $outletId)->lockForUpdate()->first();
        $draft = $find();

        if (! $draft && $create) {
            try {
                $draft = SetStockDraft::create([
                    'outlet_id' => $outletId,
                    'active_outlet_id' => $outletId,
                    'status' => 'active',
                ]);
            } catch (QueryException $e) {
                // Pengguna lain membuat draft yang sama bersamaan: pakai draft itu.
                $draft = $find();
                if (! $draft) {
                    throw $e;
                }
            }
        }

        return $draft;
    }

    private function itemPayload(SetStockDraftItem $item): array
    {
        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'qty' => (int) $item->qty,
            'scan_count' => (int) $item->scan_count,
            'version' => (int) $item->version,
            'updated_by' => $item->updater?->name,
            'updated_at' => $item->updated_at?->timezone('Asia/Jakarta')->format('d/m H:i'),
        ];
    }
}