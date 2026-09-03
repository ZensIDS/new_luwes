<?php

namespace App\Http\Controllers;

use App\Models\OwnerStock;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\OutletStockService;
use App\Support\OutletAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OwnerStockController extends Controller
{
    public function index(Request $request)
    {
        $outlets = OutletAccess::outlets();
        $outletId = OutletAccess::id($request, false);
        $selectedOwner = $outletId ? Outlet::find($outletId) : null;

        $stocks = $selectedOwner
            ? OwnerStock::with(['product.category', 'stock'])
                ->withSum('movements as qty_in_total', 'qty_in')
                ->withSum('movements as qty_out_total', 'qty_out')
                ->withSum(['movements as adjustment_in_total' => function ($query) {
                    $query->where('type', 'adjustment');
                }], 'qty_in')
                ->withSum(['movements as adjustment_out_total' => function ($query) {
                    $query->where('type', 'adjustment');
                }], 'qty_out')
                ->where('owner_id', $selectedOwner->id)
                ->when($request->filled('search'), function ($query) use ($request) {
                    $search = trim($request->search);
                    $query->where(function ($searchQuery) use ($search) {
                        $searchQuery->whereHas('product', fn ($productQuery) => $productQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%"))
                            ->orWhere('batch_number', 'like', "%{$search}%");
                    });
                })
                ->orderBy('product_id')
                ->orderBy('created_at')
                ->get()
            : collect();

        return view('owner-stocks.index', compact('outlets', 'selectedOwner', 'stocks'));
    }

    public function show(Request $request, Outlet $owner)
    {
        $request->merge(['outlet_id' => $owner->id]);
        OutletAccess::id($request);

        return redirect()->route('owner-stocks.index', ['outlet_id' => $owner->id]);
    }

    public function kartu(Request $request)
    {
        $outlets = OutletAccess::outlets();
        $outletId = OutletAccess::id($request, false);
        $selectedOwner = $outletId ? Outlet::find($outletId) : null;
        $products = $selectedOwner
            ? Product::whereHas('ownerStocks', fn ($query) => $query
                ->where('owner_id', $selectedOwner->id))
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
            : collect();

        return view('owner-stocks.kartu', compact('outlets', 'selectedOwner', 'products'));
    }

    public function getKartuData(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|integer|exists:outlets,id',
            'product_id' => 'required|integer|exists:products,id',
        ]);
        $outletId = OutletAccess::id($request);
        $product = Product::findOrFail($request->product_id);
        $stocks = OwnerStock::with('stock')
            ->where('owner_id', $outletId)
            ->where('product_id', $product->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $stockIds = $stocks->pluck('id');

        $running = 0;
        $transactions = StockMovement::where('owner_id', $outletId)
            ->where('product_id', $product->id)
            ->whereIn('owner_stock_id', $stockIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(function ($movement) use (&$running) {
                $running += (int) $movement->qty_in - (int) $movement->qty_out;

                return [
                    'date' => optional($movement->created_at)->format('Y-m-d H:i'),
                    'type' => $movement->type,
                    'qty_in' => (int) $movement->qty_in,
                    'qty_out' => (int) $movement->qty_out,
                    'balance' => $movement->balance ?? $running,
                    'notes' => $movement->notes,
                ];
            });

        return response()->json([
            'product' => $product->only(['id', 'code', 'name', 'satuan']),
            'summary' => [
                'qty' => (int) $stocks->sum('qty'),
                'batches' => $stocks->map(fn ($stock) => [
                    'id' => $stock->id,
                    'batch_number' => $stock->batch_number,
                    'qty' => (int) $stock->qty,
                    'hpp' => (float) $stock->hpp,
                    'expired_at' => optional($stock->expired_at)->toDateString(),
                    'serial_number' => $stock->stock?->serial_number,
                ])->values(),
            ],
            'transactions' => $transactions->values(),
        ]);
    }

    public function opname(Request $request)
    {
        $outlets = OutletAccess::outlets();
        $outletId = OutletAccess::id($request, false);
        $selectedOwner = $outletId ? Outlet::find($outletId) : null;

        return view('owner-stocks.opname', compact('outlets', 'selectedOwner'));
    }

    public function getOpnameData(Request $request)
    {
        $request->validate(['outlet_id' => 'required|integer|exists:outlets,id']);
        $outletId = OutletAccess::id($request);

        $stocks = OwnerStock::with(['product', 'stock'])
            ->where('owner_id', $outletId)
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
            ]);

        return response()->json(['stocks' => $stocks->values()]);
    }

    public function saveOpname(Request $request, OutletStockService $stockService)
    {
        $request->validate([
            'outlet_id' => 'required|integer|exists:outlets,id',
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
}
