<?php

namespace App\Http\Controllers;

use App\Http\Requests\OutletPriceRequest;
use App\Models\OutletPrice;
use App\Models\Product;
use App\Support\OutletAccess;
use Illuminate\Http\Request;

class OutletPriceController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureManagementAccess();
        $prices = OutletPrice::with(['outlet', 'product'])
            ->when($request->filled('outlet_id'), fn ($query) => $query->where('outlet_id', $request->outlet_id))
            ->when($request->filled('search'), fn ($query) => $query->whereHas('product', fn ($productQuery) => $productQuery
                ->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('code', 'like', '%' . $request->search . '%')))
            ->latest('updated_at')
            ->paginate(25)
            ->withQueryString();

        return view('outlet-prices.index', [
            'prices' => $prices,
            'outlets' => OutletAccess::outlets(),
        ]);
    }

    public function create()
    {
        $this->ensureManagementAccess();
        return view('outlet-prices.form', [
            'price' => new OutletPrice([
                'disc_brand_type' => 'nominal',
                'margin_type' => 'percentage',
                'disc_toko_type' => 'nominal',
                'is_active' => true,
            ]),
            'outlets' => OutletAccess::outlets(),
            'products' => Product::orderBy('name')->get(['id', 'code', 'name']),
            'method' => 'POST',
            'action' => route('outlet-prices.store'),
        ]);
    }

    public function store(OutletPriceRequest $request)
    {
        $price = OutletPrice::withTrashed()->firstOrNew([
            'outlet_id' => $request->outlet_id,
            'product_id' => $request->product_id,
        ]);
        $price->fill([
            ...$request->validated(),
            'created_by' => auth()->id(),
            'is_active' => $request->boolean('is_active', true),
        ]);
        if ($price->trashed()) {
            $price->restore();
        }
        $price->save();

        return redirect()->route('outlet-prices.index')->with('toast_success', 'Master harga outlet berhasil disimpan.');
    }

    public function edit(OutletPrice $outletPrice)
    {
        $this->ensureManagementAccess();
        return view('outlet-prices.form', [
            'price' => $outletPrice,
            'outlets' => OutletAccess::outlets(),
            'products' => Product::orderBy('name')->get(['id', 'code', 'name']),
            'method' => 'PUT',
            'action' => route('outlet-prices.update', $outletPrice),
        ]);
    }

    public function update(OutletPriceRequest $request, OutletPrice $outletPrice)
    {
        $outletPrice->update([...$request->validated(), 'is_active' => $request->boolean('is_active')]);

        return redirect()->route('outlet-prices.index')->with('toast_success', 'Master harga outlet berhasil diperbarui.');
    }

    public function destroy(OutletPrice $outletPrice)
    {
        $this->ensureManagementAccess();
        $outletPrice->delete();

        return redirect()->route('outlet-prices.index')->with('toast_success', 'Master harga outlet berhasil dihapus.');
    }

    private function ensureManagementAccess(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['superadmin', 'admin-gudang', 'owner'], true), 403);
    }
}
