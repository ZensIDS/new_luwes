<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalesmanRequest;
use App\Models\Salesman;
use Illuminate\Http\Request;

class SalesmanController extends Controller
{
    public function index(Request $request)
    {
        $salesmans = Salesman::get();
        if ($request->wantsJson()) {
            return response($salesmans);
        }

        return view('salesmans.index', [
            'salesmans' => $salesmans,
        ]);
    }

    public function create()
    {
        return view('salesmans.create', []);
    }

    public function store(SalesmanRequest $request)
    {
        $data = $request->validated();

        Salesman::create($data);

        return redirect(route('salesman.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function show(Salesman $salesman)
    {
        dd($salesman);
    }

    public function edit(Salesman $salesman)
    {
        return view('salesmans.edit', [
            'salesman' => $salesman,
        ]);
    }

    public function update(SalesmanRequest $request, Salesman $salesman)
    {
        $data = $request->validated();

        $salesman->update($data);

        return redirect(route('salesman.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function destroy(Salesman $salesman)
    {
        $salesman->delete();

        return redirect(route('salesman.index'))->with('toast_success', 'Berhasil Menghapus Data!');
    }
}
