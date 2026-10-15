<?php

namespace App\Http\Controllers;

use App\Exports\OutletPurchaseSingleExport;
use App\Models\Outlet;
use App\Models\OutletPurchase;
use App\Models\OutletPurchaseItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\MinimumStockCalculator;
use App\Services\OutletPurchaseStockInfo;
use App\Services\OutletStockService;
use App\Support\OutletAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

class OutletPurchaseController extends Controller
{
    public function index(Request $request)
    {
        $this->ensurePurchaseAccess();

        $outlets = OutletAccess::outlets();
        $selectedOutletId = OutletAccess::id($request, false);

        // Default filter: outlet pada pembelian terbaru (hanya dari outlet yang boleh diakses user).
        if (! $selectedOutletId && ! $request->has('outlet_id')) {
            $selectedOutletId = OutletPurchase::whereIn('outlet_id', $outlets->pluck('id'))
                ->orderByDesc('purchase_date')
                ->orderByDesc('id')
                ->value('outlet_id');
        }

        // Halaman hanya render shell tabel; data diambil server-side lewat getIndexData().
        return view('outlet-purchases.index', compact('outlets', 'selectedOutletId'));
    }

    public function getIndexData(Request $request)
    {
        $this->ensurePurchaseAccess();

        $draw = (int) $request->input('draw');
        $outletId = OutletAccess::id($request, false);
        $start = max((int) $request->input('start', 0), 0);
        $length = (int) $request->input('length', 25);
        $length = $length > 0 ? min($length, 100) : 25;
        $searchValue = trim((string) $request->input('search.value', ''));
        $orderDir = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Index kolom DataTables (urutan kolom di outlet-purchases/index.blade.php).
        $sortable = [
            1 => 'outlet_purchases.code',
            2 => 'outlet_purchases.purchase_date',
            3 => 'outlets.name',
            4 => 'suppliers.name',
            6 => 'outlet_purchases.subtotal',
            7 => 'outlet_purchases.status',
        ];
        $orderBy = $sortable[(int) $request->input('order.0.column', 2)] ?? 'outlet_purchases.purchase_date';

        // Query dasar hanya join tabel 1:1 (supplier, outlet) untuk search/sort.
        // Item produk TIDAK di-join di sini supaya baris tidak berlipat ganda dan count tetap ringan.
        $base = OutletPurchase::query()
            ->leftJoin('suppliers', 'suppliers.id', '=', 'outlet_purchases.supplier_id')
            ->leftJoin('outlets', 'outlets.id', '=', 'outlet_purchases.outlet_id')
            ->when($outletId, fn ($query) => $query->where('outlet_purchases.outlet_id', $outletId));

        $recordsTotal = (clone $base)->count('outlet_purchases.id');

        // Filter status (draft | pending | received); nilai lain diabaikan (= semua status).
        $statusFilter = (string) $request->input('status', '');
        if (in_array($statusFilter, [
            OutletPurchase::STATUS_DRAFT,
            OutletPurchase::STATUS_PENDING,
            OutletPurchase::STATUS_RECEIVED,
        ], true)) {
            $base->where('outlet_purchases.status', $statusFilter);
        }

        // SEARCH berbasis AWALAN (LIKE 'kata%') agar index bisa dipakai di puluhan ribu data:
        // "jet sw" / "jet sweet" menemukan "JET SWEET", tetapi "sweet" tidak.
        // Berlaku untuk nama produk, kode produk, kode POUT, no. invoice, dan nama supplier.
        // Pencarian produk memakai subquery IN (bukan JOIN) agar 1 POUT dengan banyak
        // produk tidak menggandakan baris.
        if ($searchValue !== '') {
            $prefix = addcslashes($searchValue, '\\%_') . '%';

            $base->where(function ($query) use ($prefix) {
                $query->where('outlet_purchases.code', 'like', $prefix)
                    ->orWhere('outlet_purchases.invoice_number', 'like', $prefix)
                    ->orWhere('suppliers.name', 'like', $prefix)
                    ->orWhereIn('outlet_purchases.id', function ($sub) use ($prefix) {
                        $sub->select('opi.outlet_purchase_id')
                            ->from('outlet_purchase_items as opi')
                            ->join('products as p', 'p.id', '=', 'opi.product_id')
                            ->whereNull('p.deleted_at')
                            ->where(function ($q) use ($prefix) {
                                $q->where('p.name', 'like', $prefix)
                                    ->orWhere('p.code', 'like', $prefix);
                            });
                    });
            });
        }

        $recordsFiltered = (clone $base)->count('outlet_purchases.id');

        // Ambil ID halaman ini saja. Tie-breaker id unik supaya urutan stabil antar halaman.
        $pageIds = $base
            ->orderBy($orderBy, $orderDir)
            ->orderBy('outlet_purchases.id', $orderDir)
            ->offset($start)
            ->limit($length)
            ->pluck('outlet_purchases.id');

        // Relasi dimuat hanya untuk baris di halaman ini (maks 100), kolom produk dibatasi.
        $order = $pageIds->flip();
        $purchases = OutletPurchase::with([
            'outlet:id,name',
            'supplier:id,name',
            'items:id,outlet_purchase_id,product_id,qty',
            'items.product' => fn ($query) => $query->withTrashed()
                ->select('id', 'code', 'name', 'konversi_qty', 'satuan_besar', 'satuan'),
        ])
            ->whereIn('id', $pageIds)
            ->get()
            ->sortBy(fn ($purchase) => $order[$purchase->id])
            ->values();

        $data = $purchases->map(function ($purchase) {
            $items = $purchase->items;
            $totalItems = $items->count();
            $itemsHtml = '<ul class="list-unstyled" style="margin:0">';
            foreach ($items as $index => $item) {
                $hidden = $index >= 3;
                $konversi = $item->product?->konversiDisplay($item->qty);
                $konversiLabel = ($konversi && $konversi !== '-')
                    ? ' <span class="label label-info">' . e($konversi) . '</span>'
                    : '';

                $itemsHtml .= '<li class="' . ($hidden ? 'extra-item-' . $purchase->id : '') . '"'
                    . ($hidden ? ' style="display:none"' : '') . '>'
                    . '<small>' . e($item->product?->code) . ' | ' . e($item->product?->name)
                    . ' × ' . e($item->qty) . $konversiLabel . '</small></li>';
            }
            $itemsHtml .= '</ul>';

            if ($totalItems > 3) {
                $itemsHtml .= '<a href="javascript:void(0)" class="btn-toggle-items" data-target="' . $purchase->id . '" data-state="closed" style="display:inline-block;margin-top:4px;">'
                    . '<span class="label label-default">Selengkapnya (' . ($totalItems - 3) . ')</span></a>';
            }

            if ($purchase->isDraft()) {
                $statusHtml = '<span class="label label-default">Draft</span>';
                $aksiHtml = '<a href="' . route('outlet-purchases.edit', $purchase) . '" class="btn btn-xs btn-warning">Lanjutkan</a>'
                    . ' <a href="' . route('outlet-purchases.print', $purchase) . '" class="btn btn-xs btn-success" title="Dokumen PO Outlet (Excel)">Print</a>'
                    . ' <button type="button" class="btn btn-xs btn-danger btn-delete-draft" data-url="' . e(route('outlet-purchases.destroy', $purchase)) . '" data-code="' . e($purchase->code) . '">Hapus Draft</button>';
            } else {
                $statusHtml = $purchase->isPending()
                    ? '<span class="label label-warning">Menunggu Penerimaan</span>'
                    : '<span class="label label-success">Completed</span>';

                $aksiHtml = '<a href="' . route('outlet-purchases.show', $purchase) . '" class="btn btn-xs btn-info">Lihat</a>';
                if ($purchase->isPending()) {
                    $aksiHtml .= ' <a href="' . route('outlet-purchases.edit', $purchase) . '" class="btn btn-xs btn-warning">Edit</a>'
                        . ' <a href="' . route('outlet-purchases.print', $purchase) . '" class="btn btn-xs btn-success" title="Dokumen PO Outlet (Excel)">Print</a>';
                }
            }

            return [
                'id' => $purchase->id,
                'code' => $purchase->code,
                'tanggal' => optional($purchase->purchase_date)->format('Y-m-d'),
                'outlet' => $purchase->outlet?->name ?? '-',
                'supplier' => $purchase->supplier?->name ?? '-',
                'items_html' => $itemsHtml,
                'subtotal' => 'Rp. ' . number_format($purchase->subtotal, 0, ',', '.'),
                'status_html' => $statusHtml,
                'aksi_html' => $aksiHtml,
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data->values(),
        ]);
    }

    /**
     * Tambah belanja langsung = langsung membuat DRAFT lalu membuka halaman edit (sama seperti PO).
     * Outlet dipilih saat klik Tambah dan tidak diganti lagi. Draft kosong milik user di outlet yang sama
     * dipakai ulang supaya klik Tambah berulang tidak menumpuk draft kosong.
     */
    public function create(Request $request)
    {
        $this->ensurePurchaseAccess();

        $outletId = OutletAccess::id($request, false);
        if (! $outletId) {
            return redirect()
                ->route('outlet-purchases.index')
                ->with('toast_error', 'Pilih outlet terlebih dahulu sebelum menambah belanja langsung.');
        }

        $draft = DB::transaction(function () use ($outletId) {
            // Kunci baris outlet sebagai mutex: klik ganda / dua tab tidak bisa membuat dua draft sekaligus.
            Outlet::whereKey($outletId)->lockForUpdate()->firstOrFail();

            $reusable = OutletPurchase::query()
                ->where('outlet_id', $outletId)
                ->where('created_by', auth()->id())
                ->where('status', OutletPurchase::STATUS_DRAFT)
                ->whereNull('supplier_id')
                ->whereDoesntHave('items')
                ->orderBy('id')
                ->first();

            if ($reusable) {
                // Draft kosong lama: tanggal disegarkan ke hari ini.
                if (! $reusable->purchase_date || $reusable->purchase_date->lt(today())) {
                    $reusable->update(['purchase_date' => today()->toDateString()]);
                }

                return $reusable;
            }

            return OutletPurchase::create([
                'code' => $this->generateCode(),
                'outlet_id' => $outletId,
                'supplier_id' => null,
                'created_by' => auth()->id(),
                'purchase_date' => today()->toDateString(),
                'subtotal' => 0,
                'paid_amount' => 0,
                'payment_method' => 'Internal outlet purchase',
                // Wajib eksplisit: default kolom status di database adalah 'received'.
                'status' => OutletPurchase::STATUS_DRAFT,
            ]);
        });

        return redirect()->route('outlet-purchases.edit', $draft);
    }

    public function edit(OutletPurchase $outletPurchase)
    {
        $this->ensurePurchaseAccess();
        $this->authorizeOutlet($outletPurchase);

        if (! $outletPurchase->isEditable()) {
            return redirect()
                ->route('outlet-purchases.show', $outletPurchase)
                ->with('toast_error', 'Pembelian ini sudah completed dan tidak bisa diedit lagi.');
        }

        $outletPurchase->load(['outlet:id,name', 'items' => fn ($query) => $query->orderBy('id'), 'items.product']);

        return view('outlet-purchases.edit', [
            'purchase' => $outletPurchase,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Cek Barang toko: produk milik supplier + stok TOKO, Dalam Pesanan, min stok TOKO, dan harga awal
     * (HPP terbaru outlet itu). Outlet diambil dari POUT (terkunci), bukan dari request, dan POUT yang sedang
     * diedit dikecualikan dari "Dalam Pesanan". Sengaja TIDAK membaca stok gudang maupun min stok gudang (K5).
     * Semua angka dihitung dengan query agregat per kelompok produk (tanpa query per produk).
     */
    public function cekBarang(
        Request $request,
        OutletPurchase $outletPurchase,
        MinimumStockCalculator $calculator,
        OutletPurchaseStockInfo $stockInfo
    ) {
        $this->ensurePurchaseAccess();
        $this->authorizeOutlet($outletPurchase);

        $supplierId = $request->integer('supplier_id');
        if (! $supplierId) {
            return response()->json([]);
        }

        $products = Product::query()
            ->select('id', 'code', 'name', 'harga_beli', 'konversi_qty', 'satuan_besar', 'satuan')
            ->whereHas('suppliers', fn ($query) => $query->where('suppliers.id', $supplierId))
            ->orderBy('name')
            ->get();

        $outletId = (int) $outletPurchase->outlet_id;
        $minimums = [];
        $info = [];
        // Dibagi per 2000 produk supaya jumlah binding query tetap aman untuk supplier dengan produk sangat banyak.
        foreach (array_chunk($products->pluck('id')->all(), 2000) as $chunk) {
            $minimums += $calculator->forOutlet($outletId, $chunk);
            $info += $stockInfo->forProducts($outletId, $chunk, (int) $outletPurchase->id);
        }

        $rows = $products->map(function ($product) use ($minimums, $info) {
            $min = $minimums[$product->id] ?? null;
            $data = $info[$product->id] ?? ['stock' => 0.0, 'on_order' => 0.0, 'price' => (float) $product->harga_beli];

            $minFinal = (int) ($min['min_final'] ?? 0);
            $stock = (float) $data['stock'];
            $onOrder = (float) $data['on_order'];
            $under = $stock < $minFinal; // K4: OUT OF STOCK bila stok < min

            return [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'konversi_qty' => $product->konversi_qty,
                'satuan_besar' => $product->satuan_besar,
                'satuan' => $product->satuan,
                // Harga awal baris = HPP terbaru outlet (fallback harga_beli produk), dibulatkan: form harga memakai angka bulat.
                'harga_beli' => (int) round($data['price']),
                'stock_count' => $stock,
                'on_order' => $onOrder,
                'effective_min' => $minFinal,
                'min_source' => $min['source'] ?? 'insufficient', // auto | insufficient (data belum cukup)
                'min_adjustment_pct' => (float) ($min['adjustment_pct'] ?? 0),
                'average_sold' => round((float) ($min['average_sold'] ?? 0), 1),
                'is_under_minimum' => $under,
                // Qty otomatis hanya untuk baris OUT OF STOCK: min - stok - dalam pesanan (minimal 0).
                'suggested_qty' => $under ? max(0, (int) ceil($minFinal - $stock - $onOrder)) : 0,
                // Kurang dari min, tetapi stok + dalam pesanan sudah cukup: badge "Sudah dipesan".
                'already_ordered' => $under && ($stock + $onOrder) >= $minFinal,
            ];
        });

        return response()->json($rows->values());
    }

    /**
     * Autosave header: supplier, tanggal, no. nota, catatan. Outlet tidak ikut (terkunci sejak draft dibuat).
     * Mengganti supplier mengosongkan item di server (sama seperti PO).
     */
    public function autosaveHeader(Request $request, OutletPurchase $outletPurchase)
    {
        $this->ensurePurchaseAccess();
        $this->authorizeOutlet($outletPurchase);

        $data = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'purchase_date' => 'nullable|date',
            'invoice_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ]);

        $result = DB::transaction(function () use ($request, $outletPurchase, $data) {
            // Kunci header supaya tidak bentrok dengan autosave item yang sedang berjalan.
            $purchase = OutletPurchase::whereKey($outletPurchase->id)->lockForUpdate()->firstOrFail();

            if (! $purchase->isEditable()) {
                return $this->notEditableResponse();
            }

            $updates = [];

            if ($request->has('supplier_id')) {
                $newSupplierId = $data['supplier_id'] ?? null;

                if ((int) $purchase->supplier_id !== (int) $newSupplierId) {
                    // Daftar produk di layar langsung dikosongkan saat supplier diganti; samakan di database.
                    $purchase->items()->delete();
                    $updates['subtotal'] = 0;
                }

                $updates['supplier_id'] = $newSupplierId;
            }

            // purchase_date NOT NULL: hanya diubah kalau terisi.
            if (! empty($data['purchase_date'])) {
                $updates['purchase_date'] = $data['purchase_date'];
            }
            if ($request->has('invoice_number')) {
                $updates['invoice_number'] = $data['invoice_number'] ?? null;
            }
            if ($request->has('notes')) {
                $updates['notes'] = $data['notes'] ?? null;
            }

            if ($updates) {
                $purchase->update($updates);
            }

            return response()->json([
                'status' => 'ok',
                'code' => $purchase->code,
                'total' => (float) $purchase->subtotal,
                'saved_at' => now()->toDateTimeString(),
            ]);
        });

        return $result;
    }

    /**
     * Autosave satu item (buat baru bila `id` kosong, ubah bila ada). Satu produk hanya boleh 1x per POUT.
     * Tidak mengubah products.harga_beli (HPP toko terbentuk saat penerimaan, HPP gudang tidak disentuh).
     */
    public function autosaveItem(Request $request, OutletPurchase $outletPurchase)
    {
        $this->ensurePurchaseAccess();
        $this->authorizeOutlet($outletPurchase);

        $data = $request->validate([
            'id' => 'nullable|integer',
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1',
            'harga_beli' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($data, $outletPurchase) {
            // Kunci header: request autosave bersamaan untuk POUT yang sama diproses satu per satu,
            // jadi pengecekan produk ganda di bawah tidak bisa "kebalap".
            $purchase = OutletPurchase::whereKey($outletPurchase->id)->lockForUpdate()->firstOrFail();

            if (! $purchase->isEditable()) {
                return $this->notEditableResponse();
            }

            $product = Product::findOrFail($data['product_id']);

            $item = null;
            if (! empty($data['id'])) {
                $item = $purchase->items()->find($data['id']);

                if (! $item) {
                    return response()->json([
                        'status' => 'error',
                        'code' => 'item_not_found',
                        'message' => 'Item tidak ditemukan (mungkin sudah dihapus).',
                    ], 404);
                }
            }

            if ($this->productAlreadyInPurchase($purchase, $product, $item?->id)) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'duplicate',
                    'message' => 'Produk "' . $product->name . '" (' . $product->code . ') sudah ada di belanja langsung ini.',
                ], 422);
            }

            $qty = (int) $data['qty'];
            $hargaBeli = (float) $data['harga_beli'];

            $attributes = [
                'product_id' => $product->id,
                'qty' => $qty,
                'harga_beli' => $hargaBeli,
                'subtotal' => $qty * $hargaBeli,
            ];
            // expired_at sengaja tidak disentuh di sini: diisi saat penerimaan (receive). Nilai lama pada POUT
            // pending yang dibuat sebelum perubahan ini tetap terbawa sebagai nilai awal di form penerimaan.

            if ($item) {
                $item->update($attributes);
            } else {
                $item = $purchase->items()->create($attributes + [
                    'owner_stock_id' => null,
                    'qty_received' => null,
                ]);
            }

            // Nomor batch dibuat server dari id item: unik, tidak bentrok saat item dihapus lalu ditambah,
            // dan tidak berubah selama item masih ada.
            if (blank($item->batch_number)) {
                $item->update(['batch_number' => $purchase->code . '-' . $item->id]);
            }

            $total = (float) $purchase->items()->sum('subtotal');
            $purchase->update(['subtotal' => $total]);

            return response()->json([
                'status' => 'ok',
                'item_id' => $item->id,
                'subtotal' => (float) $item->subtotal,
                'total' => $total,
                'batch_number' => $item->batch_number,
            ]);
        });
    }

    public function destroyItem(OutletPurchase $outletPurchase, OutletPurchaseItem $item)
    {
        $this->ensurePurchaseAccess();
        $this->authorizeOutlet($outletPurchase);
        abort_unless((int) $item->outlet_purchase_id === (int) $outletPurchase->id, 404);

        return DB::transaction(function () use ($outletPurchase, $item) {
            $purchase = OutletPurchase::whereKey($outletPurchase->id)->lockForUpdate()->firstOrFail();

            if (! $purchase->isEditable()) {
                return $this->notEditableResponse();
            }

            $item->delete();

            $total = (float) $purchase->items()->sum('subtotal');
            $purchase->update(['subtotal' => $total]);

            return response()->json(['status' => 'ok', 'total' => $total]);
        });
    }

    /**
     * Selesai mengisi: validasi supplier, outlet, dan minimal 1 item, lalu draft menjadi Menunggu Penerimaan.
     * Pembelian yang sudah pending tetap bisa disimpan ulang lewat tombol yang sama.
     */
    public function finish(OutletPurchase $outletPurchase)
    {
        $this->ensurePurchaseAccess();
        $this->authorizeOutlet($outletPurchase);

        try {
            DB::transaction(function () use ($outletPurchase) {
                $purchase = OutletPurchase::whereKey($outletPurchase->id)->lockForUpdate()->firstOrFail();

                if (! $purchase->isEditable()) {
                    throw new RuntimeException('Pembelian ini sudah completed dan tidak bisa diedit lagi.');
                }
                if (! $purchase->outlet_id) {
                    throw new RuntimeException('Outlet belum terisi.');
                }
                if (! $purchase->supplier_id) {
                    throw new RuntimeException('Supplier harus dipilih.');
                }
                if (! $purchase->items()->exists()) {
                    throw new RuntimeException('Minimal harus ada 1 item produk.');
                }

                $purchase->update([
                    'status' => OutletPurchase::STATUS_PENDING,
                    'subtotal' => (float) $purchase->items()->sum('subtotal'),
                ]);
            });
        } catch (RuntimeException $e) {
            return back()->with('toast_error', $e->getMessage());
        }

        return redirect()
            ->route('outlet-purchases.index')
            ->with('toast_success', "Belanja langsung {$outletPurchase->code} tersimpan. Stock toko bertambah setelah penerimaan barang diselesaikan.");
    }

    /**
     * Untuk pembelian pending, halaman ini adalah halaman penerimaan barang.
     * Untuk pembelian completed, halaman ini hanya detail (read-only).
     */
    public function show(OutletPurchase $outletPurchase)
    {
        $this->ensurePurchaseAccess();
        $this->authorizeOutlet($outletPurchase);

        // Draft belum punya penerimaan barang: lanjutkan pengisian di halaman edit.
        if ($outletPurchase->isDraft()) {
            return redirect()->route('outlet-purchases.edit', $outletPurchase);
        }

        return view('outlet-purchases.show', [
            'purchase' => $outletPurchase->load(['outlet', 'supplier', 'creator', 'receiver', 'items.product']),
        ]);
    }

    /**
     * Dokumen PO Outlet (Excel), modelnya sama dengan tombol "PO" di menu Pembelian.
     * Boleh untuk draft, pending, maupun completed; staff outlet hanya untuk outlet miliknya.
     */
    public function print(OutletPurchase $outletPurchase)
    {
        $this->ensurePurchaseAccess();
        $this->authorizeOutlet($outletPurchase);

        $outletPurchase->load([
            'outlet:id,name',
            'supplier:id,name',
            'items.product' => fn ($query) => $query->withTrashed(),
        ]);

        $disk = Storage::disk('public');
        $settings = $disk->exists('settings.json')
            ? (json_decode($disk->get('settings.json'), true) ?: [])
            : [];

        $safeCode = preg_replace('/[^A-Za-z0-9\-]/', '_', (string) $outletPurchase->code);

        return Excel::download(
            new OutletPurchaseSingleExport($outletPurchase, $settings),
            'Dokumen_' . $safeCode . '.xlsx'
        );
    }

    /**
     * Selesaikan penerimaan barang: qty yang datang masuk ke stock toko (batch, HPP, kartu stok),
     * lalu status menjadi completed dan tidak bisa diedit lagi.
     */
    public function receive(Request $request, OutletPurchase $outletPurchase, OutletStockService $stockService)
    {
        $this->ensurePurchaseAccess();
        $this->authorizeOutlet($outletPurchase);

        $request->validate([
            'items' => 'required|array',
            'items.*.qty_received' => 'nullable|integer|min:0',
            'items.*.expired_at' => 'nullable|date',
        ], [
            'items.required' => 'Data item penerimaan wajib diisi.',
            'items.*.qty_received.integer' => 'Qty diterima harus berupa angka bulat.',
            'items.*.qty_received.min' => 'Qty diterima tidak boleh negatif.',
            'items.*.expired_at.date' => 'Tanggal kadaluarsa tidak valid.',
        ]);

        $itemData = $request->input('items', []);

        try {
            DB::transaction(function () use ($outletPurchase, $itemData, $stockService) {
                // Cegah terima ganda (stok & movement akan tercatat dua kali)
                $purchase = OutletPurchase::lockForUpdate()->findOrFail($outletPurchase->id);
                if ($purchase->isDraft()) {
                    throw new RuntimeException('Pembelian ini masih draft. Selesaikan pengisiannya dulu sebelum penerimaan barang.');
                }
                if (! $purchase->isPending()) {
                    throw new RuntimeException('Pembelian ini sudah completed dan tidak bisa diproses ulang.');
                }

                $purchase->load('items.product');
                $subtotal = 0;

                foreach ($purchase->items as $item) {
                    $qtyReceived = (int) ($itemData[$item->id]['qty_received'] ?? $item->qty);

                    if ($qtyReceived < 0 || $qtyReceived > (int) $item->qty) {
                        throw new RuntimeException(
                            "Qty diterima untuk {$item->product?->name} ({$qtyReceived}) tidak boleh melebihi qty dipesan ({$item->qty})."
                        );
                    }

                    // Kadaluarsa diisi saat penerimaan (opsional). Bila kolomnya tidak ikut terkirim (halaman lama
                    // yang masih terbuka), pakai nilai yang sudah tersimpan di item.
                    $row = $itemData[$item->id] ?? [];
                    $expiredAt = array_key_exists('expired_at', $row)
                        ? ($row['expired_at'] ?: null)
                        : $item->expired_at;

                    $ownerStockId = null;
                    if ($qtyReceived > 0) {
                        $ownerStock = $stockService->receive(
                            (int) $purchase->outlet_id,
                            (int) $item->product_id,
                            $qtyReceived,
                            (float) $item->harga_beli,
                            [
                                'expired_at' => $expiredAt,
                                'batch_number' => $item->batch_number,
                                'source_type' => OutletPurchase::class,
                                'source_id' => $purchase->id,
                                'notes' => "Pembelian langsung outlet {$purchase->code} dari supplier",
                            ],
                            OutletPurchase::class,
                            $purchase->id,
                            auth()->user()
                        );
                        $ownerStockId = $ownerStock->id;
                    }

                    $lineSubtotal = $qtyReceived * (float) $item->harga_beli;
                    $item->update([
                        'owner_stock_id' => $ownerStockId,
                        'qty_received' => $qtyReceived,
                        'subtotal' => $lineSubtotal,
                        'expired_at' => $expiredAt,
                    ]);
                    $subtotal += $lineSubtotal;
                }

                $purchase->update([
                    'status' => OutletPurchase::STATUS_RECEIVED,
                    'subtotal' => $subtotal,
                    'received_by' => auth()->id(),
                    'received_at' => now(),
                ]);
            });
        } catch (\Exception $e) {
            return back()->with('toast_error', $e->getMessage());
        }

        return redirect()
            ->route('outlet-purchases.index')
            ->with('toast_success', "Penerimaan barang {$outletPurchase->code} selesai dan stock toko sudah bertambah.");
    }

    /**
     * Hapus draft. Hanya status draft yang boleh dihapus: draft belum punya stok, kartu stok, maupun
     * dampak ke laporan, jadi dihapus permanen (item ikut terhapus lewat cascade).
     */
    public function destroy(OutletPurchase $outletPurchase)
    {
        $this->ensurePurchaseAccess();
        $this->authorizeOutlet($outletPurchase);

        $code = $outletPurchase->code;

        try {
            DB::transaction(function () use ($outletPurchase) {
                $purchase = OutletPurchase::lockForUpdate()->findOrFail($outletPurchase->id);
                if (! $purchase->isDraft()) {
                    throw new RuntimeException('Hanya draft yang bisa dihapus.');
                }

                $purchase->forceDelete();
            });
        } catch (RuntimeException $e) {
            return redirect()->route('outlet-purchases.index')->with('toast_error', $e->getMessage());
        }

        return redirect()
            ->route('outlet-purchases.index')
            ->with('toast_success', "Draft {$code} dihapus.");
    }

    /** Kode POUT unik (termasuk yang sudah di-soft-delete, karena kolom code unik di database). */
    private function generateCode(): string
    {
        do {
            $code = 'POUT-' . now()->format('YmdHis') . '-' . random_int(10, 99);
        } while (OutletPurchase::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    /** Respons seragam bila autosave datang setelah dokumen tidak bisa diedit lagi (mis. sudah completed). */
    private function notEditableResponse()
    {
        return response()->json([
            'status' => 'error',
            'code' => 'not_editable',
            'message' => 'Pembelian ini sudah completed dan tidak bisa diedit lagi.',
        ], 422);
    }

    /** Produk (product_id ATAU kode/barcode yang sama) hanya boleh 1x per belanja langsung. */
    private function productAlreadyInPurchase(OutletPurchase $purchase, Product $product, ?int $exceptItemId = null): bool
    {
        return $purchase->items()
            ->when($exceptItemId, fn ($query) => $query->where('id', '!=', $exceptItemId))
            ->where(function ($query) use ($product) {
                $query->where('product_id', $product->id);

                if (filled($product->code)) {
                    $query->orWhereHas('product', fn ($p) => $p->where('code', $product->code));
                }
            })
            ->exists();
    }

    /** Staff outlet hanya boleh membuka pembelian outlet miliknya. */
    private function authorizeOutlet(OutletPurchase $purchase): void
    {
        $user = auth()->user();

        if ($user && in_array($user->role, ['staff-outlet', 'kasir'], true)) {
            abort_unless(
                $user->outlet_id && (int) $user->outlet_id === (int) $purchase->outlet_id,
                403,
                'User tidak memiliki akses ke outlet tersebut.'
            );
        }
    }

    private function ensurePurchaseAccess(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['superadmin', 'admin-gudang', 'owner', 'staff-outlet'], true), 403);
    }
}