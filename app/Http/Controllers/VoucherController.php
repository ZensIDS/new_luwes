<?php

namespace App\Http\Controllers;

use App\Http\Requests\VoucherRequest;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;

class VoucherController extends Controller
{
    public function index()
    {
        $vouchers = Voucher::query()
            ->when(auth()->user()->role === 'kasir', function ($query) {
                $query->where('kasir_id', auth()->id())->orWhereNull('kasir_id');
            })
            ->where('limit', '>', 0)
            ->get();

        if (request()->wantsJson()) {
            return response()->json($vouchers);
        }

        return view('vouchers.index', compact('vouchers'));
    }

    public function create()
    {
        return view('vouchers.create', [
            'kasirs' => User::where('role', 'kasir')->get(),
            'products' => Product::get(),
        ]);
    }

    public function store(VoucherRequest $request)
    {
        $data = $request->validated();
        [$start_at, $end_at] = explode(' - ', $request->daterange);
        $data['start_at'] = $start_at;
        $data['end_at'] = $end_at;
        $data['type'] = 'nominal';
        $data['jenis'] = 'keseluruhan';
        $data['limit'] = 1;
        unset($data['daterange']);

        Voucher::create($data);

        return redirect(route('voucher.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function show(Voucher $voucher)
    {
        return view('vouchers.show', [
            'voucher' => $voucher,
        ]);
    }

    public function edit(Voucher $voucher)
    {
        return view('vouchers.edit', [
            'voucher' => $voucher,
            'products' => Product::get(),
            'kasirs' => User::where('role', 'kasir')->get(),
            'defaultDateRange' => $voucher->start_at.' - '.$voucher->end_at,
        ]);
    }

    public function update(VoucherRequest $request, Voucher $voucher)
    {
        $data = $request->validated();
        [$start_at, $end_at] = explode(' - ', $request->daterange);
        $data['start_at'] = $start_at;
        $data['end_at'] = $end_at;
        $data['type'] = 'nominal';
        $data['jenis'] = 'keseluruhan';
        $data['limit'] = 1;
        unset($data['daterange']);

        $voucher->update($data);

        return redirect(route('voucher.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function destroy(Voucher $voucher)
    {
        $voucher->delete();

        return redirect(route('voucher.index'))->with('toast_success', 'Berhasil Menghapus Data!');
    }
}
