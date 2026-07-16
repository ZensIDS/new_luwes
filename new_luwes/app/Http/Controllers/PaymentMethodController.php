<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentMethodRequest;
use App\Models\PaymentMethod;

class PaymentMethodController extends Controller
{
    public function index()
    {
        return view('payment.index', ['payments' => PaymentMethod::get()]);
    }

    public function create()
    {
        return view('payment.create', []);
    }

    public function store(PaymentMethodRequest $request)
    {
        $data = $request->validated();
        PaymentMethod::create($data);

        return redirect(route('payment.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function show($paymentMethod)
    {
        dd($paymentMethod);
    }

    public function edit($paymentMethod)
    {
        $paymentMethod = PaymentMethod::find($paymentMethod);

        return view('payment.edit', ['paymentMethod' => $paymentMethod]);
    }

    public function update(PaymentMethodRequest $request, $paymentMethod)
    {
        $data = $request->validated();
        PaymentMethod::find($paymentMethod)->update($data);

        return redirect(route('payment.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function destroy($paymentMethod)
    {
        $paymentMethod = PaymentMethod::find($paymentMethod);
        $paymentMethod->delete();

        return redirect(route('payment.index'))->with('toast_success', 'Berhasil Menghapus Data!');
    }
}
