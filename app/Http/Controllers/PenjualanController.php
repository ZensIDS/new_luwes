<?php

namespace App\Http\Controllers;

use App\Http\Requests\PenjualanRequest;
use App\Models\Kas;
use App\Models\Outlet;
use App\Models\Penjualan;
use App\Models\Stock;
use App\Models\Voucher;
use App\Services\CashierSaleService;
use App\Support\OutletAccess;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDF;
use RuntimeException;

class PenjualanController extends Controller
{
    public function getPenjualan($outlet_id)
    {
        $penjualans = Penjualan::where('outlet_id', $outlet_id)->get();

        return response()->json($penjualans);
    }

    public function getItems($penjualan_id)
    {
        $penjualan = Penjualan::find($penjualan_id);
        if ($penjualan) {
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

    public function index()
    {
        return view('penjualan.index', [
            'penjualan' => Penjualan::doesntHave('transaction')
                ->with([
                    'outlet',
                    'kasir',
                    'cashierShift',
                    // withTrashed: produk yang sudah di-soft-delete tetap tampil di riwayat penjualan
                    'items.product' => fn ($q) => $q->withTrashed(),
                ])
                ->orderBy('created_at', 'desc')
                ->get(),
        ]);
    }

    public function create()
    {
        if (auth()->user()->role == 'kasir' | auth()->user()->role == 'admin'){
            return redirect()->route('outlet.show', auth()->user()->outlet_id);
        }

        return view('penjualan.create', [
            'outlets' => Outlet::get(),
        ]);
    }

    public function store(Request $request, CashierSaleService $sales)
    {
        // Total, subtotal, diskon, promo, dan kembalian dihitung server dari keranjang
        // (CashierSaleService), jadi tidak diterima dari client. customer_id boleh kosong
        // (pelanggan Umum).
        $data = $request->validate([
            'outlet_id' => 'nullable|integer|exists:outlets,id',
            'customer_id' => 'nullable|exists:users,id',
            'salesman_id' => 'nullable',
            'paid_amount' => 'required|numeric|min:0',
            'payment_method_id' => 'nullable|integer|exists:payment_methods,id',
            'payment_method_name' => 'nullable|string|max:255',
            'payment_reference' => 'nullable|string|max:255',
            'voucher_codes' => 'nullable|array',
            'voucher_codes.*' => 'string|max:100',
            'promotion_codes' => 'nullable|array',
            'promotion_codes.*' => 'string|max:100',
        ]);

        // Kasir / staff outlet hanya boleh bertransaksi di outletnya sendiri.
        $data['outlet_id'] = OutletAccess::id($request);

        try {
            $order = $sales->checkout($request->user(), $data);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil dibuat.',
            'redirect' => route('outlet.show', $order->outlet_id),
            'print' => route('penjualan.print', [$order]),
            'order' => $order,
        ], 201);
    }

    public function show(Penjualan $penjualan)
    {
        // dd($penjualan->load(['kasir', 'customer', 'items.product'])->toArray());
        // $pdf = PDF::loadView('penjualan.penjualan_pdf', ['penjualan' => $penjualan]);

        // return $pdf->download('penjualan_'.$penjualan->id.'.pdf');
        return view('penjualan.show', [
            'penjualan' => $penjualan,
        ]);
    }

    public function print(Penjualan $penjualan)
    {
        return view('penjualan.print', [
            'penjualan' => $penjualan,
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
        $penjualan->delete();

        return redirect(route('penjualan.index'))->with('toast_success', 'Berhasil Menghapus Data!');
    }
}