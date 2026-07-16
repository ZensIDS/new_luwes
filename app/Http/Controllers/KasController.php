<?php

namespace App\Http\Controllers;

use App\Http\Requests\KasRequest;
use App\Models\Kas;
use App\Models\Outlet;
use Illuminate\Http\Request;

class KasController extends Controller
{
    public function index(Request $request)
    {
        $outletId = $request->input('outlet_id');
        if ($request->wantsJson()) {
            return response(Kas::where('outlet_id', $outletId)->orderBy('outlet_id', 'asc')->get());
        }

        return view('kas.index', ['kas' => Kas::get()]);
    }

    public function create()
    {
        return view('kas.create', [
            'outlets' => Outlet::get(),
        ]);
    }

    public function store(KasRequest $request)
    {
        $data = $request->validated();
        Kas::create($data);

        return redirect(route('kas.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function show(Kas $kas)
    {
        dd($kas);
    }

    public function edit($kas)
    {
        return view('kas.edit', [
            'kas' => Kas::find($kas),
            'outlets' => Outlet::get(),
        ]);
    }

    public function update(KasRequest $request, $kas)
    {
        $data = $request->validated();
        Kas::find($kas)->update($data);

        return redirect(route('kas.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function destroy($kas)
    {
        Kas::find($kas)->delete();

        return redirect(route('kas.index'))->with('toast_success', 'Berhasil Menghapus Data!');
    }
}
