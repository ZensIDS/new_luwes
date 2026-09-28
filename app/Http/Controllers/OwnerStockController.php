<?php

namespace App\Http\Controllers;

use App\Models\OwnerStock;
use App\Models\Outlet;
use App\Models\OutletPurchase;
use App\Models\OutletPurchaseItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\OutletStockService;
use App\Services\OwnerKartuStokBuilder;
use App\Support\OutletAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class OwnerStockController extends Controller
{
    public function index(Request $request)
    {
        $outlets = OutletAccess::outlets();
        $outletId = OutletAccess::id($request, false);
        $selectedOwner = $outletId ? Outlet::find($outletId) : null;
        $outletIds = $outlets->pluck('id');

        return view('owner-stocks.index', [
            'outlets' => $outlets,
            'selectedOwner' => $selectedOwner,
            'categoryOptions' => $this->categoryOptions(),
            'locationOptions' => $this->locationOptions(),
            'sourceOptions' => $this->sourceOptions($outletIds),
            'supplierOptions' => $this->supplierOptions($outletIds),
        ]);
    }

    /**
     * Server-side DataTables untuk Stock Toko.
     * Semua agregasi (saldo, masuk, keluar, adjustment, HPP rata-rata) dihitung di SQL,
     * dan hanya satu halaman (maks 100 baris) yang dikirim ke browser.
     */
    public function getIndexData(Request $request)
    {
        $draw = (int) $request->input('draw');
        $outletId = OutletAccess::id($request, false);

        if (! $outletId) {
            return response()->json(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }

        $start = max((int) $request->input('start', 0), 0);
        $length = (int) $request->input('length', 25);
        $length = $length > 0 ? min($length, 100) : 25;
        $searchValue = trim((string) $request->input('search.value', ''));
        $orderDir = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        // Index kolom DataTables (urutan kolom di owner-stocks/index.blade.php) -> alias kolom hasil agregasi.
        $sortable = [
            2 => 'product_code',
            3 => 'product_name',
            4 => 'category_name',
            7 => 'hpp',
            8 => 'qty_in_total',
            9 => 'qty_out_total',
            10 => 'adjustment_total',
            11 => 'qty',
            12 => 'expired_at',
        ];
        $orderBy = $sortable[(int) $request->input('order.0.column', 3)] ?? 'product_name';

        $recordsTotal = DB::table('owner_stocks')
            ->whereNull('deleted_at')
            ->where('owner_id', $outletId)
            ->distinct()
            ->count('product_id');

        // Total pergerakan per batch stok toko (sekali agregasi, bukan 4 subquery per baris).
        $movementTotals = DB::table('stock_movements')
            ->whereNotNull('owner_stock_id')
            ->select('owner_stock_id')
            ->selectRaw('SUM(qty_in) as m_in, SUM(qty_out) as m_out')
            ->selectRaw("SUM(CASE WHEN type = 'adjustment' THEN qty_in ELSE 0 END) as adj_in")
            ->selectRaw("SUM(CASE WHEN type = 'adjustment' THEN qty_out ELSE 0 END) as adj_out")
            ->groupBy('owner_stock_id');

        $base = DB::table('owner_stocks as os')
            ->whereNull('os.deleted_at')
            ->where('os.owner_id', $outletId)
            ->leftJoin('products as p', function ($join) {
                $join->on('p.id', '=', 'os.product_id')->whereNull('p.deleted_at');
            })
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoinSub($movementTotals, 'm', 'm.owner_stock_id', '=', 'os.id');

        $supplierId = $request->input('supplier_id');
        if ($supplierId) {
            // Join tambahan hanya kalau filter supplier dipakai.
            $base->leftJoin('stocks as st', 'st.id', '=', 'os.stock_id')
                ->leftJoin('pembelians as pb', 'pb.id', '=', 'st.pembelian_id')
                ->leftJoin('outlet_purchases as op', function ($join) {
                    $join->on('op.id', '=', 'os.source_id')
                        ->where('os.source_type', '=', OutletPurchase::class);
                });
        }

        if ($request->filled('kategori')) {
            $base->where('c.name', $request->kategori);
        }
        if ($request->filled('lokasi')) {
            $base->where('p.lokasi', $request->lokasi);
        }

        if ($searchValue !== '') {
            $words = array_slice(preg_split('/\s+/', $searchValue, -1, PREG_SPLIT_NO_EMPTY), 0, 5);
            $base->where(function ($query) use ($words) {
                foreach ($words as $word) {
                    $query->where(function ($wordQuery) use ($word) {
                        $wordQuery->where('p.name', 'like', "%{$word}%")
                            ->orWhere('p.code', 'like', "%{$word}%")
                            ->orWhere('os.batch_number', 'like', "%{$word}%");
                    });
                }
            });
        }

        $sourceDistinct = "COUNT(DISTINCT CONCAT(COALESCE(os.source_type, ''), ':', COALESCE(os.source_id, '')))";
        $expiredExpr = 'COALESCE(MIN(os.expired_at) < CURDATE(), 0)';

        $grouped = $base
            ->groupBy('os.owner_id', 'os.product_id', 'p.code', 'p.name', 'p.satuan', 'p.lokasi', 'c.name')
            ->select('os.owner_id', 'os.product_id')
            ->selectRaw('p.code as product_code, p.name as product_name, p.satuan as satuan, p.lokasi as lokasi, c.name as category_name')
            ->selectRaw('SUM(os.qty) as qty')
            ->selectRaw('CASE WHEN SUM(os.qty) > 0 THEN SUM(os.qty * os.hpp) / SUM(os.qty) ELSE MAX(os.hpp) END as hpp')
            ->selectRaw('COUNT(*) as batch_count')
            ->selectRaw('COALESCE(SUM(m.m_in), 0) as qty_in_total')
            ->selectRaw('COALESCE(SUM(m.m_out), 0) as qty_out_total')
            ->selectRaw('COALESCE(SUM(m.adj_in), 0) - COALESCE(SUM(m.adj_out), 0) as adjustment_total')
            ->selectRaw('MIN(os.expired_at) as expired_at')
            ->selectRaw('MIN(os.source_type) as source_type, MIN(os.source_id) as source_id')
            ->selectRaw("{$sourceDistinct} as source_count");

        if ($supplierId) {
            $grouped->havingRaw('SUM(CASE WHEN COALESCE(pb.supplier_id, op.supplier_id) = ? THEN 1 ELSE 0 END) > 0', [$supplierId]);
        }

        if ($request->filled('sumber')) {
            if ($request->sumber === 'multiple') {
                $grouped->havingRaw("{$sourceDistinct} > 1");
            } else {
                $grouped->havingRaw("{$sourceDistinct} = 1 AND MIN(os.source_type) = ?", [$request->sumber]);
            }
        }

        $status = $request->input('status');
        if ($status === 'expired') {
            $grouped->havingRaw("{$expiredExpr} = 1");
        } elseif ($status === 'available') {
            $grouped->havingRaw("{$expiredExpr} = 0 AND SUM(os.qty) > 0");
        } elseif ($status === 'empty') {
            $grouped->havingRaw("{$expiredExpr} = 0 AND SUM(os.qty) <= 0");
        }

        $recordsFiltered = DB::query()->fromSub($grouped, 'g')->count();

        $rows = DB::query()->fromSub($grouped, 'g')
            ->orderByRaw("g.{$orderBy} {$orderDir}") // $orderBy dari whitelist, $orderDir sudah divalidasi
            ->orderBy('g.product_id', $orderDir)
            ->offset($start)
            ->limit($length)
            ->get();

        // Nama supplier hanya dicari untuk produk di halaman ini (maks 100 produk).
        $supplierMap = collect();
        if ($rows->isNotEmpty()) {
            $supplierMap = DB::table('owner_stocks as os')
                ->whereNull('os.deleted_at')
                ->where('os.owner_id', $outletId)
                ->whereIn('os.product_id', $rows->pluck('product_id'))
                ->leftJoin('stocks as st', 'st.id', '=', 'os.stock_id')
                ->leftJoin('pembelians as pb', 'pb.id', '=', 'st.pembelian_id')
                ->leftJoin('outlet_purchases as op', function ($join) {
                    $join->on('op.id', '=', 'os.source_id')
                        ->where('os.source_type', '=', OutletPurchase::class);
                })
                ->join('suppliers as su', 'su.id', '=', DB::raw('COALESCE(pb.supplier_id, op.supplier_id)'))
                ->select('os.product_id', 'su.name as supplier_name')
                ->distinct()
                ->get()
                ->groupBy('product_id')
                ->map(fn ($items) => $items->pluck('supplier_name')->sort()->join(', '));
        }

        $outletName = Outlet::whereKey($outletId)->value('name');
        $today = today()->toDateString();

        $data = $rows->map(function ($row) use ($supplierMap, $outletName, $today) {
            $expiredAt = $row->expired_at ? substr((string) $row->expired_at, 0, 10) : null;
            $qty = (int) $row->qty;
            $single = (int) $row->source_count <= 1;

            return [
                'owner_id' => (int) $row->owner_id,
                'product_id' => (int) $row->product_id,
                'outlet' => $outletName ?? '-',
                'code' => $row->product_code ?? '-',
                'name' => $row->product_name ?? '-',
                'category' => $row->category_name ?: '-',
                'suppliers' => $supplierMap->get($row->product_id) ?: '-',
                'source_type' => $single ? $row->source_type : 'multiple',
                'source_id' => $single ? $row->source_id : null,
                'batch_count' => (int) $row->batch_count,
                'hpp' => (float) $row->hpp,
                'qty_in' => (int) $row->qty_in_total,
                'qty_out' => (int) $row->qty_out_total,
                'adjustment' => (int) $row->adjustment_total,
                'qty' => $qty,
                'satuan' => $row->satuan ?? '',
                'expired_at' => $expiredAt,
                'status' => $expiredAt && $expiredAt < $today ? 'expired' : ($qty > 0 ? 'available' : 'empty'),
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data->values(),
        ]);
    }

    public function show(Request $request, Outlet $owner)
    {
        $request->merge(['outlet_id' => $owner->id]);
        OutletAccess::id($request);

        return redirect()->route('owner-stocks.index', ['outlet_id' => $owner->id]);
    }

    public function history(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|integer|exists:outlets,id',
            'product_id' => 'required|integer|exists:products,id',
        ]);
        $outletId = OutletAccess::id($request);
        $stockIds = OwnerStock::where('owner_id', $outletId)
            ->where('product_id', $request->product_id)
            ->pluck('id');

        $activities = Activity::where('subject_type', (new OwnerStock)->getMorphClass())
            ->whereIn('subject_id', $stockIds)
            ->with('causer')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($activity) => [
                'date' => optional($activity->created_at)->format('d M Y H:i'),
                'user' => $activity->causer?->name ?? 'System',
                'event' => $activity->event,
                'properties' => $activity->properties,
            ])
            ->values();

        $purchaseItems = OutletPurchaseItem::with(['purchase.creator'])
            ->where('product_id', $request->product_id)
            ->whereHas('purchase', fn ($query) => $query->where('outlet_id', $outletId))
            ->get();
        $purchaseIds = $purchaseItems->pluck('outlet_purchase_id')->unique()->values();

        $movements = StockMovement::where('owner_id', $outletId)
            ->where('product_id', $request->product_id)
            ->where(function ($query) use ($stockIds, $purchaseIds) {
                $query->whereIn('owner_stock_id', $stockIds);
                if ($purchaseIds->isNotEmpty()) {
                    $query->orWhere(function ($purchaseQuery) use ($purchaseIds) {
                        $purchaseQuery->where('reference_type', OutletPurchase::class)
                            ->whereIn('reference_id', $purchaseIds);
                    });
                }
            })
            ->with('user')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        // Older direct purchases may have an OwnerStock row but no movement
        // yet. Add their inbound line to this history without duplicating
        // purchases that already have a recorded movement.
        foreach ($purchaseItems as $item) {
            $hasMovement = $movements->contains(fn ($movement) => $movement->reference_type === OutletPurchase::class
                && (int) $movement->reference_id === (int) $item->outlet_purchase_id
                && (! $item->owner_stock_id || (int) $movement->owner_stock_id === (int) $item->owner_stock_id));

            if (! $hasMovement) {
                $purchase = $item->purchase;
                $movements->push((object) [
                    'id' => -$item->id,
                    'created_at' => $purchase?->created_at ?? $purchase?->purchase_date,
                    'user' => $purchase?->creator,
                    'type' => 'in',
                    'reference_type' => OutletPurchase::class,
                    'reference_id' => $purchase?->id,
                    'owner_stock_id' => $item->owner_stock_id,
                    'qty_in' => (int) $item->qty,
                    'qty_out' => 0,
                    'balance' => null,
                    'notes' => 'Pembelian langsung outlet ' . ($purchase?->code ?? ''),
                ]);
            }
        }

        $running = 0;
        $movements = $movements
            ->sortBy(fn ($movement) => [
                optional($movement->created_at)->timestamp ?? 0,
                (int) $movement->id,
            ])
            ->map(function ($movement) use (&$running) {
                $running += (int) $movement->qty_in - (int) $movement->qty_out;

                return [
                    'date' => optional($movement->created_at)->format('d M Y H:i'),
                    'user' => $movement->user?->name ?? 'System',
                    'type' => $movement->type,
                    'qty_in' => (int) $movement->qty_in,
                    'qty_out' => (int) $movement->qty_out,
                    'balance' => $running,
                    'reference_type' => $movement->reference_type,
                    'reference_id' => $movement->reference_id,
                    'notes' => $movement->notes,
                ];
            })
            ->values();

        return response()->json(['success' => true, 'activities' => $activities, 'movements' => $movements]);
    }

    public function kartu(Request $request)
    {
        $outlets = OutletAccess::outlets();
        $outletId = OutletAccess::id($request, false);
        $selectedOwner = $outletId ? Outlet::find($outletId) : null;

        // Daftar produk TIDAK lagi dimuat ke halaman. Produk dicari lewat AJAX
        // (searchKartuProducts), sama seperti Kartu Stok Gudang. Yang dimuat hanya
        // satu produk kalau halaman dibuka dari tombol "Kartu" di menu Stock Toko.
        $selectedProduct = $request->filled('product_id')
            ? Product::select(['id', 'code', 'name'])->find($request->product_id)
            : null;

        return view('owner-stocks.kartu', [
            'outlets' => $outlets,
            'selectedOwner' => $selectedOwner,
            'selectedProduct' => $selectedProduct,
            'suppliers' => $this->supplierOptions($this->scopeOutletIds($outlets, $selectedOwner)),
            'categoryOptions' => $this->categoryOptions(),
            'locationOptions' => $this->locationOptions(),
        ]);
    }

    /**
     * Select2 AJAX untuk Kartu Stock Toko (padanan StockController::searchStock di gudang).
     * Hanya mengembalikan 20 produk per halaman, tanpa COUNT(*) (cukup ambil 1 baris ekstra).
     */
    public function searchKartuProducts(Request $request)
    {
        $request->validate([
            'outlet_id' => 'nullable|integer|exists:outlets,id',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
        ]);

        $search = trim((string) $request->get('q', ''));
        $page = max((int) $request->get('page', 1), 1);
        $perPage = 20;

        $outletId = OutletAccess::id($request, false);
        $outletIds = OutletAccess::outlets()->pluck('id');

        $query = Product::query()
            ->whereHas('ownerStocks', function ($ownerQuery) use ($outletIds, $outletId, $request) {
                $ownerQuery->whereIn('owner_id', $outletIds)
                    ->when($outletId, fn ($q) => $q->where('owner_id', $outletId));

                if ($request->filled('supplier_id')) {
                    $this->applySupplierFilter($ownerQuery, $request->supplier_id);
                }
            })
            ->when($request->filled('kategori'), fn ($q) => $q->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', $request->kategori)))
            ->when($request->filled('lokasi'), fn ($q) => $q->where('lokasi', $request->lokasi));

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "{$search}%");
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
                'text' => "{$product->name} | {$product->code}",
            ])->values(),
            'pagination' => ['more' => $rows->count() > $perPage],
        ]);
    }

    public function getKartuData(Request $request)
    {
        $request->validate([
            'outlet_id' => 'nullable|integer|exists:outlets,id',
            'product_id' => 'required|integer|exists:products,id',
        ], [
            'product_id.required' => 'Produk harus dipilih.',
            'product_id.exists' => 'Produk yang dipilih tidak ditemukan.',
        ]);

        $outletId = OutletAccess::id($request, false);
        $outletIds = OutletAccess::outlets()->pluck('id');
        $product = Product::findOrFail($request->product_id);

        return response()->json(
            app(OwnerKartuStokBuilder::class)->build($product, $outletIds, $outletId)
        );
    }

    public function opname(Request $request)
    {
        $outlets = OutletAccess::outlets();
        $outletId = OutletAccess::id($request, false);
        $selectedOwner = $outletId ? Outlet::find($outletId) : null;

        return view('owner-stocks.opname', [
            'outlets' => $outlets,
            'selectedOwner' => $selectedOwner,
            'suppliers' => $this->supplierOptions($this->scopeOutletIds($outlets, $selectedOwner), true),
            'categoryOptions' => $this->categoryOptions(),
            'locationOptions' => $this->locationOptions(),
        ]);
    }

    /**
     * Sama seperti opname gudang: supplier WAJIB dipilih dulu, baru stok supplier itu dimuat.
     * Tanpa supplier, seluruh stok outlet (bisa ratusan ribu baris) tidak akan pernah dikirim ke browser.
     */
    public function getOpnameData(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|integer|exists:outlets,id',
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'kategori' => 'nullable|string',
            'lokasi' => 'nullable|string',
        ], [
            'supplier_id.required' => 'Supplier harus dipilih.',
            'supplier_id.exists' => 'Supplier yang dipilih tidak ditemukan.',
        ]);
        $outletId = OutletAccess::id($request);

        $stocks = DB::table('owner_stocks as os')
            ->whereNull('os.deleted_at')
            ->where('os.owner_id', $outletId)
            // Produk sudah dihapus -> skip dari opname (sama seperti gudang).
            ->join('products as p', function ($join) {
                $join->on('p.id', '=', 'os.product_id')->whereNull('p.deleted_at');
            })
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('stocks as st', 'st.id', '=', 'os.stock_id')
            ->leftJoin('pembelians as pb', 'pb.id', '=', 'st.pembelian_id')
            ->leftJoin('outlet_purchases as op', function ($join) {
                $join->on('op.id', '=', 'os.source_id')
                    ->where('os.source_type', '=', OutletPurchase::class);
            })
            ->whereRaw('COALESCE(pb.supplier_id, op.supplier_id) = ?', [$request->supplier_id])
            ->when($request->filled('kategori'), fn ($query) => $query->where('c.name', $request->kategori))
            ->when($request->filled('lokasi'), fn ($query) => $query->where('p.lokasi', $request->lokasi))
            ->orderBy('os.product_id')
            ->orderBy('os.created_at')
            ->orderBy('os.id')
            ->get([
                'os.id', 'os.product_id', 'os.batch_number', 'os.qty', 'os.hpp',
                'p.name as product_name', 'p.code as product_code', 'p.satuan', 'p.lokasi',
                'c.name as category_name', 'st.serial_number',
            ])
            ->map(fn ($stock) => [
                'id' => $stock->id,
                'product_id' => $stock->product_id,
                'product_name' => $stock->product_name,
                'product_code' => $stock->product_code,
                'batch_number' => $stock->batch_number,
                'serial_number' => $stock->serial_number,
                'qty' => (int) $stock->qty,
                'hpp' => (float) $stock->hpp,
                'satuan' => $stock->satuan ?? 'pcs',
                'kategori' => $stock->category_name ?? '-',
                'lokasi' => $stock->lokasi ?? '-',
            ]);

        return response()->json(['stocks' => $stocks->values()]);
    }

    public function saveOpname(Request $request, OutletStockService $stockService)
    {
        $request->validate([
            'outlet_id' => 'required|integer|exists:outlets,id',
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'adjustment_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.owner_stock_id' => 'required|exists:owner_stocks,id',
            'items.*.physical_qty' => 'required|numeric|min:0',
            'items.*.keterangan' => 'nullable|string',
        ], [
            'supplier_id.required' => 'Supplier harus dipilih.',
            'adjustment_date.required' => 'Tanggal penyesuaian harus diisi.',
            'adjustment_date.date' => 'Tanggal penyesuaian harus berupa tanggal yang valid.',
            'items.required' => 'Item harus diisi.',
            'items.*.owner_stock_id.exists' => 'Stok yang dipilih tidak ditemukan.',
            'items.*.physical_qty.required' => 'Stok fisik harus diisi.',
            'items.*.physical_qty.numeric' => 'Stok fisik harus berupa angka.',
        ]);
        $outletId = OutletAccess::id($request);

        // Semua batch diambil dalam 1 query (bukan 1 query per item), sekaligus
        // memastikan batch memang milik outlet & supplier yang dipilih.
        $ownerStockQuery = OwnerStock::where('owner_id', $outletId)
            ->whereIn('id', collect($request->items)->pluck('owner_stock_id')->unique()->values());
        $this->applySupplierFilter($ownerStockQuery, $request->supplier_id);
        $ownerStocks = $ownerStockQuery->get()->keyBy('id');

        DB::transaction(function () use ($request, $ownerStocks, $stockService) {
            foreach ($request->items as $item) {
                $ownerStock = $ownerStocks->get((int) $item['owner_stock_id']);

                abort_unless($ownerStock, 422, 'Ada batch stok yang bukan milik outlet / supplier yang dipilih.');

                // adjust() mengunci baris, menghitung selisih terhadap qty terbaru,
                // dan tidak mencatat apa pun kalau selisihnya 0.
                $stockService->adjust(
                    $ownerStock,
                    (float) $item['physical_qty'],
                    $request->adjustment_date,
                    $item['keterangan'] ?? null,
                    auth()->user()
                );
            }
        });

        return response()->json(['success' => true, 'message' => 'Stock opname toko berhasil disimpan.']);
    }

    /**
     * Outlet yang dipakai untuk menyaring daftar supplier: outlet terpilih, atau semua outlet yang boleh diakses.
     */
    protected function scopeOutletIds($outlets, ?Outlet $selectedOwner)
    {
        return $selectedOwner ? collect([$selectedOwner->id]) : $outlets->pluck('id');
    }

    protected function categoryOptions()
    {
        return collect(Cache::remember('owner-stock:categories', 600, fn () => \App\Models\Category::orderBy('name')->pluck('name')->all()));
    }

    protected function locationOptions()
    {
        return collect(Cache::remember('owner-stock:locations', 600, fn () => Product::whereNotNull('lokasi')
            ->where('lokasi', '!=', '')
            ->distinct()
            ->orderBy('lokasi')
            ->pluck('lokasi')
            ->all()));
    }

    protected function sourceOptions($outletIds)
    {
        $key = 'owner-stock:sources:' . md5($outletIds->sort()->implode(','));

        return collect(Cache::remember($key, 600, fn () => OwnerStock::whereIn('owner_id', $outletIds)
            ->whereNotNull('source_type')
            ->where('source_type', '!=', '')
            ->distinct()
            ->orderBy('source_type')
            ->pluck('source_type')
            ->all()));
    }

    /**
     * Supplier yang benar-benar punya stok / belanja langsung di outlet yang boleh diakses user.
     * Sebelumnya memakai whereHas('pembelians.stocks') yang menyisir seluruh stok gudang.
     */
    protected function supplierOptions($outletIds, bool $requireSku = false)
    {
        $key = 'owner-stock:suppliers:' . md5($outletIds->sort()->implode(',')) . ($requireSku ? ':sku' : '');

        $rows = Cache::remember($key, 600, function () use ($outletIds, $requireSku) {
            $fromWarehouse = DB::table('owner_stocks as os')
                ->join('stocks as st', 'st.id', '=', 'os.stock_id')
                ->join('pembelians as pb', 'pb.id', '=', 'st.pembelian_id')
                ->whereNull('os.deleted_at')
                ->whereIn('os.owner_id', $outletIds)
                ->when($requireSku, fn ($query) => $query->whereNotNull('st.sku'))
                ->distinct()
                ->pluck('pb.supplier_id');

            $direct = DB::table('outlet_purchases')
                ->whereIn('outlet_id', $outletIds)
                ->distinct()
                ->pluck('supplier_id');

            return Supplier::whereIn('id', $fromWarehouse->merge($direct)->filter()->unique()->values())
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($supplier) => ['id' => $supplier->id, 'name' => $supplier->name])
                ->all();
        });

        return collect($rows)->map(fn ($row) => (object) $row);
    }

    /**
     * Owner stock can come from warehouse stock or from a direct outlet purchase.
     * Keep supplier filtering useful for both sources.
     */
    protected function applySupplierFilter($query, int|string $supplierId)
    {
        return $query->where(function ($supplierQuery) use ($supplierId) {
            $supplierQuery->whereHas('stock.pembelian', fn ($pembelianQuery) => $pembelianQuery->where('supplier_id', $supplierId))
                ->orWhere(function ($sourceQuery) use ($supplierId) {
                    $sourceQuery->where('source_type', \App\Models\OutletPurchase::class)
                        ->whereIn('source_id', \App\Models\OutletPurchase::query()
                            ->where('supplier_id', $supplierId)
                            ->select('id'));
                });
        });
    }
}