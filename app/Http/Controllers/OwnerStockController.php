<?php

namespace App\Http\Controllers;

use App\Models\OwnerStock;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\OutletStockService;
use App\Support\OutletAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class OwnerStockController extends Controller
{
    public function index(Request $request)
    {
        $outlets = OutletAccess::outlets();
        $outletId = OutletAccess::id($request, false);
        $selectedOwner = $outletId ? Outlet::find($outletId) : null;

        $categoryOptions = \App\Models\Category::orderBy('name')->pluck('name');
        $locationOptions = Product::whereNotNull('lokasi')
            ->where('lokasi', '!=', '')
            ->distinct()
            ->orderBy('lokasi')
            ->pluck('lokasi');
        $sourceOptions = OwnerStock::whereIn('owner_id', $outlets->pluck('id'))
            ->whereNotNull('source_type')
            ->where('source_type', '!=', '')
            ->distinct()
            ->orderBy('source_type')
            ->pluck('source_type');
        $supplierOptions = Supplier::where(function ($query) {
                $query->whereHas('pembelians.stocks')
                    ->orWhereIn('id', \App\Models\OutletPurchase::query()->select('supplier_id'));
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $stockRows = $outlets->isNotEmpty()
            ? OwnerStock::with(['owner', 'product.category', 'stock.pembelian.supplier'])
                ->withSum('movements as qty_in_total', 'qty_in')
                ->withSum('movements as qty_out_total', 'qty_out')
                ->withSum(['movements as adjustment_in_total' => function ($query) {
                    $query->where('type', 'adjustment');
                }], 'qty_in')
                ->withSum(['movements as adjustment_out_total' => function ($query) {
                    $query->where('type', 'adjustment');
                }], 'qty_out')
                ->whereIn('owner_id', $outlets->pluck('id'))
                ->when($outletId, fn ($query) => $query->where('owner_id', $outletId))
                ->when($request->filled('search'), function ($query) use ($request) {
                    $search = trim($request->search);
                    $query->where(function ($searchQuery) use ($search) {
                        $searchQuery->whereHas('product', fn ($productQuery) => $productQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%"))
                            ->orWhere('batch_number', 'like', "%{$search}%");
                        });
                })
                ->when($request->filled('kategori'), fn ($query) => $query->whereHas('product.category', fn ($categoryQuery) => $categoryQuery->where('name', $request->kategori)))
                ->when($request->filled('lokasi'), fn ($query) => $query->whereHas('product', fn ($productQuery) => $productQuery->where('lokasi', $request->lokasi)))
                ->when($request->filled('sumber'), fn ($query) => $query->where('source_type', $request->sumber))
                ->when($request->input('status') === 'available', fn ($query) => $query->where('qty', '>', 0)->where(function ($dateQuery) {
                    $dateQuery->whereNull('expired_at')->orWhereDate('expired_at', '>=', today());
                }))
                ->when($request->input('status') === 'empty', fn ($query) => $query->where('qty', '<=', 0))
                ->when($request->input('status') === 'expired', fn ($query) => $query->whereDate('expired_at', '<', today()))
                ->orderBy('product_id')
                ->orderBy('created_at')
                ->get()
            : collect();

        $directPurchaseSupplierMap = $stockRows->filter(fn ($row) => $row->source_type === \App\Models\OutletPurchase::class && $row->source_id)
            ->pluck('source_id')
            ->unique()
            ->pipe(fn ($ids) => $ids->isNotEmpty()
                ? \App\Models\OutletPurchase::whereIn('id', $ids)->pluck('supplier_id', 'id')
                : collect());

        // The operational stock view is product-based. Batch rows remain
        // available in the stock card, but duplicate product codes must not
        // make the outlet balance look fragmented.
        $stocks = $stockRows->groupBy(fn ($row) => $row->owner_id . ':' . $row->product_id)->map(function ($rows) use ($directPurchaseSupplierMap, $supplierOptions) {
            $first = $rows->first();
            $qty = (int) $rows->sum('qty');
            $qtyIn = (int) $rows->sum('qty_in_total');
            $qtyOut = (int) $rows->sum('qty_out_total');
            $adjustment = (int) $rows->sum('adjustment_in_total') - (int) $rows->sum('adjustment_out_total');
            $cost = $rows->sum(fn ($row) => (int) $row->qty * (float) $row->hpp);
            $sources = $rows->map(fn ($row) => [$row->source_type, $row->source_id])
                ->unique(fn ($source) => implode(':', $source))
                ->values();
            $supplierIds = $rows->map(fn ($row) => $row->stock?->pembelian?->supplier_id
                ?: ($row->source_type === \App\Models\OutletPurchase::class ? $directPurchaseSupplierMap->get($row->source_id) : null))
                ->filter()
                ->unique()
                ->values();

            return (object) [
                'product_id' => $first->product_id,
                'owner_id' => $first->owner_id,
                'owner' => $first->owner,
                'product' => $first->product,
                'category' => $first->product?->category?->name,
                'lokasi' => $first->product?->lokasi,
                'supplier_ids' => $supplierIds->all(),
                'suppliers' => $supplierIds->map(fn ($id) => $supplierOptions->firstWhere('id', $id)?->name)->filter()->join(', '),
                'batch_number' => $rows->pluck('batch_number')->filter()->unique()->join(', '),
                'batch_count' => $rows->count(),
                'hpp' => $qty > 0 ? $cost / $qty : (float) $first->hpp,
                'qty' => $qty,
                'qty_in_total' => $qtyIn,
                'qty_out_total' => $qtyOut,
                'adjustment_in_total' => (int) $rows->sum('adjustment_in_total'),
                'adjustment_out_total' => (int) $rows->sum('adjustment_out_total'),
                'source_type' => $sources->count() === 1 ? $sources[0][0] : 'multiple',
                'source_id' => $sources->count() === 1 ? $sources[0][1] : null,
                'expired_at' => $rows->pluck('expired_at')->filter()->sort()->first(),
            ];
        })->sortBy(fn ($stock) => ($stock->owner?->name ?? '') . ' ' . ($stock->product?->name ?? ''))->values();

        return view('owner-stocks.index', compact(
            'outlets',
            'selectedOwner',
            'stocks',
            'categoryOptions',
            'locationOptions',
            'sourceOptions',
            'supplierOptions'
        ));
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

        $activities = OwnerStock::whereIn('id', $stockIds)
            ->get()
            ->flatMap(fn ($stock) => Activity::forSubject($stock)
                ->with('causer')
                ->orderBy('created_at')
                ->get()
                ->map(fn ($activity) => [
                    'date' => optional($activity->created_at)->format('d M Y H:i'),
                    'user' => $activity->causer?->name ?? 'System',
                    'event' => $activity->event,
                    'properties' => $activity->properties,
                ]))
            ->sortBy('date')
            ->values();

        $movements = StockMovement::where('owner_id', $outletId)
            ->where('product_id', $request->product_id)
            ->whereIn('owner_stock_id', $stockIds)
            ->with('user')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn ($movement) => [
                'date' => optional($movement->created_at)->format('d M Y H:i'),
                'user' => $movement->user?->name ?? 'System',
                'type' => $movement->type,
                'qty_in' => (int) $movement->qty_in,
                'qty_out' => (int) $movement->qty_out,
                'balance' => $movement->balance,
                'notes' => $movement->notes,
            ])
            ->values();

        return response()->json(['success' => true, 'activities' => $activities, 'movements' => $movements]);
    }

    public function kartu(Request $request)
    {
        $outlets = OutletAccess::outlets();
        $suppliers = Supplier::where(function ($query) {
                $query->whereHas('pembelians.stocks', fn ($stockQuery) => $stockQuery->whereNotNull('sku'))
                    ->orWhereIn('id', \App\Models\OutletPurchase::query()->select('supplier_id'));
            })
            ->orderBy('name')
            ->get(['id', 'name']);
        $outletId = OutletAccess::id($request, false);
        $selectedOwner = $outletId ? Outlet::find($outletId) : null;
        $ownerIds = $outlets->pluck('id');
        $products = Product::with('category')
            ->whereHas('ownerStocks', function ($query) use ($ownerIds, $selectedOwner, $request) {
                $query->whereIn('owner_id', $ownerIds)
                    ->when($selectedOwner, fn ($ownerQuery) => $ownerQuery->where('owner_id', $selectedOwner->id));
                if ($request->filled('supplier_id')) {
                    $this->applySupplierFilter($query, $request->supplier_id);
                }
            })
            ->when($request->filled('kategori'), fn ($query) => $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', $request->kategori)))
            ->when($request->filled('lokasi'), fn ($query) => $query->where('lokasi', $request->lokasi))
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'category_id', 'lokasi', 'konversi_qty', 'satuan_besar', 'satuan']);

        $categoryOptions = \App\Models\Category::orderBy('name')->pluck('name');
        $locationOptions = Product::whereNotNull('lokasi')
            ->where('lokasi', '!=', '')
            ->distinct()
            ->orderBy('lokasi')
            ->pluck('lokasi');

        return view('owner-stocks.kartu', compact(
            'outlets',
            'selectedOwner',
            'products',
            'suppliers',
            'categoryOptions',
            'locationOptions'
        ));
    }

    public function getKartuData(Request $request)
    {
        $request->validate([
            'outlet_id' => 'nullable|integer|exists:outlets,id',
            'product_id' => 'required|integer|exists:products,id',
        ]);
        $outletId = OutletAccess::id($request, false);
        $outletIds = OutletAccess::outlets()->pluck('id');
        $product = Product::findOrFail($request->product_id);
        $stocks = OwnerStock::with(['stock', 'owner'])
            ->whereIn('owner_id', $outletIds)
            ->when($outletId, fn ($query) => $query->where('owner_id', $outletId))
            ->where('product_id', $product->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $stockIds = $stocks->pluck('id');

        $running = 0;
        $transactions = StockMovement::whereIn('owner_id', $outletIds)
            ->when($outletId, fn ($query) => $query->where('owner_id', $outletId))
            ->where('product_id', $product->id)
            ->whereIn('owner_stock_id', $stockIds)
            ->with('owner')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(function ($movement) use (&$running) {
                $running += (int) $movement->qty_in - (int) $movement->qty_out;

                return [
                    'date' => optional($movement->created_at)->format('Y-m-d H:i'),
                    'type' => $movement->type,
                    'outlet' => $movement->owner?->name ?? '-',
                    'reference_type' => $movement->reference_type,
                    'is_return' => $movement->reference_type === \App\Models\RefundPembelian::class
                        || str_contains(strtolower((string) $movement->notes), 'retur'),
                    'qty_in' => (int) $movement->qty_in,
                    'qty_out' => (int) $movement->qty_out,
                    'balance' => $running,
                    'notes' => $movement->notes,
                ];
            });

        return response()->json([
            'product' => $product->only(['id', 'code', 'name', 'satuan', 'konversi_qty', 'satuan_besar']),
            'summary' => [
                'qty' => (int) $stocks->sum('qty'),
                'batches' => $stocks->map(fn ($stock) => [
                    'id' => $stock->id,
                    'batch_number' => $stock->batch_number,
                    'qty' => (int) $stock->qty,
                    'hpp' => (float) $stock->hpp,
                    'expired_at' => optional($stock->expired_at)->toDateString(),
                    'serial_number' => $stock->stock?->serial_number,
                    'outlet' => $stock->owner?->name ?? '-',
                ])->values(),
            ],
            'transactions' => $transactions->values(),
        ]);
    }

    public function opname(Request $request)
    {
        $outlets = OutletAccess::outlets();
        $suppliers = Supplier::where(function ($query) {
                $query->whereHas('pembelians.stocks', fn ($stockQuery) => $stockQuery->whereNotNull('sku'))
                    ->orWhereIn('id', \App\Models\OutletPurchase::query()->select('supplier_id'));
            })
            ->orderBy('name')
            ->get(['id', 'name']);
        $outletId = OutletAccess::id($request, false);
        $selectedOwner = $outletId ? Outlet::find($outletId) : null;
        $categoryOptions = \App\Models\Category::orderBy('name')->pluck('name');
        $locationOptions = Product::whereNotNull('lokasi')
            ->where('lokasi', '!=', '')
            ->distinct()
            ->orderBy('lokasi')
            ->pluck('lokasi');

        return view('owner-stocks.opname', compact('outlets', 'suppliers', 'selectedOwner', 'categoryOptions', 'locationOptions'));
    }

    public function getOpnameData(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|integer|exists:outlets,id',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'kategori' => 'nullable|string',
            'lokasi' => 'nullable|string',
        ]);
        $outletId = OutletAccess::id($request);

        $stocks = OwnerStock::with(['product.category', 'stock.pembelian.supplier'])
            ->where('owner_id', $outletId)
            ->when($request->filled('supplier_id'), fn ($query) => $this->applySupplierFilter($query, $request->supplier_id))
            ->when($request->filled('kategori'), fn ($query) => $query->whereHas('product.category', fn ($categoryQuery) => $categoryQuery->where('name', $request->kategori)))
            ->when($request->filled('lokasi'), fn ($query) => $query->whereHas('product', fn ($productQuery) => $productQuery->where('lokasi', $request->lokasi)))
            ->orderBy('product_id')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($stock) => [
                'id' => $stock->id,
                'product_id' => $stock->product_id,
                'product_name' => $stock->product?->name,
                'product_code' => $stock->product?->code,
                'batch_number' => $stock->batch_number,
                'serial_number' => $stock->stock?->serial_number,
                'qty' => (int) $stock->qty,
                'hpp' => (float) $stock->hpp,
                'satuan' => $stock->product?->satuan ?? 'pcs',
                'supplier' => $stock->stock?->pembelian?->supplier?->name ?? '-',
                'kategori' => $stock->product?->category?->name ?? '-',
                'lokasi' => $stock->product?->lokasi ?? '-',
            ]);

        return response()->json(['stocks' => $stocks->values()]);
    }

    public function saveOpname(Request $request, OutletStockService $stockService)
    {
        $request->validate([
            'outlet_id' => 'required|integer|exists:outlets,id',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'adjustment_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.owner_stock_id' => 'required|exists:owner_stocks,id',
            'items.*.physical_qty' => 'required|numeric|min:0',
            'items.*.keterangan' => 'nullable|string',
        ]);
        $outletId = OutletAccess::id($request);

        DB::transaction(function () use ($request, $outletId, $stockService) {
            foreach ($request->items as $item) {
                $ownerStock = OwnerStock::where('owner_id', $outletId)
                    ->whereKey($item['owner_stock_id'])
                    ->when($request->filled('supplier_id'), fn ($query) => $this->applySupplierFilter($query, $request->supplier_id))
                    ->firstOrFail();
                $physicalQty = (float) $item['physical_qty'];
                $stockService->adjust(
                    $ownerStock,
                    $physicalQty,
                    $request->adjustment_date,
                    $item['keterangan'] ?? null,
                    auth()->user()
                );
            }
        });

        return response()->json(['success' => true, 'message' => 'Stock opname toko berhasil disimpan.']);
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
