<?php

namespace App\Http\Controllers;

use App\Http\Requests\OutletPriceRequest;
use App\Models\OutletPrice;
use App\Models\OwnerStock;
use App\Models\Product;
use App\Services\PriceCalculator;
use App\Support\OutletAccess;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class OutletPriceController extends Controller
{
    public function index(Request $request, PriceCalculator $calculator)
    {
        $this->ensureManagementAccess();
        $outletId = OutletAccess::id($request, false);
        $prices = $outletId
            ? OutletPrice::with([
                'outlet',
                'product.ownerStocks' => fn ($query) => $query
                    ->where('owner_id', $outletId)
                    ->where('qty', '>', 0)
                    ->where(function ($expiryQuery) {
                        $expiryQuery->whereNull('expired_at')->orWhereDate('expired_at', '>=', today());
                    })
                    ->orderBy('created_at')
                    ->orderBy('id'),
            ])
                ->where('outlet_id', $outletId)
                ->when($request->filled('search'), fn ($query) => $query->whereHas('product', fn ($productQuery) => $productQuery
                    ->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('code', 'like', '%' . $request->search . '%')))
                ->latest('updated_at')
                ->paginate(25)
                ->withQueryString()
            : new LengthAwarePaginator([], 0, 25);

        if ($outletId) {
            $prices->getCollection()->each(function (OutletPrice $price) use ($calculator) {
                $product = $price->product;
                $stock = $product?->ownerStocks?->first();
                $calculated = $calculator->calculateItem(
                    (float) ($stock?->hpp ?? $product?->harga_beli ?? 0),
                    $price,
                    $product
                );

                $price->setAttribute('print_price_hpp_after_tax', $calculated['hpp_setelah_pajak']);
                $price->setAttribute('print_price_margin', $calculated['margin_amount']);
                $price->setAttribute('print_price_strike', $calculator->money(
                    $calculated['hpp_setelah_pajak'] + $calculated['margin_amount']
                ));
                $price->setAttribute('print_price_net', $calculated['price']);
            });
        }

        return view('outlet-prices.index', [
            'prices' => $prices,
            'outlets' => OutletAccess::outlets(),
            'selectedOutletId' => $outletId,
        ]);
    }

    public function create(Request $request)
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
            'selectedProduct' => $this->selectedProduct($request->old('product_id')),
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

    public function edit(Request $request, OutletPrice $outletPrice)
    {
        $this->ensureManagementAccess();
        return view('outlet-prices.form', [
            'price' => $outletPrice,
            'outlets' => OutletAccess::outlets(),
            'selectedProduct' => $this->selectedProduct($request->old('product_id', $outletPrice->product_id)),
            'method' => 'PUT',
            'action' => route('outlet-prices.update', $outletPrice),
            'previewHpp' => OwnerStock::where('owner_id', $outletPrice->outlet_id)
                ->where('product_id', $outletPrice->product_id)
                ->latest('created_at')
                ->value('hpp') ?? Product::find($outletPrice->product_id)?->harga_beli,
        ]);
    }

    /**
     * Select2 AJAX untuk pilihan produk: 20 produk per halaman, tanpa COUNT(*)
     * (ambil 1 baris ekstra untuk tahu masih ada halaman berikutnya).
     */
    public function searchProducts(Request $request)
    {
        $this->ensureManagementAccess();

        $search = trim((string) $request->query('q', ''));
        $page = max((int) $request->query('page', 1), 1);
        $perPage = 20;

        $query = Product::query();

        if ($search !== '') {
            $like = addcslashes($search, '\\%_');
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', "%{$like}%")
                    ->orWhere('code', 'like', "{$like}%");
            });
        }

        $rows = $query
            ->orderBy('name')
            ->skip(($page - 1) * $perPage)
            ->take($perPage + 1)
            ->get(['id', 'code', 'name']);

        return response()->json([
            'results' => $rows->take($perPage)->map(fn ($product) => [
                'id' => $product->id,
                'text' => "{$product->code} — {$product->name}",
            ])->values(),
            'pagination' => ['more' => $rows->count() > $perPage],
        ]);
    }

    /**
     * Hanya produk yang sedang terpilih (edit / old input), bukan seluruh katalog.
     */
    private function selectedProduct($productId): ?Product
    {
        return $productId ? Product::select(['id', 'code', 'name'])->find($productId) : null;
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