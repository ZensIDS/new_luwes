<?php

namespace App\Http\Controllers;

use App\Models\OutletPurchase;
use App\Models\OutletPurchaseItem;
use App\Models\Product;
use App\Services\OutletStockService;
use App\Models\Supplier;
use App\Support\OutletAccess;
use App\Support\IndonesianNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OutletPurchaseController extends Controller
{
    public function index(Request $request)
    {
        $this->ensurePurchaseAccess();
        $outletId = OutletAccess::id($request, false);
        $purchases = OutletPurchase::with(['outlet', 'supplier', 'items'])
            ->when($outletId, fn ($query) => $query->where('outlet_id', $outletId))
            ->latest('purchase_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('outlet-purchases.index', compact('purchases'));
    }

    public function create(Request $request)
    {
        $this->ensurePurchaseAccess();
        return view('outlet-purchases.create', [
            'outlets' => OutletAccess::outlets(),
            'selectedOutletId' => OutletAccess::id($request, false),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'products' => Product::orderBy('name')->get(['id', 'code', 'name', 'satuan']),
        ]);
    }

    public function store(Request $request, OutletStockService $stockService)
    {
        $this->ensurePurchaseAccess();
        $request->merge([
            'paid_amount' => IndonesianNumber::parse($request->input('paid_amount')),
            'items' => collect($request->input('items', []))->map(function ($item) {
                $item['harga_beli'] = IndonesianNumber::parse($item['harga_beli'] ?? null);
                return $item;
            })->all(),
        ]);
        $request->validate([
            'outlet_id' => 'required|integer|exists:outlets,id',
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'purchase_date' => 'required|date',
            'invoice_number' => 'nullable|string|max:100',
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.harga_beli' => 'required|numeric|min:0',
            'items.*.batch_number' => 'nullable|string|max:100',
            'items.*.expired_at' => 'nullable|date',
        ]);
        $outletId = OutletAccess::id($request);

        $purchase = DB::transaction(function () use ($request, $outletId, $stockService) {
            $code = 'POUT-' . now()->format('YmdHis') . '-' . random_int(10, 99);
            $purchase = OutletPurchase::create([
                'code' => $code,
                'outlet_id' => $outletId,
                'supplier_id' => $request->supplier_id,
                'created_by' => auth()->id(),
                'purchase_date' => $request->purchase_date,
                'invoice_number' => $request->invoice_number,
                'paid_amount' => $request->input('paid_amount', 0),
                'payment_method' => $request->payment_method,
                'status' => 'received',
                'notes' => $request->notes,
            ]);
            $subtotal = 0;

            foreach ($request->items as $index => $item) {
                $qty = (int) $item['qty'];
                $hargaBeli = (float) $item['harga_beli'];
                $lineSubtotal = $qty * $hargaBeli;
                $product = Product::findOrFail($item['product_id']);
                $batch = $item['batch_number'] ?: $purchase->code . '-' . ($index + 1);

                $ownerStock = $stockService->receive(
                    $outletId,
                    $product->id,
                    $qty,
                    $hargaBeli,
                    [
                        'expired_at' => $item['expired_at'] ?? null,
                        'batch_number' => $batch,
                        'source_type' => OutletPurchase::class,
                        'source_id' => $purchase->id,
                        'notes' => "Pembelian langsung outlet {$purchase->code} dari supplier",
                    ],
                    OutletPurchase::class,
                    $purchase->id,
                    auth()->user()
                );

                OutletPurchaseItem::create([
                    'outlet_purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                    'owner_stock_id' => $ownerStock->id,
                    'qty' => $qty,
                    'harga_beli' => $hargaBeli,
                    'subtotal' => $lineSubtotal,
                    'batch_number' => $batch,
                    'expired_at' => $item['expired_at'] ?? null,
                ]);
                $subtotal += $lineSubtotal;
            }

            $purchase->update(['subtotal' => $subtotal]);

            return $purchase;
        });

        return redirect()->route('outlet-purchases.show', $purchase)->with('toast_success', 'Pembelian langsung dan stock toko berhasil disimpan.');
    }

    public function show(OutletPurchase $outletPurchase)
    {
        $this->ensurePurchaseAccess();
        $request = request();
        $request->merge(['outlet_id' => $outletPurchase->outlet_id]);
        OutletAccess::id($request);

        return view('outlet-purchases.show', [
            'purchase' => $outletPurchase->load(['outlet', 'supplier', 'creator', 'items.product']),
        ]);
    }

    private function ensurePurchaseAccess(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['superadmin', 'admin-gudang', 'owner', 'staff-outlet'], true), 403);
    }
}
