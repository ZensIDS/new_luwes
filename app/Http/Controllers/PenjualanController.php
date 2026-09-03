<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\Penjualan;
use App\Services\CashierSaleService;
use App\Support\OutletAccess;
use Illuminate\Http\Request;
use Throwable;

class PenjualanController extends Controller
{
    public function getPenjualan(Request $request, $outlet_id)
    {
        $request->merge(['outlet_id' => $outlet_id]);
        OutletAccess::id($request);
        $penjualans = Penjualan::where('outlet_id', $outlet_id)->get();

        return response()->json($penjualans);
    }

    public function getItems(Request $request, $penjualan_id)
    {
        $penjualan = Penjualan::find($penjualan_id);
        if ($penjualan) {
            $request->merge(['outlet_id' => $penjualan->outlet_id]);
            OutletAccess::id($request);
            $items = $penjualan->items;

            return response()->json($items);
        } else {
            return response()->json([], 404);
        }
    }

    public function marketplace()
    {
        return view('penjualan.marketplace', [
            'penjualan' => Penjualan::has('transaction')->orderBy('created_at', 'desc')->get(),
        ]);
    }

    public function index(Request $request)
    {
        $outletId = OutletAccess::id($request, false);
        $query = Penjualan::with(['items.product', 'outlet', 'kasir', 'vouchers'])
            ->orderBy('created_at', 'desc');
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        return view('penjualan.index', [
            'penjualan' => $query->get(),
        ]);
    }

    public function create()
    {
        if (in_array(auth()->user()->role, ['kasir', 'staff-outlet'], true)) {
            abort_unless(auth()->user()->outlet_id, 422, 'User belum memiliki outlet.');
            return redirect()->route('outlet.show', auth()->user()->outlet_id);
        }

        return view('penjualan.create', [
            'outlets' => Outlet::get(),
        ]);
    }

    public function store(Request $request, CashierSaleService $saleService)
    {
        $request->validate([
            'customer_id' => 'nullable|integer|exists:users,id',
            'outlet_id' => 'required|integer|exists:outlets,id',
            'paid_amount' => 'required|numeric|min:0',
            'payment_method_id' => 'nullable|integer|exists:payment_methods,id',
            'payment_method_name' => 'nullable|string|max:100',
            'salesman_id' => 'nullable|integer|exists:salesmen,id',
            'voucher_codes' => 'nullable|array',
            'voucher_codes.*' => 'string|max:100',
        ]);

        try {
            OutletAccess::id($request);
            $order = $saleService->checkout($request->user(), $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dibuat.',
                'redirect' => route('penjualan.show', $order),
                'order' => $order,
            ], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(Penjualan $penjualan)
    {
        $this->ensureSaleAccess($penjualan);
        return view('penjualan.show', [
            'penjualan' => $penjualan->load(['kasir', 'customer', 'outlet', 'items.product', 'vouchers', 'paymentMethod']),
        ]);
    }

    public function print(Penjualan $penjualan)
    {
        $this->ensureSaleAccess($penjualan);
        return view('penjualan.print', [
            'penjualan' => $penjualan->load(['kasir', 'customer', 'outlet', 'items.product', 'vouchers', 'paymentMethod']),
        ]);
    }

    // public function edit(Penjualan $penjualan)
    // {
    //     return view('penjualan.edit', [
    //         'penjualan' => $penjualan,
    //     ]);
    // }

    // public function update(PenjualanRequest $request, Penjualan $penjualan)
    // {
    //     $data = $request->validated();

    //     $penjualan->update($data);

    //     return redirect(route('penjualan.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    // }

    public function destroy(Penjualan $penjualan)
    {
        $this->ensureSaleAccess($penjualan);
        if ($penjualan->status === 'paid' && $penjualan->items()->exists()) {
            return redirect()->back()->with('toast_error', 'Penjualan paid tidak dapat dihapus karena stok dan voucher harus tetap dapat diaudit. Gunakan alur retur/void.');
        }
        $penjualan->delete();

        return redirect(route('penjualan.index'))->with('toast_success', 'Berhasil Menghapus Data!');
    }

    private function ensureSaleAccess(Penjualan $penjualan): void
    {
        $user = auth()->user();
        if (in_array($user?->role, ['staff-outlet', 'kasir'], true)) {
            abort_unless($user->outlet_id && (int) $user->outlet_id === (int) $penjualan->outlet_id, 403);
        }
    }
}
