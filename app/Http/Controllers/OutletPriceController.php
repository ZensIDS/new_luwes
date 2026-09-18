<?php

namespace App\Http\Controllers;

use App\Http\Requests\OutletPriceRequest;
use App\Models\OutletPrice;
use App\Models\OwnerStock;
use App\Models\Product;
use App\Support\OutletAccess;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class OutletPriceController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureManagementAccess();
        $outletId = OutletAccess::id($request, false);
        $prices = $outletId
            ? OutletPrice::with(['outlet', 'product'])
                ->where('outlet_id', $outletId)
                ->when($request->filled('search'), fn ($query) => $query->whereHas('product', fn ($productQuery) => $productQuery
                    ->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('code', 'like', '%' . $request->search . '%')))
                ->latest('updated_at')
                ->paginate(25)
                ->withQueryString()
            : new LengthAwarePaginator([], 0, 25);

        return view('outlet-prices.index', [
            'prices' => $prices,
            'outlets' => OutletAccess::outlets(),
            'selectedOutletId' => $outletId,
        ]);
    }

    public function create()
    {
        $this->ensureManagementAccess();
        return view('outlet-prices.form', [
            'price' => new OutletPrice([
                'disc_brand_type' => 'nominal',
                'pajak_type' => 'percentage',
                'pajak_value' => 0,
                'disc_tambahan_type' => 'nominal',
                'disc_tambahan_value' => 0,
                'margin_type' => 'percentage',
                'margin_value' => 0,
                'disc_toko_type' => 'nominal',
                'disc_toko_value' => 0,
                'outlet_adjustment_type' => 'nominal',
                'outlet_adjustment_value' => 0,
                'is_active' => true,
            ]),
            'outlets' => OutletAccess::outlets(),
            'products' => Product::orderBy('name')->get(['id', 'code', 'name']),
            'method' => 'POST',
            'action' => route('outlet-prices.store'),
            'previewHpp' => null,
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
            'previewHpp' => OwnerStock::where('owner_id', $outletPrice->outlet_id)
                ->where('product_id', $outletPrice->product_id)
                ->latest('created_at')
                ->value('hpp') ?? Product::find($outletPrice->product_id)?->harga_beli,
        ]);
    }

    public function previewHpp(Request $request)
    {
        $this->ensureManagementAccess();
        $request->validate([
            'outlet_id' => 'required|integer|exists:outlets,id',
            'product_id' => 'required|integer|exists:products,id',
        ]);

        $product = Product::findOrFail($request->product_id);
        $hpp = OwnerStock::where('owner_id', $request->outlet_id)
            ->where('product_id', $request->product_id)
            ->latest('created_at')
            ->value('hpp');

        return response()->json(['hpp' => (float) ($hpp ?? $product->harga_beli ?? 0)]);
    }

    public function update(OutletPriceRequest $request, OutletPrice $outletPrice)
    {
        $outletPrice->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

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
