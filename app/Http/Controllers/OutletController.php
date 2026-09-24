<?php

namespace App\Http\Controllers;

use App\Http\Requests\OutletRequest;
use App\Models\Kas;
use App\Models\Outlet;
use Illuminate\Support\Facades\Storage;

class OutletController extends Controller
{
    public function getKas($outlet_id)
    {
        $kas = Kas::where('outlet_id', $outlet_id)->get();

        return response()->json($kas);
    }

    public function index()
    {
        return view('outlets.index', [
            'outlets' => Outlet::get(),
        ]);
    }

    public function create()
    {
        return view('outlets.create', []);
    }

    public function store(OutletRequest $request)
    {
        $data = $request->validated();

        // Handle file upload
        if ($request->hasFile('logo')) {
            // Get the uploaded file
            $file = $request->file('logo');

            // Generate a unique file name
            $fileName = time().'.'.$file->getClientOriginalExtension();

            // Store the file
            $file->storeAs('public/logos', $fileName);

            // Add the file path to the data array
            $data['logo'] = 'storage/logos/'.$fileName;
        }

        Outlet::create($data);

        return redirect(route('outlet.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function show(Outlet $outlet)
    {
        // dd($outlet);
        return view('outlets.show', [
            'outlet' => $outlet,
        ]);
    }

    public function edit(Outlet $outlet)
    {
        return view('outlets.edit', [
            'outlet' => $outlet,
        ]);
    }

    public function update(OutletRequest $request, Outlet $outlet)
    {
        $data = $request->validated();
        if ($request->hasFile('logo')) {
            // Delete the old image file
            if ($outlet->logo) {
                Storage::delete(str_replace('storage', 'public', $outlet->logo));
            }
            // Store the new image file
            $file = $request->file('logo');
            $fileName = time().'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/logos', $fileName);
            $data['logo'] = 'storage/logos/'.$fileName;
        }
        $outlet->update($data);

        return redirect(route('outlet.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function destroy(Outlet $outlet)
    {
        // Delete the image file
        if ($outlet->logo) {
            Storage::delete(str_replace('storage', 'public', $outlet->logo));
        }

        $outlet->delete();

        return redirect(route('outlet.index'))->with('toast_success', 'Berhasil Menghapus Data!');
    }
}
