<?php

namespace App\Http\Controllers;

use App\Models\OutletPurchase;
use App\Models\OutletPurchaseItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\OutletStockService;
use App\Support\IndonesianNumber;
use App\Support\OutletAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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
            'purchase' => null,
            'outlets' => OutletAccess::outlets(),
            'selectedOutletId' => OutletAccess::id($request, false),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'initialItems' => $this->initialItems(null),
        ]);
    }

    /**
     * Simpan pembelian dengan status pending. Stock toko BELUM bertambah:
     * stok baru masuk setelah penerimaan barang diselesaikan (receive()).
     */
    public function store(Request $request)
    {
        $this->ensurePurchaseAccess();
        $this->validatePayload($request);
        $outletId = OutletAccess::id($request);

        $purchase = DB::transaction(function () use ($request, $outletId) {
            $purchase = OutletPurchase::create([
                'code' => 'POUT-' . now()->format('YmdHis') . '-' . random_int(10, 99),
                'outlet_id' => $outletId,
                'supplier_id' => $request->supplier_id,
                'created_by' => auth()->id(),
                'purchase_date' => $request->purchase_date,
                'invoice_number' => $request->invoice_number,
                'paid_amount' => $request->input('paid_amount', 0),
                'payment_method' => $request->payment_method,
                'status' => OutletPurchase::STATUS_PENDING,
                'notes' => $request->notes,
            ]);

            $this->syncItems($purchase, $request->items);

            return $purchase;
        });

        return redirect()
            ->route('outlet-purchases.index')
            ->with('toast_success', "Belanja langsung {$purchase->code} tersimpan. Stock toko bertambah setelah penerimaan barang diselesaikan.");
    }

    public function edit(OutletPurchase $outletPurchase)
    {
        $this->ensurePurchaseAccess();
        $this->authorizeOutlet($outletPurchase);

        if (! $outletPurchase->isPending()) {
            return redirect()
                ->route('outlet-purchases.show', $outletPurchase)
                ->with('toast_error', 'Pembelian ini sudah completed dan tidak bisa diedit lagi.');
        }

        $outletPurchase->load('items.product');

        return view('outlet-purchases.create', [
            'purchase' => $outletPurchase,
            'outlets' => OutletAccess::outlets(),
            'selectedOutletId' => $outletPurchase->outlet_id,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'initialItems' => $this->initialItems($outletPurchase),
        ]);
    }

    public function update(Request $request, OutletPurchase $outletPurchase)
    {
        $this->ensurePurchaseAccess();
        $this->authorizeOutlet($outletPurchase);
        $this->validatePayload($request);
        $outletId = OutletAccess::id($request);

        try {
            DB::transaction(function () use ($request, $outletPurchase, $outletId) {
                $purchase = OutletPurchase::lockForUpdate()->findOrFail($outletPurchase->id);
                if (! $purchase->isPending()) {
                    throw new RuntimeException('Pembelian ini sudah completed dan tidak bisa diedit lagi.');
                }

                $purchase->update([
                    'outlet_id' => $outletId,
                    'supplier_id' => $request->supplier_id,
                    'purchase_date' => $request->purchase_date,
                    'invoice_number' => $request->invoice_number,
                    'notes' => $request->notes,
                ]);

                $this->syncItems($purchase, $request->items);
            });
        } catch (RuntimeException $e) {
            return redirect()->route('outlet-purchases.show', $outletPurchase)->with('toast_error', $e->getMessage());
        }

        return redirect()
            ->route('outlet-purchases.index')
            ->with('toast_success', "Belanja langsung {$outletPurchase->code} berhasil diperbarui.");
    }

    /**
     * Untuk pembelian pending, halaman ini adalah halaman penerimaan barang.
     * Untuk pembelian completed, halaman ini hanya detail (read-only).
     */
    public function show(OutletPurchase $outletPurchase)
    {
        $this->ensurePurchaseAccess();
        $this->authorizeOutlet($outletPurchase);

        return view('outlet-purchases.show', [
            'purchase' => $outletPurchase->load(['outlet', 'supplier', 'creator', 'receiver', 'items.product']),
        ]);
    }

    /**
     * Selesaikan penerimaan barang: qty yang datang masuk ke stock toko (batch, HPP, kartu stok),
     * lalu status menjadi completed dan tidak bisa diedit lagi.
     */
    public function receive(Request $request, OutletPurchase $outletPurchase, OutletStockService $stockService)
    {
        $this->ensurePurchaseAccess();
        $this->authorizeOutlet($outletPurchase);

        $request->validate([
            'items' => 'required|array',
            'items.*.qty_received' => 'nullable|integer|min:0',
        ], [
            'items.required' => 'Data item penerimaan wajib diisi.',
            'items.*.qty_received.integer' => 'Qty diterima harus berupa angka bulat.',
            'items.*.qty_received.min' => 'Qty diterima tidak boleh negatif.',
        ]);

        $itemData = $request->input('items', []);

        try {
            DB::transaction(function () use ($outletPurchase, $itemData, $stockService) {
                // Cegah terima ganda (stok & movement akan tercatat dua kali)
                $purchase = OutletPurchase::lockForUpdate()->findOrFail($outletPurchase->id);
                if (! $purchase->isPending()) {
                    throw new RuntimeException('Pembelian ini sudah completed dan tidak bisa diproses ulang.');
                }

                $purchase->load('items.product');
                $subtotal = 0;

                foreach ($purchase->items as $item) {
                    $qtyReceived = (int) ($itemData[$item->id]['qty_received'] ?? $item->qty);

                    if ($qtyReceived < 0 || $qtyReceived > (int) $item->qty) {
                        throw new RuntimeException(
                            "Qty diterima untuk {$item->product?->name} ({$qtyReceived}) tidak boleh melebihi qty dipesan ({$item->qty})."
                        );
                    }

                    $ownerStockId = null;
                    if ($qtyReceived > 0) {
                        $ownerStock = $stockService->receive(
                            (int) $purchase->outlet_id,
                            (int) $item->product_id,
                            $qtyReceived,
                            (float) $item->harga_beli,
                            [
                                'expired_at' => $item->expired_at,
                                'batch_number' => $item->batch_number,
                                'source_type' => OutletPurchase::class,
                                'source_id' => $purchase->id,
                                'notes' => "Pembelian langsung outlet {$purchase->code} dari supplier",
                            ],
                            OutletPurchase::class,
                            $purchase->id,
                            auth()->user()
                        );
                        $ownerStockId = $ownerStock->id;
                    }

                    $lineSubtotal = $qtyReceived * (float) $item->harga_beli;
                    $item->update([
                        'owner_stock_id' => $ownerStockId,
                        'qty_received' => $qtyReceived,
                        'subtotal' => $lineSubtotal,
                    ]);
                    $subtotal += $lineSubtotal;
                }

                $purchase->update([
                    'status' => OutletPurchase::STATUS_RECEIVED,
                    'subtotal' => $subtotal,
                    'received_by' => auth()->id(),
                    'received_at' => now(),
                ]);
            });
        } catch (\Exception $e) {
            return back()->with('toast_error', $e->getMessage());
        }

        return redirect()
            ->route('outlet-purchases.index')
            ->with('toast_success', "Penerimaan barang {$outletPurchase->code} selesai dan stock toko sudah bertambah.");
    }

    /**
     * Validasi form (dipakai store & update). Angka format Indonesia dikonversi dulu.
     */
    private function validatePayload(Request $request): void
    {
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
    }

    /**
     * Ganti seluruh item pembelian pending (belum ada stok yang masuk, jadi aman dibuat ulang).
     */
    private function syncItems(OutletPurchase $purchase, array $items): void
    {
        $purchase->items()->delete();
        $subtotal = 0;
        $position = 0;

        foreach ($items as $item) {
            $position++;
            $qty = (int) $item['qty'];
            $hargaBeli = (float) $item['harga_beli'];
            $lineSubtotal = $qty * $hargaBeli;

            OutletPurchaseItem::create([
                'outlet_purchase_id' => $purchase->id,
                'product_id' => (int) $item['product_id'],
                'owner_stock_id' => null,
                'qty' => $qty,
                'qty_received' => null,
                'harga_beli' => $hargaBeli,
                'subtotal' => $lineSubtotal,
                'batch_number' => ($item['batch_number'] ?? null) ?: $purchase->code . '-' . $position,
                'expired_at' => $item['expired_at'] ?? null,
            ]);
            $subtotal += $lineSubtotal;
        }

        $purchase->update(['subtotal' => $subtotal]);
    }

    /**
     * Baris item awal untuk form: input lama (validasi gagal) atau item pembelian yang diedit.
     */
    private function initialItems(?OutletPurchase $purchase): array
    {
        $old = old('items');

        if (is_array($old)) {
            $products = Product::whereIn('id', collect($old)->pluck('product_id')->filter())
                ->get(['id', 'code', 'name'])->keyBy('id');

            return collect($old)->values()->map(fn ($item) => [
                'product_id' => $item['product_id'] ?? null,
                'label' => isset($products[$item['product_id'] ?? null])
                    ? $products[$item['product_id']]->code . ' — ' . $products[$item['product_id']]->name
                    : null,
                'qty' => $item['qty'] ?? 1,
                'harga_beli' => $item['harga_beli'] ?? '',
                'expired_at' => $item['expired_at'] ?? '',
                'batch_number' => $item['batch_number'] ?? null,
            ])->all();
        }

        if (! $purchase) {
            return [];
        }

        return $purchase->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'label' => $item->product ? $item->product->code . ' — ' . $item->product->name : null,
            'qty' => $item->qty,
            'harga_beli' => $item->harga_beli,
            'expired_at' => $item->expired_at?->format('Y-m-d') ?? '',
            'batch_number' => $item->batch_number,
        ])->all();
    }

    /** Staff outlet hanya boleh membuka pembelian outlet miliknya. */
    private function authorizeOutlet(OutletPurchase $purchase): void
    {
        $user = auth()->user();

        if ($user && in_array($user->role, ['staff-outlet', 'kasir'], true)) {
            abort_unless(
                $user->outlet_id && (int) $user->outlet_id === (int) $purchase->outlet_id,
                403,
                'User tidak memiliki akses ke outlet tersebut.'
            );
        }
    }

    private function ensurePurchaseAccess(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['superadmin', 'admin-gudang', 'owner', 'staff-outlet'], true), 403);
    }
}