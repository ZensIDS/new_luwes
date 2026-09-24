<?php

namespace App\Http\Controllers;

use App\Models\Kas;
use App\Models\Outlet;
use App\Models\OwnerStock;
use App\Models\RefundPembelian;
use App\Models\RefundPembelianItem;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RefundPembelianController extends Controller
{
    // -----------------------------------------------------------------------
    // AJAX Helpers
    // -----------------------------------------------------------------------

    /**
     * Warehouse stocks (Stock) for a supplier.
     */
    public function getSupplierProducts(Supplier $supplier)
    {
        $stocks = Stock::whereHas('pembelian', fn ($q) => $q->where('supplier_id', $supplier->id))
            ->where('qty_available', '>', 0)
            ->with(['product', 'pembelian'])
            ->get()
            ->map(fn ($s) => [
                'stock_id'       => $s->id,
                'product_id'     => $s->product_id,
                'product_name'   => $s->product->name,
                'sku'            => $s->sku ?? '-',
                'qty_available'  => $s->qty_available,
                'harga_beli'     => $s->harga_beli,
                'pembelian_code' => $s->pembelian->code ?? '-',
            ]);

        return response()->json($stocks->values());
    }

    /**
     * All outlet stocks for an outlet (for retur, showing DO source).
     */
    public function getOutletProducts(Outlet $outlet)
    {
        $stocks = OwnerStock::where('owner_id', $outlet->id)
            ->where('qty', '>', 0)
            ->with(['product', 'stock'])
            ->get()
            ->map(function ($ownerStock) use ($outlet) {
                $doItem = \App\Models\DeliveryOrderItem::where('stock_id', $ownerStock->stock_id)
                    ->whereHas('deliveryOrder', fn ($q) => $q->where('owner_id', $outlet->id)->where('status', 'delivered'))
                    ->with('deliveryOrder:id,code')
                    ->first();

                return [
                    'stock_id'      => $ownerStock->stock_id,
                    'product_id'    => $ownerStock->product_id,
                    'product_name'  => $ownerStock->product->name,
                    'sku'           => $ownerStock->stock->sku ?? '-',
                    'do_code'       => $doItem?->deliveryOrder?->code ?? '-',
                    'qty_available' => $ownerStock->qty,
                ];
            });

        return response()->json($stocks->values());
    }

    // -----------------------------------------------------------------------
    // Resource
    // -----------------------------------------------------------------------

    public function index(Request $request)
    {
        $user    = auth()->user();
        $isStaff = $user->role === 'staff-outlet';

        $query = RefundPembelian::with('user', 'supplier', 'outlet')->latest();

        if ($isStaff) {
            $query->where('type', 'outlet_ke_gudang')
                ->where('outlet_id', $user->outlet_id);
        } else {
            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }
            if ($request->filled('outlet_id')) {
                $query->where('outlet_id', $request->outlet_id);
            }
        }

        return view('refundPembelians.index', [
            'refundPembelians' => $query->get(),
            'selectedType'     => $isStaff ? 'outlet_ke_gudang' : $request->type,
            'selectedOutletId' => $isStaff ? $user->outlet_id : $request->outlet_id,
            'outlets'          => Outlet::orderBy('name')->get(),
            'isStaffOutlet'    => $isStaff,
        ]);
    }

    public function create()
    {
        $lastRetur  = RefundPembelian::latest('id')->first();
        $nextNumber = $lastRetur ? ((int) substr($lastRetur->code, 3) + 1) : 1;
        $code       = 'RTR'.str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

        $user          = auth()->user();
        $isStaffOutlet = $user->role === 'staff-outlet';

        return view('refundPembelians.create', [
            'suppliers'     => Supplier::get(),
            'outlets'       => Outlet::get(),
            'code'          => $code,
            'isStaffOutlet' => $isStaffOutlet,
            'staffOutletId' => $isStaffOutlet ? $user->outlet_id : null,
        ]);
    }

    public function store(Request $request)
    {
        $type = $request->input('type');
        $selectedRows = collect($request->input('selected_rows', []))
            ->map(fn ($row) => (string) $row)
            ->filter()
            ->values();
        $selectedProducts = collect($request->input('product', []))
            ->filter(function ($product, $key) use ($selectedRows) {
                return $selectedRows->isEmpty() || $selectedRows->contains((string) $key);
            })
            ->values()
            ->all();

        $rules = [
            'code'    => 'required|string|unique:refund_pembelians,code',
            'tanggal' => 'required|date',
            'type'    => 'required|in:gudang_ke_supplier,outlet_ke_gudang',
            'product' => 'required|array|min:1',
            'product.*.product_id' => 'required|exists:products,id',
            'product.*.qty'        => 'required|integer|min:1',
            'product.*.alasan'     => 'required|string',
            'product.*.stock_id'   => 'required|exists:stocks,id',
        ];

        if ($type === 'gudang_ke_supplier') {
            $rules['supplier_id'] = 'required|exists:suppliers,id';
        } elseif ($type === 'outlet_ke_gudang') {
            $rules['outlet_id'] = 'required|exists:outlets,id';
        }

        $request->validate($rules, [
            'code.required'    => 'Kode refund wajib diisi.',
            'code.unique'      => 'Kode refund sudah terdaftar.',
            'tanggal.required' => 'Tanggal harus dipilih.',
            'type.required'    => 'Tipe refund wajib dipilih.',
            'supplier_id.required' => 'Supplier wajib diisi untuk retur Gudang ke Supplier.',
            'outlet_id.required'   => 'Outlet wajib diisi untuk retur Outlet ke Gudang.',
            'product.required'     => 'Minimal harus ada satu produk.',
            'product.min'          => 'Minimal harus ada satu produk.',
            'product.*.product_id.required' => 'ID Produk tidak valid.',
            'product.*.qty.min'    => 'Jumlah barang minimal 1.',
            'product.*.alasan.required' => 'Alasan retur wajib diisi.',
        ]);

        DB::beginTransaction();
        try {
            $isOutlet = $request->type === 'outlet_ke_gudang';
            $total    = 0;

            if (empty($selectedProducts)) {
                throw new \Exception('Pilih minimal satu baris retur yang dicentang.');
            }

            $refundPembelian = RefundPembelian::create([
                'code'              => $request->code,
                'tanggal'           => $request->tanggal,
                'type'              => $request->type,
                'status'            => $isOutlet ? 'complete' : 'retur',
                'supplier_id'       => $request->supplier_id,
                'outlet_id'         => $request->outlet_id,
                'delivery_order_id' => $request->delivery_order_id,
                'user_id'           => auth()->id(),
                'total'             => 0,
            ]);

            foreach ($selectedProducts as $product) {

                if (! $isOutlet) {
                    // ── Gudang ke Supplier ──────────────────────────────────────
                    $stock = Stock::findOrFail($product['stock_id']);

                    if ($stock->qty_available < $product['qty']) {
                        throw new \Exception("Stok gudang tidak mencukupi untuk: {$stock->product->name}");
                    }

                    // Reduce warehouse stock (only qty, qty_available is generated)
                    $stock->qty -= $product['qty'];
                    $stock->save();

                    $harga  = (int) str_replace(',', '', $product['harga'] ?? $stock->harga_beli);
                    $total += $harga * $product['qty'];

                    StockMovement::create([
                        'product_id'     => $product['product_id'],
                        'user_id'        => auth()->id(),
                        'type'           => 'retur_ke_supplier',
                        'reference_type' => RefundPembelian::class,
                        'reference_id'   => $refundPembelian->id,
                        'qty_in'         => 0,
                        'qty_out'        => $product['qty'],
                        'balance'        => $stock->qty,
                        'notes'          => "Retur ke supplier - {$refundPembelian->code} - SKU: {$stock->sku} - Alasan: {$product['alasan']}",
                    ]);

                    RefundPembelianItem::create([
                        'refund_pembelian_id' => $refundPembelian->id,
                        'product_id'          => $product['product_id'],
                        'stock_id'            => $stock->id,
                        'sku'                 => $stock->sku,
                        'qty'                 => $product['qty'],
                        'harga'               => $harga,
                        'alasan'              => $product['alasan'],
                    ]);
                } else {
                    // ── Outlet ke Gudang ─────────────────────────────────────────
                    $stock      = Stock::findOrFail($product['stock_id']);
                    $ownerStock = $stock->ownerStock;

                    if (! $ownerStock || $ownerStock->qty < $product['qty']) {
                        throw new \Exception("Stok outlet tidak mencukupi untuk: {$stock->product->name}");
                    }

                    // Reduce outlet stock
                    $ownerStock->qty -= $product['qty'];
                    $ownerStock->save();

                    // Restore warehouse stock
                    $stock->qty += $product['qty'];
                    $stock->save();

                    StockMovement::create([
                        'product_id'     => $product['product_id'],
                        'user_id'        => auth()->id(),
                        'type'           => 'retur_dari_outlet',
                        'reference_type' => RefundPembelian::class,
                        'reference_id'   => $refundPembelian->id,
                        'qty_in'         => $product['qty'],
                        'qty_out'        => 0,
                        'balance'        => $stock->qty,
                        'notes'          => "Retur outlet ke gudang - {$refundPembelian->code} - SKU: {$stock->sku} - Alasan: {$product['alasan']}",
                    ]);

                    RefundPembelianItem::create([
                        'refund_pembelian_id' => $refundPembelian->id,
                        'product_id'          => $product['product_id'],
                        'stock_id'            => $stock->id,
                        'sku'                 => $stock->sku,
                        'qty'                 => $product['qty'],
                        'harga'               => $stock->harga_beli,
                        'alasan'              => $product['alasan'],
                        'resolution'          => 'barang', // default for outlet retur
                    ]);
                }
            }

            $refundPembelian->update(['total' => $total]);

            DB::commit();

            return redirect(route('refundPembelian.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->withInput()->with('toast_error', 'Gagal: '.$e->getMessage());
        }
    }

    public function show(RefundPembelian $refundPembelian)
    {
        return view('refundPembelians.show', [
            'refundPembelian' => $refundPembelian->load('refundPembelianItems.product'),
        ]);
    }

    public function edit(RefundPembelian $refundPembelian)
    {
        if ($refundPembelian->status === 'complete') {
            return redirect()->route('refundPembelian.show', $refundPembelian)
                ->with('toast_error', 'Data yang sudah complete tidak dapat diedit.');
        }

        return view('refundPembelians.edit', [
            'refundPembelian' => $refundPembelian->load('refundPembelianItems.product'),
            'suppliers'       => Supplier::get(),
            'outlets'         => Outlet::get(),
        ]);
    }

    public function update(Request $request, RefundPembelian $refundPembelian)
    {
        if ($refundPembelian->status === 'complete') {
            return redirect()->route('refundPembelian.show', $refundPembelian)
                ->with('toast_error', 'Data yang sudah complete tidak dapat diedit.');
        }

        $request->validate([
            'code'    => 'required|string|unique:refund_pembelians,code,'.$refundPembelian->id,
            'tanggal' => 'required|date',
        ], [
            'code.required' => 'Kode harus diisi.',
            'code.unique'   => 'Kode sudah digunakan.',
            'tanggal.date'  => 'Format tanggal tidak valid.',
        ]);

        $refundPembelian->update([
            'code'    => $request->code,
            'tanggal' => $request->tanggal,
        ]);

        return redirect()->route('refundPembelian.show', $refundPembelian)
            ->with('toast_success', 'Berhasil Update Data!');
    }

    public function destroy(RefundPembelian $refundPembelian)
    {
        if ($refundPembelian->status === 'complete') {
            return redirect()->route('refundPembelian.index')
                ->with('toast_error', 'Data yang sudah complete tidak dapat dihapus.');
        }

        DB::beginTransaction();
        try {
            if ($refundPembelian->type === 'gudang_ke_supplier') {
                // Reverse stock reduction (only for retur status)
                foreach ($refundPembelian->refundPembelianItems as $item) {
                    $stock = Stock::find($item->stock_id);
                    if ($stock) {
                        $stock->qty += $item->qty;
                        $stock->save();
                    }
                }
            }

            $refundPembelian->delete();
            DB::commit();

            return redirect()->route('refundPembelian.index')->with('toast_success', 'Berhasil Menghapus Data!');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('toast_error', 'Gagal: '.$e->getMessage());
        }
    }

    // -----------------------------------------------------------------------
    // Terima Retur (hanya untuk gudang_ke_supplier)
    // -----------------------------------------------------------------------

    public function terimaForm(RefundPembelian $refundPembelian)
    {
        if ($refundPembelian->type !== 'gudang_ke_supplier' || $refundPembelian->status !== 'retur') {
            return redirect()->route('refundPembelian.show', $refundPembelian)
                ->with('toast_error', 'Data tidak dapat diproses.');
        }

        return view('refundPembelians.terima', [
            'refundPembelian' => $refundPembelian->load('refundPembelianItems.product', 'supplier'),
            'kasList'         => Kas::get(),
        ]);
    }

    public function terima(Request $request, RefundPembelian $refundPembelian)
    {
        if ($refundPembelian->type !== 'gudang_ke_supplier' || $refundPembelian->status !== 'retur') {
            return redirect()->route('refundPembelian.show', $refundPembelian)
                ->with('toast_error', 'Data tidak dapat diproses.');
        }

        $request->validate([
            'items'              => 'required|array',
            'items.*.resolution' => 'required|in:barang,uang',
            'kas_id'             => 'nullable|exists:kas,id',
        ], [
            'items.required'           => 'Daftar item tidak boleh kosong.',
            'items.*.resolution.in'    => 'Resolusi harus berupa barang atau uang.',
            'items.*.resolution.required' => 'Resolusi setiap item wajib dipilih.',
            'kas_id.exists'            => 'Kas yang dipilih tidak terdaftar.',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->items as $itemId => $itemData) {
                $item       = RefundPembelianItem::findOrFail($itemId);
                $resolution = $itemData['resolution'];
                $item->update(['resolution' => $resolution]);

                // if ($resolution === 'barang') {
                // Restore warehouse stock
                $stock = Stock::find($item->stock_id);
                if ($stock) {
                    $stock->qty += $item->qty;
                    $stock->save();
                    $newBalance = $stock->qty;
                } else {
                    $newBalance = $item->qty;
                }

                StockMovement::create([
                    'product_id'     => $item->product_id,
                    'user_id'        => auth()->id(),
                    'type'           => 'penerimaan_retur',
                    'reference_type' => RefundPembelian::class,
                    'reference_id'   => $refundPembelian->id,
                    'qty_in'         => $item->qty,
                    'qty_out'        => 0,
                    'balance'        => $newBalance,
                    'notes'          => "Terima retur barang - {$refundPembelian->code} - SKU: {$item->sku} - Alasan: {$item->alasan}",
                ]);
                // } else {
                //     $uangTotal += $item->qty * $item->harga;
                // }
            }

            // if ($uangTotal > 0 && $request->kas_id) {
            //     $kas           = Kas::findOrFail($request->kas_id);
            //     $kas->nominal += $uangTotal;
            //     $kas->save();

            //     $refundPembelian->update(['kas_id' => $request->kas_id]);
            // }

            $refundPembelian->update(['status' => 'complete']);

            DB::commit();

            return redirect()->route('refundPembelian.show', $refundPembelian)
                ->with('toast_success', 'Penerimaan retur berhasil diselesaikan!');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('toast_error', 'Gagal: '.$e->getMessage());
        }
    }
}
