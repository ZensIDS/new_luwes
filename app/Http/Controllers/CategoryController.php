<?php

namespace App\Http\Controllers;

use App\Exports\CategoriesExport;
use App\Http\Requests\CategoryRequest;
use App\Imports\CategoriesImport;
use App\Models\Category;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class CategoryController extends Controller
{
    // public function index()
    // {
    //     return view('categories.index', [
    //         'categories' => Category::get()->sortBy('type'),
    //     ]);
    // }

    public function indexProduct()
    {
        $categories = Category::get();

        return view('categories.index', ['categories' => $categories, 'type' => 'product']);
    }

    // public function indexPengeluaran()
    // {
    //     $categories = Category::where('type', 'pengeluaran')->get();

    //     return view('categories.index', ['categories' => $categories, 'type' => 'pengeluaran']);
    // }

    // public function create()
    // {
    //     return view('categories.create', []);
    // }

    public function createProduct()
    {
        return view('categories.create', ['type' => 'product', 'outlets' => Outlet::get()]);
    }

    public function createPengeluaran()
    {
        return view('categories.create', ['type' => 'pengeluaran', 'outlets' => Outlet::get()]);
    }

    public function store(CategoryRequest $request)
    {
        $data = $request->validated();
        Category::create($data);

        return redirect(route('category.product.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
        // if ($data['type'] == 'product') {
        //     return redirect(route('category.product.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
        // } else {
        //     return redirect(route('category.pengeluaran.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
        // }
    }

    public function show(Category $category)
    {
        dd($category);
    }

    // public function edit(Category $category)
    // {
    //     return view('categories.edit', [
    //         'category' => $category,
    //     ]);
    // }

    public function editProduct(Category $category)
    {
        return view('categories.edit', ['category' => $category, 'type' => 'product', 'outlets' => Outlet::get()]);
    }

    public function editPengeluaran(Category $category)
    {
        return view('categories.edit', ['category' => $category, 'type' => 'pengeluaran', 'outlets' => Outlet::get()]);
    }

    public function update(CategoryRequest $request, Category $category)
    {
        $data = $request->validated();

        $category->update($data);

        return redirect(route('category.product.index'))->with('toast_success', 'Berhasil Mengedit Data!');

        // if ($data['type'] == 'product') {
        //     return redirect(route('category.product.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
        // } else {
        //     return redirect(route('category.pengeluaran.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
        // }
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return redirect()->back()->with('toast_success', 'Berhasil Menghapus Data!');
    }

    ///-----------------------------------------------------------------------------------------------

    public function exportProduct()
    {
        return Excel::download(new CategoriesExport(), 'categories_product.xlsx');
    }

    public function exportPengeluaran()
    {
        return Excel::download(new CategoriesExport(), 'categories_pengeluaran.xlsx');
    }

    public function exportTemplate()
    {
        return Excel::download(new CategoriesExport(templateOnly: true), 'template_categories.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|mimes:xlsx,xls,csv']);
        Excel::import(new CategoriesImport(), $request->file('file'));

        return redirect()->back()->with('toast_success', 'Berhasil Import Data!');
    }
}
