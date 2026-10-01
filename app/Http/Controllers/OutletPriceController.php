<?php

namespace App\Http\Controllers;

use App\Http\Requests\OutletPriceRequest;
use App\Models\OutletPrice;
use App\Models\OwnerStock;
use App\Models\Product;
use App\Services\LatestHpp;
use App\Services\PriceCalculator;
use App\Support\OutletAccess;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class OutletPriceController extends Controller
{
    public function index(Request $request, PriceCalculator $calculator)
    {
        $this->ensureManagementAccess();
        $outletId = OutletAccess::id($request, false);
        $prices = $outletId
            ? OutletPrice::with([
                'outlet',
                'product',
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
            $latestHpp = app(LatestHpp::class)->forProducts($outletId, $prices->getCollection()->pluck('product_id'));

            $prices->getCollection()->each(function (OutletPrice $price) use ($calculator, $latestHpp) {
                $product = $price->product;
                $calculated = $calculator->calculateItem(
                    (float) ($latestHpp[$price->product_id] ?? 0),
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
        DB::transaction(function () use ($request) {
            $price = OutletPrice::withTrashed()->firstOrNew([
                'outlet_id' => $request->outlet_id,
                'product_id' => $request->product_id,
            ]);
            $price->fill([
                ...Arr::except($request->validated(), ['hpp', 'hpp_changed']),
                'created_by' => auth()->id(),
                'is_active' => $request->boolean('is_active', true),
            ]);
            if ($price->trashed()) {
                $price->restore();
            }
            $price->save();

            $this->syncLatestHpp($request, (int) $price->outlet_id, (int) $price->product_id);
        });

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
            'previewHpp' => app(LatestHpp::class)->forProduct((int) $outletPrice->outlet_id, (int) $outletPrice->product_id),
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

        return response()->json([
            'hpp' => app(LatestHpp::class)->forProduct((int) $request->outlet_id, (int) $request->product_id),
        ]);
    }

    /**
     * Kalau HPP diubah dari form master harga, simpan sebagai HPP terbaru.
     * Hanya jalan bila form menandai angkanya benar-benar diubah (hpp_changed),
     * supaya angka bawaan form tidak menimpa data tanpa disengaja.
     */
    private function syncLatestHpp(Request $request, int $outletId, int $productId): void
    {
        if (! $request->boolean('hpp_changed') || $request->input('hpp') === null) {
            return;
        }

        $hpp = round((float) $request->input('hpp'), 2);
        $stock = app(LatestHpp::class)->stockFor($outletId, $productId);

        if ($stock) {
            $stock->update(['hpp' => $hpp]);

            return;
        }

        Product::whereKey($productId)->update(['harga_beli' => $hpp]);
    }

    public function update(OutletPriceRequest $request, OutletPrice $outletPrice)
    {
        DB::transaction(function () use ($request, $outletPrice) {
            $outletPrice->update([
                ...Arr::except($request->validated(), ['hpp', 'hpp_changed']),
                'is_active' => $request->boolean('is_active'),
            ]);

            $this->syncLatestHpp($request, (int) $outletPrice->outlet_id, (int) $outletPrice->product_id);
        });

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