<?php

namespace App\Http\Controllers;

use App\Exports\SuppliersExport;
use App\Http\Requests\SupplierRequest;
use App\Imports\SuppliersImport;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class SupplierController extends Controller
{
    public function index()
    {
        return view('suppliers.index', [
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('suppliers.create', [
            'nextKode' => Supplier::generateNextKode(),
        ]);
    }

    public function store(SupplierRequest $request)
    {
        $data = $request->validated();

        Supplier::create($data);

        return redirect(route('supplier.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function show(Supplier $supplier)
    {
        dd($supplier);
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.edit', [
            'supplier' => $supplier,
        ]);
    }

    public function update(SupplierRequest $request, Supplier $supplier)
    {
        $data = $request->validated();

        $supplier->update($data);

        return redirect(route('supplier.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return redirect(route('supplier.index'))->with('toast_success', 'Berhasil Menghapus Data!');
    }

    ///-----------------------------------------------------------------------------------------------

    public function export()
    {
        return Excel::download(new SuppliersExport(), 'suppliers.xlsx');
    }

    public function exportTemplate()
    {
        return Excel::download(new SuppliersExport(templateOnly: true), 'template_suppliers.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|mimes:xlsx,xls,csv']);
        Excel::import(new SuppliersImport(), $request->file('file'));

        return redirect()->back()->with('toast_success', 'Berhasil Import Data!');
    }
}
