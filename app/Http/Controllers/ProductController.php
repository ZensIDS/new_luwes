<?php

namespace App\Http\Controllers;

use App\Exports\ProductsExport;
use App\Exports\ProductsMinStockExport;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Supplier;
use App\Support\OutletAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;

class ProductController extends Controller
{
    /**
     * Return only products available in the requested outlet for the cashier.
     * The outlet is part of the URL so the client cannot accidentally fall
     * back to the global product listing.
     */
    public function outletProducts(Request $request, Outlet $outlet)
    {
        $request->merge([
            'outlet_id' => $outlet->id,
            'status_produk' => 'all',
            'compact' => true,
        ]);
        $request->headers->set('Accept', 'application/json');

        return $this->index($request);
    }

    public function index(Request $request)
    {
        // Kasir/staff-outlet selalu discope ke outlet miliknya sendiri, terlepas dari
        // outlet_id yang dikirim dari klien.
        if (in_array($request->user()?->role, ['staff-outlet', 'kasir'], true)) {
            $request->merge(['outlet_id' => OutletAccess::id($request)]);
        }
        $outletId = $request->input('outlet_id');
        $statusFilter = $request->input('status_produk', 'sudah');
        $products = Product::query();

        // Stok outlet yang masih bisa dijual (qty > 0 dan belum kedaluwarsa).
        // expired_at dibandingkan langsung (bukan whereDate) supaya index bisa dipakai.
        $outletStockScope = function ($query) use ($outletId) {
            $query->where('owner_id', $outletId)
                ->where('qty', '>', 0)
                ->where(function ($expiryQuery) {
                    $expiryQuery->whereNull('expired_at')->orWhere('expired_at', '>=', today());
                });
        };

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            // Jalur scan barcode/serial (kasir): pencocokan eksak dulu. Bila ketemu, tidak perlu
            // menjalankan LIKE '%...%' di 5 kolom + subquery serial yang berat.
            $exactIds = $outletId ? $this->exactScanMatches($search, $outletId, $outletStockScope) : collect();

            if ($exactIds->isNotEmpty()) {
                $products = $products->whereIn('products.id', $exactIds);
            } else {
                $products = $products->where(function ($query) use ($search, $outletId, $outletStockScope) {
                    $query->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('code', 'LIKE', "%{$search}%")
                        ->orWhere('brand', 'LIKE', "%{$search}%")
                        ->orWhere('model', 'LIKE', "%{$search}%");

                    if (! $outletId) {
                        // Pencarian harga hanya dipertahankan di daftar produk gudang (admin);
                        // di kasir kolom angka ini tidak berguna dan memperberat query.
                        $query->orWhere('harga_jual', 'LIKE', "%{$search}%");
                    }

                    if ($outletId) {
                        // Serial number hanya dicari bila input terlihat seperti serial (>= 4 karakter, tanpa spasi),
                        // karena subquery bersarang ke owner_stocks -> stocks ini bagian terberat pencarian.
                        if ($this->looksLikeSerial($search, 4)) {
                            $query->orWhereHas('ownerStocks', function ($stockQuery) use ($outletStockScope, $search) {
                                $outletStockScope($stockQuery);
                                $stockQuery->whereHas('stock', fn ($stock) => $stock->where('serial_number', 'LIKE', "%{$search}%"));
                            });
                        }
                    } else {
                        $query->orWhereHas('stocks', function ($stockQuery) use ($search) {
                            $stockQuery->where('serial_number', 'LIKE', "%{$search}%")
                                ->orWhere('status', 'LIKE', "%{$search}%");
                        });
                    }
                });
            }
        }

        if ($outletId) {
            // Kasir hanya boleh menjual produk yang benar-benar punya stok di outlet ini.
            $products = $products->whereHas('ownerStocks', $outletStockScope);
        }

        if ($request->filled('category_id')) {
            $products = $products->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('lokasi')) {
            $products = $products->where('lokasi', $request->lokasi);
        }

        if ($statusFilter !== 'all') {
            $products = $products->where('status_produk', $statusFilter);
        }

        if (request()->wantsJson()) {
            $compact = $request->boolean('compact');
            $relations = [
                'outletPrices' => function ($query) use ($outletId, $compact) {
                    if ($outletId) {
                        $query->where('outlet_id', $outletId);
                    }
                    if (! $compact) {
                        $query->with('outlet');
                    }
                    $query->currentlyActive();
                },
            ];

            if (! $compact) {
                $relations['category'] = 'category:id,name';
            }

            if ($outletId) {
                // Cashier searches only need stock owned by this outlet. Do not
                // hydrate warehouse stocks for every search result.
                $relations['ownerStocks'] = function ($query) use ($outletStockScope, $compact) {
                    $outletStockScope($query);

                    if ($compact) {
                        $query->select([
                            'owner_stocks.id',
                            'owner_stocks.owner_id',
                            'owner_stocks.product_id',
                            'owner_stocks.qty',
                            'owner_stocks.hpp',
                            'owner_stocks.created_at',
                        ]);
                    } else {
                        $query->with('stock');
                    }
                };
            } else {
                $relations['stocks'] = function ($query) {
                    $query->where('qty', '>', 0)
                        ->orderBy('status')
                        ->orderBy('serial_number');
                };
            }

            if ($compact) {
                $products->select([
                    'products.id',
                    'products.name',
                    'products.code',
                    'products.harga_beli',
                    'products.harga_jual',
                    'products.pic',
                    'products.is_serialized',
                    'products.created_at',
                ]);
            }

            if (! $outletId) {
                // Konteks gudang: total stok dihitung di query (1 subselect), bukan accessor total_stock per baris.
                $products->withSum('stocks as stock_qty', 'qty');
            }

            // Halaman selalu 10 baris (klien kasir tidak lagi mengirim per_page).
            $products = $products
                ->with($relations)
                ->latest()
                ->paginate(10);

            return ProductResource::collection($products);
        }

        $products = $products
            ->with('category:id,name')
            // stock_qty (SUM qty), reserved_stock_qty (SUM qty_reserved), owner_stock_qty (SUM owner_stocks.qty)
            // -> sumber angka yang sama dengan menu Stok, Dashboard, dan Laporan
            ->withStockTotals()
            ->withEffectiveMin() // view membaca effective_min_stock per baris -> hitung di query
            ->withSum([
                'stockPembelians as approved_stock_pembelians_qty' => function ($query) {
                    $query->whereHas('pembelian', fn($pembelian) => $pembelian->where('owner_approval_status', 'approved'));
                },
            ], 'qty')
            ->orderBy('code')
            ->paginate(10)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'locations' => Product::query()
                ->whereNotNull('lokasi')
                ->where('lokasi', '!=', '')
                ->distinct()
                ->orderBy('lokasi')
                ->pluck('lokasi'),
            'search' => $request->search,
            'selectedCategoryId' => $request->input('category_id'),
            'selectedLokasi' => $request->input('lokasi'),
            'statusProdukOptions' => Product::STATUS_PRODUK,
            'selectedStatusProduk' => $statusFilter,
        ]);
    }

    public function create()
    {
        return view('products.create', [
            'outlets' => Outlet::get(),
            'suppliers' => Supplier::get(),
            'categories' => Category::get(),
            'statusProdukOptions' => Product::STATUS_PRODUK,
        ]);
    }

    public function store(ProductRequest $request)
    {
        $data = $request->validated();
        if (($data['status_produk'] ?? 'sudah') !== 'tambahan_diskon') {
            $data['status_produk_note'] = null;
        }

        // Handle file upload
        if ($request->hasFile('pic')) {
            // Get the uploaded file
            $file = $request->file('pic');

            // Generate a unique file name
            $fileName = time() . '.' . $file->getClientOriginalExtension();

            // Store the file
            $file->storeAs('public/pics', $fileName);

            // Add the file path to the data array
            $data['pic'] = 'storage/pics/' . $fileName;
        }

        $product = Product::create($data);

        // Sync suppliers (multiple select)
        if ($request->has('supplier_ids')) {
            $product->suppliers()->sync($request->supplier_ids);
        }

        return redirect(route('product.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function show(Product $product)
    {
        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'brand' => $product->brand,
            'model' => $product->model,
            'harga_beli' => $product->harga_beli,
            'harga_jual' => $product->harga_jual,
            'is_serialized' => $product->is_serialized,
            'total_stock' => $product->total_stock,
        ]);
    }

    public function edit(Product $product)
    {
        return view('products.edit', [
            'product' => $product->load('suppliers'),
            'outlets' => Outlet::get(),
            'suppliers' => Supplier::get(),
            'categories' => Category::get(),
            'statusProdukOptions' => Product::STATUS_PRODUK,
            // optional: selected supplier IDs for form
            'selectedSuppliers' => $product->suppliers->pluck('id')->toArray(),
        ]);
    }

    public function update(ProductRequest $request, Product $product)
    {
        $data = $request->validated();
        if (($data['status_produk'] ?? 'sudah') !== 'tambahan_diskon') {
            $data['status_produk_note'] = null;
        }
        if ($request->hasFile('pic')) {
            // Delete the old image file
            if ($product->pic) {
                Storage::delete(str_replace('storage', 'public', $product->pic));
            }
            // Store the new image file
            $file = $request->file('pic');
            $fileName = time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/pics', $fileName);
            $data['pic'] = 'storage/pics/' . $fileName;
        }
        $product->update($data);

        // Sync suppliers (multiple select)
        if ($request->has('supplier_ids')) {
            $product->suppliers()->sync($request->supplier_ids);
        } else {
            // If no supplier selected, detach all
            $product->suppliers()->detach();
        }

        return redirect(route('product.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function destroy(Product $product)
    {
        $totalStock = Stock::where('product_id', $product->id)->sum('qty');

        if ($totalStock > 0) {
            return redirect()->back()->with(
                'toast_error',
                "Tidak dapat menghapus produk \"{$product->name}\" karena masih memiliki stok sebanyak {$totalStock}. Kosongkan stok terlebih dahulu sebelum menghapus produk."
            );
        }

        // Delete the image file
        if ($product->pic) {
            Storage::delete(str_replace('storage', 'public', $product->pic));
        }

        $product->delete();

        return redirect(route('product.index'))->with('toast_success', 'Berhasil Menghapus Data!');
    }

    public function priceHistory(Product $product)
    {
        $activities = Activity::forSubject($product)
            ->orderBy('created_at', 'asc')
            ->get()
            ->filter(function ($activity) {
                return isset($activity->properties['attributes']['harga_beli']);
            })
            ->map(function ($activity, $index) use (&$prev) {
                $new = $activity->properties['attributes']['harga_beli'];
                $old = $activity->event === 'created'
                    ? null
                    : ($activity->properties['old']['harga_beli'] ?? null);

                return [
                    'date'  => $activity->created_at->format('d M Y H:i'),
                    'user'  => $activity->causer?->name ?? 'System',
                    'old'   => (int) $old,
                    'new'   => (int) $new,
                    'event' => $activity->event,
                ];
            })->values();

        return response()->json(['success' => true, 'data' => $activities]);
    }

    ///-----------------------------------------------------------------------------------------------

    public function export()
    {
        return Excel::download(new ProductsExport(), 'products.xlsx');
    }

    public function exportTemplate()
    {
        return Excel::download(new ProductsExport(templateOnly: true), 'template_products.xlsx');
    }

    public function exportMinStock()
    {
        return Excel::download(new ProductsMinStockExport(), 'products_min_stock.xlsx');
    }

    public function exportMinStockTemplate()
    {
        return Excel::download(new ProductsMinStockExport(templateOnly: true), 'template_min_stock.xlsx');
    }

    /**
     * Input terlihat seperti barcode/serial: cukup panjang dan tanpa spasi.
     */
    private function looksLikeSerial(string $search, int $minLength): bool
    {
        return mb_strlen($search) >= $minLength && ! preg_match('/\s/', $search);
    }

    /**
     * Pencocokan eksak untuk scan barcode (code) atau serial number di stok outlet.
     * Hanya dicoba bila input terlihat seperti barcode (>= 6 karakter, tanpa spasi).
     * Hasil kosong = lanjut ke pencarian LIKE biasa.
     */
    private function exactScanMatches(string $search, $outletId, \Closure $outletStockScope)
    {
        if (! $this->looksLikeSerial($search, 6)) {
            return collect();
        }

        return Product::query()
            ->where(function ($query) use ($search, $outletStockScope) {
                $query->where('code', $search)
                    ->orWhereHas('ownerStocks', function ($stockQuery) use ($search, $outletStockScope) {
                        $outletStockScope($stockQuery);
                        $stockQuery->whereHas('stock', fn ($stock) => $stock->where('serial_number', $search));
                    });
            })
            ->whereHas('ownerStocks', $outletStockScope)
            ->limit(10)
            ->pluck('products.id');
    }
}