<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Penjualan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

class CustomerController extends Controller
{
    public function getCustomer($penjualan_id)
    {
        $penjualan = Penjualan::find($penjualan_id);
        $customer = $penjualan->customer;

        return response()->json($customer);
    }

    public function index(Request $request)
    {
        if ($request->wantsJson()) {
            return response(User::where('role', 'customer')->get());
        }

        return view('customers.index', [
            'users' => User::where('role', 'customer')->get(),
        ]);
    }

    public function create()
    {

        return view('customers.create', []);
    }

    public function store(CustomerRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['no_telp']);

        User::create($data);

        return redirect(route('customer.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function show(User $user)
    {
        dd($user);
    }

    public function edit(User $customer)
    {
        return view('customers.edit', [
            'customer' => $customer,
        ]);
    }

    public function update(Request $request, User $customer)
    {
        $this->validate($request, [
            'name' => 'required',
            'username' => 'nullable',
            'alamat' => 'required',
            'no_telp' => 'required',
            // 'email' => 'required|email|unique:users,email,'.$customer->id,
            'password' => 'same:confirm-password',
        ]);

        $data = $request->all();
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            $data = Arr::except($data, ['password']);
        }

        $customer->update($data);

        return redirect(route('customer.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function destroy(User $customer)
    {

        $customer->delete();

        return redirect(route('customer.index'))->with('toast_success', 'Berhasil Menghapus Data!');
    }
}
