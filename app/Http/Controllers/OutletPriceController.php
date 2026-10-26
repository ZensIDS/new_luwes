<?php

namespace App\Http\Controllers;

use App\Http\Requests\OutletPriceRequest;
use App\Http\Requests\NewProductFromPriceRequest;
use App\Http\Requests\UpdateProductFromPriceRequest;
use App\Models\Category;
use App\Models\Outlet;
use App\Models\OutletPrice;
use App\Models\OwnerStock;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\LatestHpp;
use App\Services\OtherIncomeService;
use App\Services\PriceCalculator;
use App\Support\OutletAccess;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

class OutletPriceController extends Controller
{
    private const HPP_LOG_NAME = 'OutletPriceHpp';

    /** Session key: outlet terakhir yang dibuka di halaman Master Harga Jual. */
    private const LAST_OUTLET_SESSION = 'outlet_prices.last_outlet_id';

    /** Session key: kata pencarian terakhir di outlet tersebut. */
    private const LAST_SEARCH_SESSION = 'outlet_prices.last_search';

    /**
     * Penyesuaian Harga Outlet (nominal, Rp) yang otomatis terisi di form saat outlet tertentu dipilih.
     * Format: [outlet_id => nominal].
     */
    private const OUTLET_ADJUSTMENT_DEFAULTS = [
        2 => 100,
    ];

    /**
     * Outlet yang dipakai di index: pilihan di URL, kalau kosong pakai outlet terakhir yang dibuka
     * (disimpan di session), baru fallback ke outlet milik user. Hasilnya diingat lagi.
     */
    private function resolveOutletId(Request $request): ?int
    {
        if (! $request->filled('outlet_id') && ! $this->isOutletRestricted($request)) {
            $lastId = $this->lastOutletId();
            if ($lastId) {
                $request->merge(['outlet_id' => $lastId]);
            }
        }

        $outletId = OutletAccess::id($request, false);

        if ($outletId) {
            session([self::LAST_OUTLET_SESSION => $outletId]);
        }

        return $outletId;
    }

    /**
     * Parameter URL index setelah create/update/hapus: outlet yang dikerjakan + pencarian terakhir
     * (hanya jika pencarian itu memang di outlet yang sama).
     */
    private function indexParams(?int $outletId): array
    {
        $params = array_filter(['outlet_id' => $outletId]);
        $search = (string) session(self::LAST_SEARCH_SESSION, '');

        if ($outletId && $search !== '' && (int) session(self::LAST_OUTLET_SESSION, 0) === (int) $outletId) {
            $params['search'] = $search;
        }

        return $params;
    }

    private function lastOutletId(): ?int
    {
        $lastId = (int) session(self::LAST_OUTLET_SESSION, 0);

        return $lastId && Outlet::whereKey($lastId)->exists() ? $lastId : null;
    }

    private function isOutletRestricted(Request $request): bool
    {
        return $request->user() && in_array($request->user()->role, ['staff-outlet', 'kasir'], true);
    }

    public function index(Request $request, PriceCalculator $calculator)
    {
        $this->ensureManagementAccess();
        // Buka tanpa parameter (misal dari menu) = outlet terakhir tetap dipakai, pencarian mulai kosong.
        // Pencarian hanya dibawa lewat redirect setelah simpan/edit/hapus (lihat indexParams()).
        $outletId = $this->resolveOutletId($request);

        $search = trim((string) $request->input('search', ''));
        session([self::LAST_SEARCH_SESSION => $search]);
        $prices = $outletId
            ? $this->pricePage($outletId, $search)
            : new LengthAwarePaginator([], 0, 25);

        if ($outletId) {
            $latestHpp = app(LatestHpp::class)->forProducts($outletId, $prices->getCollection()->pluck('product_id'));

            // Stok terkini outlet ini per produk (SUM owner_stocks.qty), 1 query untuk satu halaman.
            $outletStocks = OwnerStock::where('owner_id', $outletId)
                ->whereIn('product_id', $prices->getCollection()->pluck('product_id'))
                ->selectRaw('product_id, SUM(qty) as total_qty')
                ->groupBy('product_id')
                ->pluck('total_qty', 'product_id');

            $prices->getCollection()->each(function (OutletPrice $price) use ($calculator, $latestHpp, $outletStocks) {
                $product = $price->product;

                $price->setAttribute('print_hpp', (float) ($latestHpp[$price->product_id] ?? 0));
                $price->setAttribute('outlet_stock_qty', (int) ($outletStocks[$price->product_id] ?? 0));
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

    /**
     * Satu halaman master harga (25 baris) untuk satu outlet, tanpa perlu index tambahan.
     *
     * "Deferred join": query pengurutan HANYA mengambil kolom id (sort kecil, tidak membebani memori
     * walau satu outlet punya puluhan ribu aturan harga); baris lengkap + relasi baru dimuat untuk
     * 25 id di halaman ini. Search berbasis AWALAN (LIKE 'kata%') lewat subquery IN.
     * Urutan: abjad nama produk (A-Z), juga saat search/filter outlet dipakai.
     */
    private function pricePage(int $outletId, string $search): LengthAwarePaginator
    {
        $prefix = $search !== '' ? addcslashes($search, '\\%_') . '%' : null;

        $page = OutletPrice::query()
            ->select('outlet_prices.id')
            ->where('outlet_prices.outlet_id', $outletId)
            ->when($prefix !== null, fn ($query) => $query->whereIn('outlet_prices.product_id', Product::query()
                ->select('id')
                ->where(fn ($productQuery) => $productQuery
                    ->where('name', 'like', $prefix)
                    ->orWhere('code', 'like', $prefix))))
            ->leftJoin('products', 'products.id', '=', 'outlet_prices.product_id')
            ->orderBy('products.name')
            ->orderBy('outlet_prices.id')
            ->paginate(25)
            ->withQueryString()
            ->appends(['outlet_id' => $outletId, 'search' => $search]);

        $ids = $page->getCollection()->pluck('id');
        $order = $ids->flip();

        $page->setCollection(
            OutletPrice::with(['outlet', 'product'])
                ->whereIn('id', $ids)
                ->get()
                ->sortBy(fn (OutletPrice $price) => $order[$price->id])
                ->values()
        );

        return $page;
    }

    /**
     * Riwayat perubahan HPP produk di outlet ini (dari activity log batch stok outlet),
     * untuk modal di kolom HPP pada index.
     */
    public function hppHistory(OutletPrice $outletPrice)
    {
        $this->ensureManagementAccess();

        // Batch MINUS tidak pernah menjadi sumber HPP (HPP-nya hanya perkiraan), jadi tidak ikut riwayat.
        $stocks = OwnerStock::withTrashed()
            ->notDeficit()
            ->where('owner_id', $outletPrice->outlet_id)
            ->where('product_id', $outletPrice->product_id)
            ->get(['id', 'batch_number'])
            ->keyBy('id');

        // 1) Perubahan HPP pada batch stok outlet ini.
        $batchRows = $stocks->isEmpty()
            ? collect()
            : Activity::where('subject_type', (new OwnerStock)->getMorphClass())
                ->whereIn('subject_id', $stocks->keys())
                ->whereNotNull('properties->attributes->hpp')
                ->latest()
                ->limit(200)
                ->with('causer')
                ->get()
                ->filter(fn ($activity) => isset($activity->properties['attributes']['hpp']))
                ->map(function ($activity) use ($stocks) {
                    $stock = $stocks->get($activity->subject_id);
                    $old = $activity->event === 'created' ? null : ($activity->properties['old']['hpp'] ?? null);

                    return [
                        'at' => $activity->created_at,
                        'source' => 'Batch ' . ($stock?->batch_number ?: ('#' . $activity->subject_id)),
                        'old' => $old === null ? null : (float) $old,
                        'new' => (float) $activity->properties['attributes']['hpp'],
                        'user' => $activity->causer?->name ?? 'System',
                        'event' => $activity->event,
                    ];
                });

        // 2) Outlet belum punya batch -> HPP diambil dari harga beli produk; perubahan HPP
        //    dari form master harga disimpan ke sana, jadi riwayatnya diambil dari log produk.
        $product = $outletPrice->product;
        $productRows = ! $product
            ? collect()
            : Activity::forSubject($product)
                ->whereNotNull('properties->attributes->harga_beli')
                ->latest()
                ->limit(200)
                ->with('causer')
                ->get()
                ->filter(fn ($activity) => isset($activity->properties['attributes']['harga_beli']))
                ->map(function ($activity) {
                    $old = $activity->event === 'created' ? null : ($activity->properties['old']['harga_beli'] ?? null);

                    return [
                        'at' => $activity->created_at,
                        'source' => 'Harga beli produk',
                        'old' => $old === null ? null : (float) $old,
                        'new' => (float) $activity->properties['attributes']['harga_beli'],
                        'user' => $activity->causer?->name ?? 'System',
                        'event' => $activity->event,
                    ];
                });

        // 3) Perubahan HPP yang dilakukan langsung dari form master harga ini.
        $masterRows = Activity::where('log_name', self::HPP_LOG_NAME)
            ->where('subject_type', $outletPrice->getMorphClass())
            ->where('subject_id', $outletPrice->id)
            ->latest()
            ->limit(200)
            ->with('causer')
            ->get()
            ->map(function ($activity) {
                $batch = $activity->properties['batch_number'] ?? null;
                $target = ($activity->properties['target'] ?? null) === 'batch'
                    ? ' (batch ' . ($batch ?: 'terbaru') . ')'
                    : ' (harga beli produk)';

                return [
                    'at' => $activity->created_at,
                    'source' => 'Master harga' . $target,
                    'old' => isset($activity->properties['old']) ? (float) $activity->properties['old'] : null,
                    'new' => (float) ($activity->properties['new'] ?? 0),
                    'user' => $activity->causer?->name ?? 'System',
                    'event' => 'updated',
                ];
            });

        // Log otomatis batch / produk yang kembar dengan catatan master harga (perubahan yang sama,
        // user sama, waktu berdekatan) dibuang supaya tidak tampil dobel.
        $isMirror = fn (array $row) => $row['event'] === 'updated' && $masterRows->contains(
            fn (array $master) => $master['user'] === $row['user']
                && abs($master['new'] - $row['new']) < 0.005
                && abs($master['at']->getTimestamp() - $row['at']->getTimestamp()) <= 10
        );

        $data = $batchRows->reject($isMirror)
            ->concat($productRows->reject($isMirror))
            ->concat($masterRows)
            ->sortBy(fn ($row) => $row['at']->getTimestamp())
            ->map(fn ($row) => [
                'date' => $row['at']->format('d M Y H:i'),
                'user' => $row['user'],
                'batch' => $row['source'],
                'old' => $row['old'],
                'new' => $row['new'],
                'event' => $row['event'],
            ])
            ->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function create(Request $request)
    {
        $this->ensureManagementAccess();
        $newPrice = new OutletPrice([
            'disc_brand_type' => 'percentage',
            'pajak_type' => 'percentage',
            'pajak_value' => 0,
            'disc_tambahan_type' => 'percentage',
            'disc_tambahan_value' => 0,
            'disc_tambahan_2_type' => 'percentage',
            'disc_tambahan_2_value' => 0,
            'disc_tambahan_3_type' => 'percentage',
            'disc_tambahan_3_value' => 0,
            'disc_tambahan_4_type' => 'percentage',
            'disc_tambahan_4_value' => 0,
            'margin_type' => 'percentage',
            'margin_value' => 0,
            'disc_toko_type' => 'percentage',
            'disc_toko_value' => 0,
            'outlet_adjustment_type' => 'nominal',
            'outlet_adjustment_value' => 0,
            'is_active' => true,
        ]);

        // Setelah daftar produk baru: outlet & produk langsung terpilih di form ini.
        $outlets = OutletAccess::outlets();
        if ($request->filled('outlet_id') && $outlets->contains('id', (int) $request->query('outlet_id'))) {
            $newPrice->outlet_id = (int) $request->query('outlet_id');
        } elseif (! $request->filled('outlet_id') && ($lastId = $this->lastOutletId()) && $outlets->contains('id', $lastId)) {
            $newPrice->outlet_id = $lastId;
        }

        return view('outlet-prices.form', [
            'price' => $newPrice,
            'outlets' => $outlets,
            'selectedProduct' => $this->selectedProduct($request->old('product_id', $request->query('product_id'))),
            'method' => 'POST',
            'action' => route('outlet-prices.store'),
            'previewHpp' => null,
            'outletAdjustmentDefaults' => self::OUTLET_ADJUSTMENT_DEFAULTS,
            // Untuk panel "Daftarkan Produk Baru" (hanya dipakai di mode tambah)
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'statusProdukOptions' => Product::STATUS_PRODUK,
            'listingNominal' => app(OtherIncomeService::class)->listingNominal(),
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
                ...Arr::except($request->validated(), ['hpp', 'hpp_changed', 'hpp_unit', 'hpp_besar']),
                'created_by' => auth()->id(),
                'is_active' => $request->boolean('is_active', true),
            ]);
            if ($price->trashed()) {
                $price->restore();
            }
            if ($price->exists) {
                // Simpan ulang data lama selalu memperbarui updated_at, walau isinya sama.
                $price->setUpdatedAt(now());
            }
            $price->save();

            $this->syncLatestHpp($request, $price);
        });

        return redirect()
            ->route('outlet-prices.index', $this->indexParams((int) $request->outlet_id))
            ->with('toast_success', 'Master harga outlet berhasil disimpan.');
    }

    /**
     * Daftarkan produk baru langsung dari form master harga (untuk staff outlet),
     * lalu kembali ke form aturan harga dengan produk & outlet sudah terpilih.
     * Validasi sama dengan menu Product (barcode unik, dll).
     */
    public function storeProduct(NewProductFromPriceRequest $request)
    {
        $this->ensureManagementAccess();

        // harga_beli sudah dikonversi ke per satuan kecil bila diinput per satuan besar
        $data = $request->productData();
        if (($data['status_produk'] ?? 'sudah') !== 'tambahan_diskon') {
            $data['status_produk_note'] = null;
        }

        try {
            $product = DB::transaction(function () use ($request, $data) {
                $product = Product::create($data);

                if ($request->filled('supplier_ids')) {
                    $product->suppliers()->sync($request->supplier_ids);
                }

                // Status Listing: catat Other Income (umum, tanpa outlet) di transaksi yang sama.
                // Gagal mencatat (mis. nominal belum diatur) = produk batal dibuat (B2).
                if (($data['status_produk'] ?? 'sudah') === 'listing') {
                    app(OtherIncomeService::class)->recordListing($product, $request->user());
                }

                return $product;
            });
        } catch (ValidationException $e) {
            // Form ini memakai error bag "newProduct" (terpisah dari form aturan harga), C5.
            throw $e->errorBag('newProduct');
        }

        $outletId = $request->filled('outlet_id') ? (int) $request->input('outlet_id') : null;

        return redirect()
            ->route('outlet-prices.create', array_filter([
                'product_id' => $product->id,
                'outlet_id' => $outletId,
            ]))
            ->with('toast_success', "Produk \"{$product->name}\" berhasil didaftarkan. Silakan atur harga jualnya.");
    }

    /**
     * Data produk (JSON) untuk mengisi panel "Edit Barang" di form tambah aturan harga.
     * Termasuk kategori, supplier, status produk, satuan & konversi, serta info jendela Other Income Listing
     * (sama seperti halaman edit Product) supaya panel bisa menampilkan keterangan yang benar.
     */
    public function editProduct(Product $product)
    {
        $this->ensureManagementAccess();

        $otherIncome = app(OtherIncomeService::class);
        $listingAlreadyRecorded = $otherIncome->hasListing($product);
        $listingWindowOpen = (int) $product->update_count === 0 && ! $listingAlreadyRecorded;

        return response()->json([
            'id' => $product->id,
            'code' => $product->code,
            'name' => $product->name,
            'category_id' => $product->category_id,
            'satuan' => $product->satuan,
            'satuan_besar' => $product->satuan_besar,
            'konversi_qty' => $product->konversi_qty !== null ? (float) $product->konversi_qty : null,
            'harga_beli' => (float) $product->harga_beli,
            'harga_beli_besar' => $product->harga_beli_besar !== null ? (float) $product->harga_beli_besar : null,
            'supplier_ids' => $product->suppliers()->pluck('suppliers.id')->values(),
            'status_produk' => $product->status_produk ?: 'sudah',
            'status_produk_note' => $product->status_produk_note,
            'brand' => $product->brand,
            'min_stock' => (int) $product->min_stock,
            'warna' => $product->warna,
            'ukuran' => $product->ukuran,
            'model' => $product->model,
            'lokasi' => $product->lokasi,
            // 'trigger' = simpan ini masih memicu income Listing; 'recorded' = sudah pernah tercatat; 'closed' = sudah lewat jendela.
            'listing_mode' => $listingWindowOpen ? 'trigger' : ($listingAlreadyRecorded ? 'recorded' : 'closed'),
            'listing_nominal' => $otherIncome->listingNominal(),
        ]);
    }

    /**
     * Simpan hasil "Edit Barang" dari form master harga. Aturan simpan sama dengan ProductController::update
     * (kunci baris, pemicu Other Income Listing hanya di update pertama, hitung update_count, sinkron supplier),
     * tetapi hanya kolom yang tampil di panel yang diubah.
     *
     * Dipanggil lewat AJAX (JSON) supaya isian form harga yang sudah diketik tidak hilang;
     * tanpa JSON tetap ada redirect ke form tambah dengan produk terpilih.
     */
    public function updateProduct(UpdateProductFromPriceRequest $request, Product $product)
    {
        $this->ensureManagementAccess();

        $data = $request->productData();
        if (($data['status_produk'] ?? 'sudah') !== 'tambahan_diskon') {
            $data['status_produk_note'] = null;
        }

        try {
            $locked = DB::transaction(function () use ($request, $data, $product) {
                // Kunci baris produk: dua simpan bersamaan tidak bisa sama-sama lolos jendela Listing.
                $locked = Product::lockForUpdate()->findOrFail($product->id);

                $isFirstUpdate = (int) $locked->update_count === 0;
                $newStatus = $data['status_produk'] ?? $locked->status_produk;

                // harga_beli (kecil) & harga_beli_besar sudah dihitung konsisten di productData();
                // model Product juga menjaga keduanya tetap sinkron saat disimpan.
                $locked->update($data);

                $service = app(OtherIncomeService::class);
                if ($isFirstUpdate && $newStatus === 'listing' && ! $service->hasListing($locked)) {
                    $service->recordListing($locked, $request->user());
                }

                // Sama seperti form Product: setiap simpan lewat form edit dihitung satu update.
                $locked->increment('update_count');

                $locked->suppliers()->sync($request->input('supplier_ids', []));

                return $locked;
            });
        } catch (ValidationException $e) {
            // Panel memakai error bag "newProduct" (terpisah dari form aturan harga).
            throw $e->errorBag('newProduct');
        }

        $message = "Barang \"{$locked->name}\" berhasil diperbarui.";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'product' => [
                    'id' => $locked->id,
                    'code' => $locked->code,
                    'name' => $locked->name,
                ],
            ]);
        }

        return redirect()
            ->route('outlet-prices.create', array_filter([
                'product_id' => $locked->id,
                'outlet_id' => $request->filled('outlet_id') ? (int) $request->input('outlet_id') : null,
            ]))
            ->with('toast_success', $message);
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
            // Panel "Edit Barang" (sama seperti di form tambah; tombol Daftarkan Produk Baru tidak ditampilkan di sini)
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'statusProdukOptions' => Product::STATUS_PRODUK,
            'listingNominal' => app(OtherIncomeService::class)->listingNominal(),
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
            'outlet_id' => 'nullable|integer|exists:outlets,id',
            'product_id' => 'required|integer|exists:products,id',
        ]);

        $productId = (int) $request->product_id;
        $latest = app(LatestHpp::class);

        // HPP dianggap sama di semua outlet: tanpa outlet dipakai HPP terbaru global.
        $hppKecil = $request->filled('outlet_id')
            ? $latest->forProduct((int) $request->outlet_id, $productId)
            : $latest->forProductGlobal($productId);

        $product = Product::find($productId, ['id', 'satuan', 'satuan_besar', 'konversi_qty']);
        $konversi = (float) ($product?->konversi_qty ?? 0);
        $hasBesar = $product && $product->satuan_besar && $konversi >= 1;

        return response()->json([
            'hpp' => $hppKecil,
            'hpp_kecil' => $hppKecil,
            'hpp_besar' => $hasBesar ? round($hppKecil * $konversi, 2) : null,
            'satuan' => $product?->satuan,
            'satuan_besar' => $hasBesar ? $product->satuan_besar : null,
            'konversi_qty' => $hasBesar ? $konversi : null,
            'konversi_text' => $hasBesar ? $product->konversi_string : '-',
        ]);
    }

    /**
     * Kalau HPP diubah dari form master harga, simpan sebagai HPP terbaru.
     * Hanya jalan bila form menandai angkanya benar-benar diubah (hpp_changed),
     * supaya angka bawaan form tidak menimpa data tanpa disengaja.
     */
    private function syncLatestHpp(Request $request, OutletPrice $price): void
    {
        if (! $request->boolean('hpp_changed') || $request->input('hpp') === null) {
            return;
        }

        $outletId = (int) $price->outlet_id;
        $productId = (int) $price->product_id;
        $hpp = round((float) $request->input('hpp'), 2);

        // HPP diisi per satuan besar: HPP per satuan kecil (yang dipakai hitung harga jual) = besar / konversi.
        $product = Product::find($productId, ['id', 'konversi_qty']);
        $konversi = (float) ($product?->konversi_qty ?? 0);
        $inputBesar = $request->input('hpp_unit') === 'besar'
            && $request->filled('hpp_besar')
            && $konversi >= 1;
        $hppBesar = $inputBesar ? round((float) $request->input('hpp_besar'), 2) : null;
        if ($inputBesar) {
            $hpp = round($hppBesar / $konversi, 2);
        }

        $latest = app(LatestHpp::class);
        $oldHpp = $latest->forProduct($outletId, $productId);
        $stock = $latest->stockFor($outletId, $productId);

        if ($stock) {
            $stock->update(['hpp' => $hpp]);
        } else {
            $productUpdate = ['harga_beli' => $hpp];
            if ($konversi >= 1) {
                // update() query builder tidak memicu sinkronisasi di model Product, jadi harga besar diisi di sini.
                $productUpdate['harga_beli_besar'] = $hppBesar ?? round($hpp * $konversi, 2);
            }
            Product::whereKey($productId)->update($productUpdate);
        }

        if (abs($oldHpp - $hpp) < 0.005) {
            return;
        }

        // Catat eksplisit supaya perubahan HPP dari master harga selalu muncul di riwayat HPP,
        // tidak bergantung pada log otomatis batch / produk.
        activity(self::HPP_LOG_NAME)
            ->performedOn($price)
            ->causedBy(auth()->user())
            ->event('hpp_changed')
            ->withProperties([
                'outlet_id' => $outletId,
                'product_id' => $productId,
                'old' => $oldHpp,
                'new' => $hpp,
                'target' => $stock ? 'batch' : 'product',
                'input_unit' => $inputBesar ? 'besar' : 'kecil',
                'hpp_besar' => $hppBesar,
                'batch_number' => $stock?->batch_number,
            ])
            ->log('HPP diubah dari master harga');
    }

    public function update(OutletPriceRequest $request, OutletPrice $outletPrice)
    {
        DB::transaction(function () use ($request, $outletPrice) {
            $outletPrice->fill([
                ...Arr::except($request->validated(), ['hpp', 'hpp_changed', 'hpp_unit', 'hpp_besar']),
                'is_active' => $request->boolean('is_active'),
            ]);
            // Setiap kali form edit disimpan, updated_at selalu diperbarui
            // (Eloquent biasanya melewatkannya bila tidak ada kolom yang berubah).
            $outletPrice->setUpdatedAt(now());
            $outletPrice->save();

            $this->syncLatestHpp($request, $outletPrice);
        });

        return redirect()
            ->route('outlet-prices.index', $this->indexParams((int) $outletPrice->outlet_id))
            ->with('toast_success', 'Master harga outlet berhasil diperbarui.');
    }

    /**
     * Tombol "Kembali" di form edit: tidak mengubah data, tetapi tetap memperbarui updated_at
     * lalu kembali ke index dengan outlet yang sama.
     */
    public function touch(OutletPrice $outletPrice)
    {
        $this->ensureManagementAccess();

        $outletPrice->setUpdatedAt(now());
        $outletPrice->save();

        return redirect()->route('outlet-prices.index', $this->indexParams((int) $outletPrice->outlet_id));
    }

    public function destroy(OutletPrice $outletPrice)
    {
        $this->ensureManagementAccess();
        $outletId = $outletPrice->outlet_id;
        $outletPrice->delete();

        return redirect()
            ->route('outlet-prices.index', $this->indexParams((int) $outletId))
            ->with('toast_success', 'Master harga outlet berhasil dihapus.');
    }

    private function ensureManagementAccess(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['superadmin', 'admin-gudang', 'owner', 'staff-outlet'], true), 403);
    }
}