<?php

namespace App\Http\Controllers;

use App\Http\Requests\OutletPriceRequest;
use App\Http\Requests\NewProductFromPriceRequest;
use App\Models\Category;
use App\Models\OutletPrice;
use App\Models\OwnerStock;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\LatestHpp;
use App\Services\PriceCalculator;
use App\Support\OutletAccess;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class OutletPriceController extends Controller
{
    private const HPP_LOG_NAME = 'OutletPriceHpp';

    /**
     * Penyesuaian Harga Outlet (nominal, Rp) yang otomatis terisi di form saat outlet tertentu dipilih.
     * Format: [outlet_id => nominal].
     */
    private const OUTLET_ADJUSTMENT_DEFAULTS = [
        2 => 100,
    ];

    public function index(Request $request, PriceCalculator $calculator)
    {
        $this->ensureManagementAccess();
        $outletId = OutletAccess::id($request, false);
        $prices = $outletId
            ? OutletPrice::with([
                'outlet',
                'product',
            ])
                ->where('outlet_id', $outletId)
                ->when($request->filled('search'), fn ($query) => $query->whereHas('product', fn ($productQuery) => $productQuery
                    ->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('code', 'like', '%' . $request->search . '%')))
                ->latest('updated_at')
                ->paginate(25)
                ->withQueryString()
            : new LengthAwarePaginator([], 0, 25);

        if ($outletId) {
            $latestHpp = app(LatestHpp::class)->forProducts($outletId, $prices->getCollection()->pluck('product_id'));

            // Stok terkini outlet ini per produk (SUM owner_stocks.qty), 1 query untuk satu halaman.
            $outletStocks = OwnerStock::where('owner_id', $outletId)
                ->whereIn('product_id', $prices->getCollection()->pluck('product_id'))
                ->selectRaw('product_id, SUM(qty) as total_qty')
                ->groupBy('product_id')
                ->pluck('total_qty', 'product_id');

            $prices->getCollection()->each(function (OutletPrice $price) use ($calculator, $latestHpp, $outletStocks) {
                $product = $price->product;

                $price->setAttribute('print_hpp', (float) ($latestHpp[$price->product_id] ?? 0));
                $price->setAttribute('outlet_stock_qty', (int) ($outletStocks[$price->product_id] ?? 0));
                $calculated = $calculator->calculateItem(
                    (float) ($latestHpp[$price->product_id] ?? 0),
                    $price,
                    $product
                );

                $price->setAttribute('print_price_hpp_after_tax', $calculated['hpp_setelah_pajak']);
                $price->setAttribute('print_price_margin', $calculated['margin_amount']);
                $price->setAttribute('print_price_strike', $calculator->money(
                    $calculated['hpp_setelah_pajak'] + $calculated['margin_amount']
                ));
                $price->setAttribute('print_price_net', $calculated['price']);
            });
        }

        return view('outlet-prices.index', [
            'prices' => $prices,
            'outlets' => OutletAccess::outlets(),
            'selectedOutletId' => $outletId,
        ]);
    }

    /**
     * Riwayat perubahan HPP produk di outlet ini (dari activity log batch stok outlet),
     * untuk modal di kolom HPP pada index.
     */
    public function hppHistory(OutletPrice $outletPrice)
    {
        $this->ensureManagementAccess();

        $stocks = OwnerStock::withTrashed()
            ->where('owner_id', $outletPrice->outlet_id)
            ->where('product_id', $outletPrice->product_id)
            ->get(['id', 'batch_number'])
            ->keyBy('id');

        // 1) Perubahan HPP pada batch stok outlet ini.
        $batchRows = $stocks->isEmpty()
            ? collect()
            : Activity::where('subject_type', (new OwnerStock)->getMorphClass())
                ->whereIn('subject_id', $stocks->keys())
                ->with('causer')
                ->get()
                ->filter(fn ($activity) => isset($activity->properties['attributes']['hpp']))
                ->map(function ($activity) use ($stocks) {
                    $stock = $stocks->get($activity->subject_id);
                    $old = $activity->event === 'created' ? null : ($activity->properties['old']['hpp'] ?? null);

                    return [
                        'at' => $activity->created_at,
                        'source' => 'Batch ' . ($stock?->batch_number ?: ('#' . $activity->subject_id)),
                        'old' => $old === null ? null : (float) $old,
                        'new' => (float) $activity->properties['attributes']['hpp'],
                        'user' => $activity->causer?->name ?? 'System',
                        'event' => $activity->event,
                    ];
                });

        // 2) Outlet belum punya batch -> HPP diambil dari harga beli produk; perubahan HPP
        //    dari form master harga disimpan ke sana, jadi riwayatnya diambil dari log produk.
        $product = $outletPrice->product;
        $productRows = ! $product
            ? collect()
            : Activity::forSubject($product)
                ->with('causer')
                ->get()
                ->filter(fn ($activity) => isset($activity->properties['attributes']['harga_beli']))
                ->map(function ($activity) {
                    $old = $activity->event === 'created' ? null : ($activity->properties['old']['harga_beli'] ?? null);

                    return [
                        'at' => $activity->created_at,
                        'source' => 'Harga beli produk',
                        'old' => $old === null ? null : (float) $old,
                        'new' => (float) $activity->properties['attributes']['harga_beli'],
                        'user' => $activity->causer?->name ?? 'System',
                        'event' => $activity->event,
                    ];
                });

        // 3) Perubahan HPP yang dilakukan langsung dari form master harga ini.
        $masterRows = Activity::where('log_name', self::HPP_LOG_NAME)
            ->where('subject_type', $outletPrice->getMorphClass())
            ->where('subject_id', $outletPrice->id)
            ->with('causer')
            ->get()
            ->map(function ($activity) {
                $batch = $activity->properties['batch_number'] ?? null;
                $target = ($activity->properties['target'] ?? null) === 'batch'
                    ? ' (batch ' . ($batch ?: 'terbaru') . ')'
                    : ' (harga beli produk)';

                return [
                    'at' => $activity->created_at,
                    'source' => 'Master harga' . $target,
                    'old' => isset($activity->properties['old']) ? (float) $activity->properties['old'] : null,
                    'new' => (float) ($activity->properties['new'] ?? 0),
                    'user' => $activity->causer?->name ?? 'System',
                    'event' => 'updated',
                ];
            });

        // Log otomatis batch / produk yang kembar dengan catatan master harga (perubahan yang sama,
        // user sama, waktu berdekatan) dibuang supaya tidak tampil dobel.
        $isMirror = fn (array $row) => $row['event'] === 'updated' && $masterRows->contains(
            fn (array $master) => $master['user'] === $row['user']
                && abs($master['new'] - $row['new']) < 0.005
                && abs($master['at']->getTimestamp() - $row['at']->getTimestamp()) <= 10
        );

        $data = $batchRows->reject($isMirror)
            ->concat($productRows->reject($isMirror))
            ->concat($masterRows)
            ->sortBy(fn ($row) => $row['at']->getTimestamp())
            ->map(fn ($row) => [
                'date' => $row['at']->format('d M Y H:i'),
                'user' => $row['user'],
                'batch' => $row['source'],
                'old' => $row['old'],
                'new' => $row['new'],
                'event' => $row['event'],
            ])
            ->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function create(Request $request)
    {
        $this->ensureManagementAccess();
        $newPrice = new OutletPrice([
            'disc_brand_type' => 'nominal',
            'pajak_type' => 'percentage',
            'pajak_value' => 0,
            'disc_tambahan_type' => 'nominal',
            'disc_tambahan_value' => 0,
            'margin_type' => 'percentage',
            'margin_value' => 0,
            'disc_toko_type' => 'nominal',
            'disc_toko_value' => 0,
            'outlet_adjustment_type' => 'nominal',
            'outlet_adjustment_value' => 0,
            'is_active' => true,
        ]);

        // Setelah daftar produk baru: outlet & produk langsung terpilih di form ini.
        $outlets = OutletAccess::outlets();
        if ($request->filled('outlet_id') && $outlets->contains('id', (int) $request->query('outlet_id'))) {
            $newPrice->outlet_id = (int) $request->query('outlet_id');
        }

        return view('outlet-prices.form', [
            'price' => $newPrice,
            'outlets' => $outlets,
            'selectedProduct' => $this->selectedProduct($request->old('product_id', $request->query('product_id'))),
            'method' => 'POST',
            'action' => route('outlet-prices.store'),
            'previewHpp' => null,
            'outletAdjustmentDefaults' => self::OUTLET_ADJUSTMENT_DEFAULTS,
            // Untuk panel "Daftarkan Produk Baru" (hanya dipakai di mode tambah)
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(OutletPriceRequest $request)
    {
        DB::transaction(function () use ($request) {
            $price = OutletPrice::withTrashed()->firstOrNew([
                'outlet_id' => $request->outlet_id,
                'product_id' => $request->product_id,
            ]);
            $price->fill([
                ...Arr::except($request->validated(), ['hpp', 'hpp_changed']),
                'created_by' => auth()->id(),
                'is_active' => $request->boolean('is_active', true),
            ]);
            if ($price->trashed()) {
                $price->restore();
            }
            if ($price->exists) {
                // Simpan ulang data lama selalu memperbarui updated_at, walau isinya sama.
                $price->setUpdatedAt(now());
            }
            $price->save();

            $this->syncLatestHpp($request, $price);
        });

        return redirect()
            ->route('outlet-prices.index', ['outlet_id' => $request->outlet_id])
            ->with('toast_success', 'Master harga outlet berhasil disimpan.');
    }

    /**
     * Daftarkan produk baru langsung dari form master harga (untuk staff outlet),
     * lalu kembali ke form aturan harga dengan produk & outlet sudah terpilih.
     * Validasi sama dengan menu Product (barcode unik, dll).
     */
    public function storeProduct(NewProductFromPriceRequest $request)
    {
        $this->ensureManagementAccess();

        // harga_beli sudah dikonversi ke per satuan kecil bila diinput per satuan besar
        $data = $request->productData();
        if (($data['status_produk'] ?? 'sudah') !== 'tambahan_diskon') {
            $data['status_produk_note'] = null;
        }

        $product = DB::transaction(function () use ($request, $data) {
            $product = Product::create($data);

            if ($request->filled('supplier_ids')) {
                $product->suppliers()->sync($request->supplier_ids);
            }

            return $product;
        });

        $outletId = $request->filled('outlet_id') ? (int) $request->input('outlet_id') : null;

        return redirect()
            ->route('outlet-prices.create', array_filter([
                'product_id' => $product->id,
                'outlet_id' => $outletId,
            ]))
            ->with('toast_success', "Produk \"{$product->name}\" berhasil didaftarkan. Silakan atur harga jualnya.");
    }

    public function edit(Request $request, OutletPrice $outletPrice)
    {
        $this->ensureManagementAccess();
        return view('outlet-prices.form', [
            'price' => $outletPrice,
            'outlets' => OutletAccess::outlets(),
            'selectedProduct' => $this->selectedProduct($request->old('product_id', $outletPrice->product_id)),
            'method' => 'PUT',
            'action' => route('outlet-prices.update', $outletPrice),
            'previewHpp' => app(LatestHpp::class)->forProduct((int) $outletPrice->outlet_id, (int) $outletPrice->product_id),
        ]);
    }

    /**
     * Select2 AJAX untuk pilihan produk: 20 produk per halaman, tanpa COUNT(*)
     * (ambil 1 baris ekstra untuk tahu masih ada halaman berikutnya).
     */
    public function searchProducts(Request $request)
    {
        $this->ensureManagementAccess();

        $search = trim((string) $request->query('q', ''));
        $page = max((int) $request->query('page', 1), 1);
        $perPage = 20;

        $query = Product::query();

        if ($search !== '') {
            $like = addcslashes($search, '\\%_');
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', "%{$like}%")
                    ->orWhere('code', 'like', "{$like}%");
            });
        }

        $rows = $query
            ->orderBy('name')
            ->skip(($page - 1) * $perPage)
            ->take($perPage + 1)
            ->get(['id', 'code', 'name']);

        return response()->json([
            'results' => $rows->take($perPage)->map(fn ($product) => [
                'id' => $product->id,
                'text' => "{$product->code} — {$product->name}",
            ])->values(),
            'pagination' => ['more' => $rows->count() > $perPage],
        ]);
    }

    /**
     * Hanya produk yang sedang terpilih (edit / old input), bukan seluruh katalog.
     */
    private function selectedProduct($productId): ?Product
    {
        return $productId ? Product::select(['id', 'code', 'name'])->find($productId) : null;
    }

    public function previewHpp(Request $request)
    {
        $this->ensureManagementAccess();
        $request->validate([
            'outlet_id' => 'required|integer|exists:outlets,id',
            'product_id' => 'required|integer|exists:products,id',
        ]);

        return response()->json([
            'hpp' => app(LatestHpp::class)->forProduct((int) $request->outlet_id, (int) $request->product_id),
        ]);
    }

    /**
     * Kalau HPP diubah dari form master harga, simpan sebagai HPP terbaru.
     * Hanya jalan bila form menandai angkanya benar-benar diubah (hpp_changed),
     * supaya angka bawaan form tidak menimpa data tanpa disengaja.
     */
    private function syncLatestHpp(Request $request, OutletPrice $price): void
    {
        if (! $request->boolean('hpp_changed') || $request->input('hpp') === null) {
            return;
        }

        $outletId = (int) $price->outlet_id;
        $productId = (int) $price->product_id;
        $hpp = round((float) $request->input('hpp'), 2);
        $latest = app(LatestHpp::class);
        $oldHpp = $latest->forProduct($outletId, $productId);
        $stock = $latest->stockFor($outletId, $productId);

        if ($stock) {
            $stock->update(['hpp' => $hpp]);
        } else {
            Product::whereKey($productId)->update(['harga_beli' => $hpp]);
        }

        if (abs($oldHpp - $hpp) < 0.005) {
            return;
        }

        // Catat eksplisit supaya perubahan HPP dari master harga selalu muncul di riwayat HPP,
        // tidak bergantung pada log otomatis batch / produk.
        activity(self::HPP_LOG_NAME)
            ->performedOn($price)
            ->causedBy(auth()->user())
            ->event('hpp_changed')
            ->withProperties([
                'outlet_id' => $outletId,
                'product_id' => $productId,
                'old' => $oldHpp,
                'new' => $hpp,
                'target' => $stock ? 'batch' : 'product',
                'batch_number' => $stock?->batch_number,
            ])
            ->log('HPP diubah dari master harga');
    }

    public function update(OutletPriceRequest $request, OutletPrice $outletPrice)
    {
        DB::transaction(function () use ($request, $outletPrice) {
            $outletPrice->fill([
                ...Arr::except($request->validated(), ['hpp', 'hpp_changed']),
                'is_active' => $request->boolean('is_active'),
            ]);
            // Setiap kali form edit disimpan, updated_at selalu diperbarui
            // (Eloquent biasanya melewatkannya bila tidak ada kolom yang berubah).
            $outletPrice->setUpdatedAt(now());
            $outletPrice->save();

            $this->syncLatestHpp($request, $outletPrice);
        });

        return redirect()
            ->route('outlet-prices.index', ['outlet_id' => $outletPrice->outlet_id])
            ->with('toast_success', 'Master harga outlet berhasil diperbarui.');
    }

    /**
     * Tombol "Kembali" di form edit: tidak mengubah data, tetapi tetap memperbarui updated_at
     * lalu kembali ke index dengan outlet yang sama.
     */
    public function touch(OutletPrice $outletPrice)
    {
        $this->ensureManagementAccess();

        $outletPrice->setUpdatedAt(now());
        $outletPrice->save();

        return redirect()->route('outlet-prices.index', ['outlet_id' => $outletPrice->outlet_id]);
    }

    public function destroy(OutletPrice $outletPrice)
    {
        $this->ensureManagementAccess();
        $outletId = $outletPrice->outlet_id;
        $outletPrice->delete();

        return redirect()
            ->route('outlet-prices.index', ['outlet_id' => $outletId])
            ->with('toast_success', 'Master harga outlet berhasil dihapus.');
    }

    private function ensureManagementAccess(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['superadmin', 'admin-gudang', 'owner', 'staff-outlet'], true), 403);
    }
}