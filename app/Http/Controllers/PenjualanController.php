<?php

namespace App\Http\Controllers;

use App\Http\Requests\PenjualanRequest;
use App\Models\Kas;
use App\Models\Outlet;
use App\Models\Penjualan;
use App\Models\Stock;
use App\Models\Voucher;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDF;

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
            'penjualan' => Penjualan::doesntHave('transaction')->orderBy('created_at', 'desc')->get(),
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

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required',
            // 'kas_id' => 'required',
            'kasir_id' => 'nullable',
            'total' => 'required',
        ]);

        DB::beginTransaction();

        try {
            $lastOrder = Penjualan::where('outlet_id', $request->outlet_id)
                ->orderBy('created_at', 'desc')
                ->first();
            $nextInvoiceNumber = $lastOrder ? ((int) substr($lastOrder->code, 3) + 1) : 1;
            $nextInvoiceNumber = str_pad($nextInvoiceNumber, 3, '0', STR_PAD_LEFT);
            $nextInvoiceCode = 'INV'.$nextInvoiceNumber;
            $order = Penjualan::create([
                'code' => $nextInvoiceCode,
                'customer_id' => $request->customer_id,
                'outlet_id' => $request->outlet_id,
                'salesman_id' => $request->salesman_id,
                'kasir_id' => $request->kasir_id,
                'voucher_id' => $request->voucher_id,
                'discount' => $request->discount,
                'total' => $request->total,
            ]);

            if (isset($request->voucher_id)) {
                Voucher::find($request->voucher_id)->update(['limit' => 0]);
            }
            // $kas = Kas::find($request->kas_id);
            // $kas->nominal += $request->total;
            // $kas->save();

            $cart = $request->user()->cart()->get();
            foreach ($cart as $item) {
                $order->items()->create([
                    'subtotal' => $item->harga_jual * $item->pivot->qty,
                    'price' => $item->harga_jual,
                    'qty' => $item->pivot->qty,
                    'product_id' => $item->id,
                    'serial_number' => $item->pivot->serial_number,
                    'stock_id' => $item->pivot->stock_id,
                ]);

                if ($item->is_serialized) {
                    // For serialized items, update specific stock
                    $stock = Stock::find($item->pivot->stock_id);
                    if (! $stock || $stock->qty < $item->pivot->qty) {
                        throw new Exception('Stock not found or insufficient quantity');
                    }
                    $stock->qty -= $item->pivot->qty;
                    $stock->save();
                } else {
                    // Existing FIFO logic for non-serialized items
                    $now = Carbon::now();
                    $stocks = Stock::where('product_id', $item->id)
                        ->where('qty', '>', 0)
                        ->get();

                    if ($stocks->isEmpty()) {
                        throw new Exception('Stock not found or expired');
                    }

                    $remainingQty = $item->pivot->qty;
                    foreach ($stocks as $stock) {
                        if ($remainingQty <= 0) {
                            break;
                        }

                        if ($stock->qty >= $remainingQty) {
                            $stock->qty -= $remainingQty;
                            $remainingQty = 0;
                        } else {
                            $remainingQty -= $stock->qty;
                            $stock->qty = 0;
                        }
                        $stock->save();
                    }

                    if ($remainingQty > 0) {
                        throw new Exception('Insufficient stock quantity');
                    }
                }
            }

            $request->user()->cart()->detach();

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            return $e->getMessage();
        }
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
