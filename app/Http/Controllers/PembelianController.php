<?php

namespace App\Http\Controllers;

use App\Http\Requests\PembelianRequest;
use App\Models\Kas;
use App\Models\Outlet;
use App\Models\Pembelian;
use App\Models\PembelianProduct;
use App\Models\PembelianTransaction;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockPembelian;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDF;

class PembelianController extends Controller
{
    public function getPembelian($outlet_id)
    {
        $pembelians = Pembelian::where('outlet_id', $outlet_id)->get();

        return response()->json($pembelians);
    }

    public function getItems($pembelian_id)
    {
        $pembelian = Pembelian::find($pembelian_id);
        if ($pembelian) {
            $items = $pembelian->stocks;

            return response()->json($items);
        } else {
            return response()->json([], 404);
        }
    }

    public function getProductsBySupplier(Supplier $supplier)
    {
        $products = $supplier->products()
            ->select('products.id', 'code', 'name', 'is_serialized', 'harga_beli', 'konversi_qty', 'satuan_besar', 'satuan')
            ->get()
            ->map(function ($product) {
                $product->stock_count = $product->stocks()->sum('qty_available');

                return $product;
            });

        return response()->json($products);
    }

    public function getAllProducts()
    {
        $products = Product::select('id', 'code', 'name', 'is_serialized', 'harga_beli', 'min_stock', 'konversi_qty', 'satuan_besar', 'satuan')
            ->withSum('stocks', 'qty_available')
            ->orderBy('name');

        if (request()->filled('supplier_id')) {
            $supplierId = request()->integer('supplier_id');
            $products->whereHas('suppliers', fn($query) => $query->where('suppliers.id', $supplierId));
        }

        $products = $products->get()
            ->map(function ($product) {
                $currentStock = (int) ($product->stocks_sum_qty_available ?? 0);
                $effectiveMin = $product->effective_min_stock;   // ← compute once
                $product->stock_count      = $currentStock;
                $product->effective_min    = $effectiveMin;      // ← expose as 'effective_min'
                $product->is_under_minimum = $currentStock <= $effectiveMin;

                return $product;
            });

        return response()->json($products);
    }

    public function index()
    {
        // Tidak lagi query semua PO di sini — halaman awal cuma render shell tabel.
        return view('pembelians.index');
    }

    public function getIndexData(Request $request)
    {
        $draw        = (int) $request->input('draw');
        $start       = max((int) $request->input('start', 0), 0);
        $length      = (int) $request->input('length', 25);
        $length      = $length > 0 ? min($length, 100) : 25;
        $searchValue = trim((string) ($request->input('search.value', '')));

        $orderColIndex = (int) $request->input('order.0.column', 2);
        $orderDir      = strtolower($request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortableColumns = [
            1 => 'pembelians.code',
            2 => 'pembelians.created_at',
            3 => 'suppliers.name',
            5 => 'pembelians.is_published',
        ];
        $orderBy = $sortableColumns[$orderColIndex] ?? 'pembelians.created_at';

        // Query dasar cuma join tabel yang dibutuhkan untuk search/sort/filter,
        // TIDAK eager-load pembelianProducts di sini (itu berat & belum perlu untuk listing/count).
        $base = \App\Models\Pembelian::query()
            ->leftJoin('suppliers', 'suppliers.id', '=', 'pembelians.supplier_id');

        $recordsTotal = (clone $base)->count('pembelians.id');

        // ==== SEARCH: meniru "smart search" DataTables, tapi tetap ringan ====
        // DataTables (client-side) memecah input jadi kata per kata dan mencari baris
        // yang mengandung SEMUA kata itu di kolom manapun, tidak peduli urutan/kerapatan.
        // Contoh: "wing surya" tetap match "Wings Surya" karena "wing" dan "surya"
        // masing-masing ketemu sebagai substring, walau tidak nempel persis.
        //
        // Query LIKE '%wing surya%' (satu string utuh) TIDAK match "Wings Surya"
        // karena butuh substring persis "wing surya" (ada huruf 's' nyempil di
        // "Wings" sebelum spasi) -> makanya data "hilang" versi lama.
        //
        // Solusinya: pecah $searchValue jadi per kata, lalu AND-kan syarat antar kata
        // (tiap kata WAJIB match di salah satu kolom), OR-kan antar kolom untuk kata
        // yang sama. Supaya tetap ringan untuk ratusan ribu baris:
        //  - Query dasar TETAP hanya join suppliers (tidak eager-load produk di sini).
        //  - Pencarian ke produk pakai whereHas (EXISTS subquery), bukan JOIN penuh,
        //    supaya tidak menggandakan baris pembelian saat 1 PO punya banyak produk.
        //  - Filter kata dibatasi (misal maks 5 kata) supaya user tidak bisa bikin
        //    query jadi sangat berat dengan mengetik puluhan kata sekaligus.
        //  - Pastikan ada index di pembelians.code, suppliers.name, products.name,
        //    products.code (dan foreign key pembelian_products.product_id /
        //    pembelian_id) supaya tiap LIKE + EXISTS tetap cepat di data besar.
        //    Kalau volume sudah jutaan baris & LIKE mulai lambat, pertimbangkan
        //    MySQL FULLTEXT index sebagai upgrade berikutnya.
        if ($searchValue !== '') {
            $searchWords = preg_split('/\s+/', $searchValue, -1, PREG_SPLIT_NO_EMPTY);
            $searchWords = array_slice($searchWords, 0, 5); // batasi biar tidak disalahgunakan

            $base->where(function ($q) use ($searchWords) {
                foreach ($searchWords as $word) {
                    $q->where(function ($qw) use ($word) {
                        $qw->where('pembelians.code', 'like', "%{$word}%")
                            ->orWhere('suppliers.name', 'like', "%{$word}%")
                            ->orWhereHas('pembelianProducts.product', function ($qp) use ($word) {
                                $qp->where('name', 'like', "%{$word}%")
                                    ->orWhere('code', 'like', "%{$word}%");
                            });
                    });
                }
            });
        }

        $recordsFiltered = (clone $base)->count('pembelians.id');

        // Ambil ID untuk halaman ini saja (ringan, tanpa eager load berat)
        $pageIds = $base
            ->orderBy($orderBy, $orderDir)
            // Tie-breaker unik. Kolom seperti created_at (bisa sama persis kalau
            // banyak PO dibuat di detik yang sama, misal via import/seed) atau
            // is_published (cuma 2 nilai) punya banyak baris kembar. Tanpa secondary
            // order by kolom unik, urutan hasil untuk baris bernilai sama TIDAK
            // dijamin konsisten antar query -> saat DataTables pindah halaman /
            // re-query gara-gara search, ada baris yang bisa terlewat (tidak pernah
            // muncul di offset manapun) atau malah dobel.
            ->orderBy('pembelians.id', $orderDir)
            ->offset($start)
            ->limit($length)
            ->pluck('pembelians.id');

        // Baru sekarang load relasi lengkap, TAPI cuma untuk baris di halaman ini (max 100 row)
        $pembelians = \App\Models\Pembelian::with([
            'supplier',
            'pembelianProducts.product',
            'pembelianTransaction',
            'ownerApprovedBy',
        ])
            ->whereIn('id', $pageIds)
            ->get()
            ->sortBy(fn($p) => array_search($p->id, $pageIds->all()))
            ->values();

        $authUser = auth()->user();

        $data = $pembelians->map(function ($value) use ($authUser) {
            $payStatus = $value->pembelianTransaction?->status ?? 'unpaid';

            // ==== Kolom Items ====
            $totalItems = $value->pembelianProducts->count();
            $itemsHtml = '<ul class="list-unstyled" style="margin:0">';
            foreach ($value->pembelianProducts as $index => $item) {
                $extraClass = $index >= 3 ? ' extra-item-' . $value->id : '';
                $style = $index >= 3 ? ' style="display:none"' : '';
                $k = $item->product?->konversiDisplay($item->qty);
                $kLabel = ($k && $k !== '-') ? ' <span class="label label-info">' . e($k) . '</span>' : '';

                $itemsHtml .= '<li class="item-pembelian-' . $value->id . $extraClass . '"' . $style . '>'
                    . '<small>' . e($item->product?->code) . ' | ' . e($item->product?->name) . ' × ' . e($item->qty) . $kLabel . '</small>'
                    . '</li>';
            }
            $itemsHtml .= '</ul>';

            if ($totalItems > 3) {
                $itemsHtml .= '<a href="javascript:void(0)" class="btn-toggle-items" data-target="' . $value->id . '" data-state="closed" style="display:inline-block;margin-top:4px;">'
                    . '<span class="label label-default">Selengkapnya (' . ($totalItems - 3) . ')</span></a>';
            }

            // ==== Kolom Status PO ====
            $statusHtml = $value->is_published
                ? '<span class="label label-success">PUBLISHED</span>'
                : '<span class="label label-default">DRAFT</span>';

            // ==== Kolom Aksi ====
            $aksiHtml = '';

            if (in_array($authUser->role, ['owner', 'superadmin']) && $value->owner_approval_status === 'pending') {
                $aksiHtml .= '<form action="' . route('pembelian.owner-approve', $value->id) . '" method="post" style="display:inline">'
                    . csrf_field()
                    . '<button class="btn btn-xs btn-success" title="ACC Owner"><i class="fa fa-check"></i> ACC</button></form> ';
                $aksiHtml .= '<form action="' . route('pembelian.owner-reject', $value->id) . '" method="post" style="display:inline">'
                    . csrf_field()
                    . '<button class="btn btn-xs btn-danger" title="Tolak Owner"><i class="fa fa-times"></i> Tolak</button></form> ';
            }

            if ($value->canBeEditedBy($authUser)) {
                $aksiHtml .= '<a href="' . route('pembelian.edit', $value->id) . '" class="btn btn-xs btn-warning" title="Edit"><i class="fa fa-pencil"></i></a> ';

                if ($authUser->role !== 'owner') {
                    $aksiHtml .= '<form action="' . route('pembelian.destroy', $value->id) . '" method="post" style="display:inline">'
                        . method_field('delete') . csrf_field()
                        . '<button class="btn btn-xs btn-danger" onclick="return confirm(\'Hapus PO ' . e($value->code) . '?\')" title="Hapus"><i class="fa fa-trash"></i></button></form> ';
                }
            }

            $aksiHtml .= '<a href="' . route('laporan.pembelian', $value->id) . '" class="btn btn-xs btn-success" title="Export PO"><i class="fa fa-file-excel-o"></i> PO</a>';

            return [
                'id'          => $value->id,
                'code'        => $value->code,
                'tanggal'     => $value->created_at->setTimezone('Asia/Jakarta')->translatedFormat('d F Y - H:i') . ' WIB',
                'supplier'    => $value->supplier?->name ?? '-',
                'items_html'  => $itemsHtml,
                'status_html' => $statusHtml,
                'aksi_html'   => $aksiHtml,
            ];
        });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data->values(),
        ]);
    }

    public function create()
    {
        $lastPembelian = Pembelian::latest('id')->first();
        $nextNumber = $lastPembelian ? ((int) substr($lastPembelian->code, 4) + 1) : 1;
        $code = 'PO' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

        return view('pembelians.create', [
            'suppliers' => Supplier::get(),
            'products' => collect(),
            'code' => $code
        ]);
    }

    public function store(PembelianRequest $request)
    {
        if (auth()->user()->role === 'owner') {
            abort(403);
        }
        $request->validated();

        $pembelian = Pembelian::create([
            'code' => $this->generatePoCode($request->supplier_id),
            'supplier_id' => $request->supplier_id,
            'total' => $request->total,
            'is_published' => false,
            'owner_approval_status' => 'approved',
            'owner_approved_by' => null,
            'owner_approved_at' => null,
            'owner_approval_note' => null,
        ]);
        $this->updateStock($request, $pembelian);
        $supplier = Supplier::find($request->supplier_id);
        PembelianTransaction::create([
            'pembelian_id' => $pembelian->id,
            'payment_date' => null,
            'payment_method' => 'bank_transfer',
            'payment_reference' => $supplier->bank_no_rek . '-' . $supplier->bank_nama ?? 'TRX-' . now(),
            'amount' => 0,
            'status' => 'unpaid',
            'notes' => null,
        ]);
        return redirect(route('pembelian.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function show(Pembelian $pembelian)
    {
        // dd($pembelian->load(['stocks', 'stocks.product'])->toArray());
        return view('pembelian.show', [
            'pembelian' => $pembelian,
        ]);
    }

    public function print(Pembelian $pembelian)
    {
        $pdf = PDF::loadView('pembelians.pembelian_pdf', ['pembelian' => $pembelian]);

        return $pdf->download('pembelian_' . $pembelian->id . '.pdf');
    }

    public function edit(Pembelian $pembelian)
    {
        if (! $pembelian->canBeEditedBy(auth()->user())) {
            return redirect()->route('pembelian.index')
                ->with('toast_error', 'PO ini belum bisa diedit. Admin gudang hanya bisa edit setelah ACC, sedangkan owner dan superadmin bisa edit kapan saja sebelum published.');
        }

        return view('pembelians.edit', [
            'pembelian' => $pembelian,
            'kas' => Kas::get(),
            'outlets' => Outlet::get(),
            'suppliers' => Supplier::get(),
            'products' => collect(),
        ]);
    }

    public function update(PembelianRequest $request, Pembelian $pembelian)
    {
        if (! $pembelian->canBeEditedBy(auth()->user())) {
            return redirect()->route('pembelian.index')
                ->with('toast_error', 'PO ini belum bisa diedit. Admin gudang hanya bisa edit setelah ACC, sedangkan owner dan superadmin bisa edit kapan saja sebelum published.');
        }

        $data = $request->validated();
        $pembelian->update($data);
        $this->updateStock($request, $pembelian);

        return redirect(route('pembelian.index'))->with('toast_success', 'Berhasil Memperbarui Data!');
    }

    public function publish(Pembelian $pembelian)
    {
        if ($pembelian->owner_approval_status !== 'approved') {
            return redirect()->route('pembelian.index')
                ->with('toast_error', 'PO masih menunggu ACC owner.');
        }

        // Prevent double publishing
        if ($pembelian->is_published) {
            return redirect()->route('pembelian.index')
                ->with('toast_error', 'Pembelian already published');
        }

        return redirect()->route('pembelian.penerimaan', $pembelian);
    }

    public function penerimaanIndex()
    {
        // Halaman awal tidak lagi query semua PO — cuma render shell tabel.
        return view('pembelians.penerimaan-index');
    }

    public function getPenerimaanIndexData(Request $request)
    {
        $draw        = (int) $request->input('draw');
        $start       = max((int) $request->input('start', 0), 0);
        $length      = (int) $request->input('length', 25);
        $length      = $length > 0 ? min($length, 100) : 25;
        $searchValue = trim((string) ($request->input('search.value', '')));

        $orderColIndex = (int) $request->input('order.0.column', 2);
        $orderDir      = strtolower($request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortableColumns = [
            1 => 'pembelians.code',
            2 => 'pembelians.code_gr',
            3 => 'suppliers.name',
            5 => 'pembelians.receipt_status',
            6 => 'pembelians.owner_approval_status',
            7 => 'pembelians.receipt_date',
            8 => 'pembelians.receipt_pic',
        ];
        $orderBy = $sortableColumns[$orderColIndex] ?? 'pembelians.created_at';
        $orderDirDefault = $request->has('order.0.column') ? $orderDir : 'desc'; // default: PO terbaru dulu, setara ->latest()

        $base = \App\Models\Pembelian::query()
            ->leftJoin('suppliers', 'suppliers.id', '=', 'pembelians.supplier_id');

        $recordsTotal = (clone $base)->count('pembelians.id');

        // ==== SEARCH: meniru "smart search" DataTables, tapi tetap ringan ====
        // DataTables (client-side) memecah input jadi kata per kata dan mencari baris
        // yang mengandung SEMUA kata itu di kolom manapun, tidak peduli urutan/kerapatan.
        // Contoh: "wing surya" tetap match "Wings Surya" karena "wing" dan "surya"
        // masing-masing ketemu sebagai substring, walau tidak nempel persis.
        //
        // LIKE '%wing surya%' (satu string utuh) TIDAK match "Wings Surya" karena
        // butuh substring persis "wing surya" -> data terasa "hilang" di versi lama.
        //
        // Fix: pecah $searchValue jadi per kata, AND-kan syarat antar kata (tiap kata
        // wajib match di salah satu kolom), OR-kan antar kolom untuk kata yang sama.
        // Supaya tetap ringan untuk ratusan ribu baris:
        //  - Query dasar tetap hanya join suppliers, tidak eager-load produk di sini.
        //  - Pencarian ke produk pakai whereHas (EXISTS subquery), bukan JOIN penuh,
        //    supaya tidak menggandakan baris pembelian saat 1 PO punya banyak produk.
        //  - Jumlah kata dibatasi (maks 5) supaya query tidak bisa dibuat sangat berat.
        //  - Pastikan ada index di pembelians.code, pembelians.code_gr, suppliers.name,
        //    pembelians.receipt_pic, products.name, products.code, serta foreign key
        //    pembelian_products.pembelian_id / product_id.
        if ($searchValue !== '') {
            $searchWords = preg_split('/\s+/', $searchValue, -1, PREG_SPLIT_NO_EMPTY);
            $searchWords = array_slice($searchWords, 0, 5); // batasi biar tidak disalahgunakan

            $base->where(function ($q) use ($searchWords) {
                foreach ($searchWords as $word) {
                    $q->where(function ($qw) use ($word) {
                        $qw->where('pembelians.code', 'like', "%{$word}%")
                            ->orWhere('pembelians.code_gr', 'like', "%{$word}%")
                            ->orWhere('suppliers.name', 'like', "%{$word}%")
                            ->orWhere('pembelians.receipt_pic', 'like', "%{$word}%")
                            ->orWhereHas('pembelianProducts.product', function ($qp) use ($word) {
                                $qp->where('name', 'like', "%{$word}%")
                                    ->orWhere('code', 'like', "%{$word}%");
                            });
                    });
                }
            });
        }

        $recordsFiltered = (clone $base)->count('pembelians.id');

        $pageIds = $base
            ->orderBy($orderBy, $orderDirDefault)
            // Tie-breaker unik. Kolom seperti receipt_status / owner_approval_status
            // hanya punya sedikit nilai berbeda, jadi banyak baris kembar. Tanpa
            // secondary order by kolom unik, urutan hasil untuk baris bernilai sama
            // TIDAK dijamin konsisten antar query -> saat DataTables pindah halaman
            // / re-query gara-gara search, ada baris yang bisa terlewat (tidak pernah
            // muncul di offset manapun) atau malah dobel.
            ->orderBy('pembelians.id', $orderDirDefault)
            ->offset($start)
            ->limit($length)
            ->pluck('pembelians.id');

        // Relasi berat cuma dimuat untuk baris di halaman ini
        $pembelians = \App\Models\Pembelian::with([
            'supplier',
            'pembelianProducts.product',
            'stocks',
            'ownerApprovedBy',
        ])
            ->whereIn('id', $pageIds)
            ->get()
            ->sortBy(fn($p) => array_search($p->id, $pageIds->all()))
            ->values();

        $data = $pembelians->map(function ($value) {
            $receiptStatus = $value->receipt_status ?? 'draft';
            $receiptBadge  = match ($receiptStatus) {
                'completed' => 'success',
                'validated' => 'info',
                default     => 'warning',
            };

            // ==== Kolom Items ====
            $totalItems = $value->pembelianProducts->count();
            $itemsHtml = '<ul class="list-unstyled" style="margin:0">';
            foreach ($value->pembelianProducts as $index => $item) {
                $extraClass = $index >= 3 ? ' extra-item-pembelian-' . $value->id : '';
                $style = $index >= 3 ? ' style="display:none"' : '';

                $konversiLabel = '';
                if ($item->product?->konversi_qty && $item->product?->satuan_besar) {
                    $konversiLabel = ' <span class="label label-info">' . e($item->product->konversiDisplay($item->qty)) . '</span>';
                }

                $hasStock = $value->stocks->where('product_id', $item->product_id)->count() > 0;
                $receivedLabel = $hasStock
                    ? ' <span class="label label-success">✓ ' . e($item->qty_diterima) . ' diterima</span>'
                    : ' <span class="label label-warning">Belum diterima</span>';

                $itemsHtml .= '<li class="' . trim($extraClass) . '"' . $style . '>'
                    . '<small>' . e($item->product?->code) . ' | ' . e($item->product?->name) . '</small>'
                    . ' <span class="label label-default">' . e($item->qty) . '</span>'
                    . $konversiLabel
                    . $receivedLabel
                    . '</li>';
            }
            $itemsHtml .= '</ul>';

            if ($totalItems > 3) {
                $itemsHtml .= '<a href="javascript:void(0)" class="btn-toggle-pembelian-items" data-target="' . $value->id . '" data-state="closed">'
                    . '<span class="label label-default">Selengkapnya (' . ($totalItems - 3) . ')</span></a>';
            }

            // ==== Kolom Status Penerimaan ====
            $receiptStatusHtml = '<span class="label label-' . $receiptBadge . '">' . strtoupper($receiptStatus) . '</span>';

            // ==== Kolom Status PO ====
            $approvalStatus = $value->owner_approval_status ?? 'pending';
            $approvalBadge  = match ($approvalStatus) {
                'approved' => 'success',
                'rejected' => 'danger',
                default    => 'warning',
            };
            $poStatusHtml = '<span class="label label-' . $approvalBadge . '">' . strtoupper($approvalStatus) . '</span>';
            if ($value->ownerApprovedBy) {
                $poStatusHtml .= '<br><small>' . e($value->ownerApprovedBy->name) . '</small>';
            }

            // ==== Kolom Aksi ====
            $btnLabel = $receiptStatus === 'completed' ? 'Detail' : 'Input Pembelian';
            $btnIcon  = $receiptStatus === 'completed' ? 'eye' : 'edit';
            $btnClass = $receiptStatus === 'completed' ? 'default' : 'primary';

            $aksiHtml = '<a href="' . route('pembelian.penerimaan', $value->id) . '" class="btn btn-xs btn-' . $btnClass . '">'
                . '<i class="fa fa-' . $btnIcon . '"></i> ' . $btnLabel . '</a> ';
            $aksiHtml .= '<a href="' . route('laporan.penerimaan', [$value->id, 'po']) . '" class="btn btn-xs btn-success" title="Export Pembelian">'
                . '<i class="fa fa-file-excel-o"></i> Pembelian</a> ';
            $aksiHtml .= '<a href="' . route('laporan.pdf.penerimaan-single', $value->id) . '" target="_blank" class="btn btn-xs btn-danger" title="Export PDF Pembelian">'
                . '<i class="fa fa-file-pdf-o"></i> Pembelian</a>';

            return [
                'id'                  => $value->id,
                'code'                => $value->code,
                'code_gr'             => $value->code_gr ?? '-',
                'supplier'            => $value->supplier?->name ?? '-',
                'items_html'          => $itemsHtml,
                'receipt_status_html' => $receiptStatusHtml,
                'po_status_html'      => $poStatusHtml,
                'receipt_date'        => $value->receipt_date ? \Carbon\Carbon::parse($value->receipt_date)->format('d/m/Y H:i') : '-',
                'receipt_pic'         => $value->receipt_pic ?? '-',
                'aksi_html'           => $aksiHtml,
            ];
        });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data->values(),
        ]);
    }

    public function penerimaan(Pembelian $pembelian)
    {
        $pembelian->load(['pembelianProducts.product', 'stocks.product', 'supplier']);

        // Generate SKU untuk setiap pembelianProduct yang belum punya stock
        $skuMap = [];
        $offset = Stock::where('pembelian_id', $pembelian->id)->count();
        $supplierAbbr = $this->generateSupplierAbbr($pembelian->supplier->name ?? 'SUP');
        $today = now()->format('Ymd');

        // Hitung base count dari DB (seluruh supplier hari ini)
        $baseCount = Stock::whereHas(
            'pembelian',
            fn($q) =>
            $q->where('supplier_id', $pembelian->supplier_id)
        )
            ->whereDate('created_at', now()->toDateString())
            ->count();

        $counter = max($baseCount, $offset);

        foreach ($pembelian->pembelianProducts as $pp) {
            // Cek apakah sudah ada stock untuk product ini di pembelian ini
            $existingStock = $pembelian->stocks->firstWhere('product_id', $pp->product_id);

            if ($existingStock) {
                // Sudah ada stock → pakai SKU yang sudah ada, jangan generate baru
                $skuMap[$pp->product_id] = $existingStock->sku;
            } else {
                // Belum ada stock → generate SKU baru
                $counter++;
                $noUrut = str_pad($counter, 4, '0', STR_PAD_LEFT);
                $skuMap[$pp->product_id] = "{$supplierAbbr}_{$today}_{$noUrut}";
            }
        }

        return view('pembelians.penerimaan', compact('pembelian', 'skuMap'));
    }

    private function generateSupplierAbbr(string $name): string
    {
        // Bersihkan kata-kata umum yang tidak perlu disingkat
        $skipWords = ['pt', 'cv', 'ud', 'tb', 'toko', 'dan', 'and', 'the'];

        $words = preg_split('/\s+/', strtoupper(trim($name)));
        $abbr  = '';

        foreach ($words as $word) {
            $clean = preg_replace('/[^A-Z0-9]/', '', $word);
            if ($clean && !in_array(strtolower($clean), $skipWords)) {
                $abbr .= $clean[0]; // ambil huruf pertama tiap kata
            }
        }

        return $abbr ?: 'SUP'; // fallback kalau kosong
    }

    private function generateSku(Pembelian $pembelian): string
    {
        $supplierAbbr = $this->generateSupplierAbbr($pembelian->supplier->name ?? 'SUP');
        $today        = now()->format('Ymd');

        // Hitung SKU yang sudah ada hari ini untuk supplier ini
        // (dari semua pembelian supplier yang sama)
        $countToday = Stock::whereHas(
            'pembelian',
            fn($q) =>
            $q->where('supplier_id', $pembelian->supplier_id)
        )
            ->whereDate('created_at', now()->toDateString())
            ->count();

        // Tambah juga SKU yang sudah ada di pembelian ini (yang belum tersimpan sebagai stock)
        // biar nomor urut tidak bentrok dalam 1 sesi penerimaan
        $countExisting = Stock::where('pembelian_id', $pembelian->id)->count();

        $noUrut = str_pad(max($countToday, $countExisting) + 1, 4, '0', STR_PAD_LEFT);

        return "{$supplierAbbr}_{$today}_{$noUrut}";
    }

    public function storePenerimaan(Request $request, Pembelian $pembelian)
    {
        $request->validate([
            'receipt_date' => 'nullable|date',
            'receipt_pic' => 'nullable|string',
            'receipt_status' => 'nullable|in:draft,validated,completed',
            'receipt_photo' => 'nullable|image|max:2048',
            'items' => 'required|array',
            'items.*.stock_id' => 'nullable|exists:stocks,id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.sku' => 'required|string',
            'items.*.qty_diterima' => 'required|integer',
            'items.*.expired_at' => 'nullable|date',
        ], [
            'receipt_date.date' => 'Tanggal penerimaan harus berupa tanggal yang valid.',
            'receipt_pic.string' => 'PIC penerimaan harus berupa teks.',
            'receipt_status.in' => 'Status penerimaan harus dipilih antara draft, validated, atau completed.',
            'receipt_photo.image' => 'Foto penerimaan harus berupa gambar.',
            'receipt_photo.max' => 'Ukuran foto penerimaan maksimal 2MB.',
            'items.required' => 'Item barang harus diisi.',
            'items.array' => 'Item barang harus berupa array.',
            'items.*.stock_id.exists' => 'Stock ID yang dipilih tidak valid.',
            'items.*.product_id.required' => 'Produk harus dipilih.',
            'items.*.product_id.exists' => 'Produk yang dipilih tidak ditemukan.',
            'items.*.sku.required' => 'SKU harus diisi.',
            'items.*.sku.string' => 'SKU harus berupa teks.',
            'items.*.sku.unique' => 'SKU ":input" sudah digunakan.',
            'items.*.qty_diterima.required' => 'Jumlah diterima harus diisi.',
            'items.*.qty_diterima.integer' => 'Jumlah diterima harus berupa angka.',
            // 'items.*.qty_diterima.min' => 'Jumlah diterima minimal 1.',
            'items.*.expired_at.date' => 'Tanggal kadaluarsa harus berupa tanggal yang valid.',
        ]);

        DB::beginTransaction();
        try {
            // Handle photo upload
            $photoPath = $pembelian->receipt_photo;
            if ($request->hasFile('receipt_photo')) {
                $photoPath = $request->file('receipt_photo')->store('receipt-photos', 'public');
            }

            // Update pembelian receipt info
            $pembelian->update([
                'code_gr' => $request->code_gr,
                'receipt_date' => $request->receipt_date,
                'receipt_pic' => $request->receipt_pic,
                'receipt_status' => $request->receipt_status,
                'receipt_photo' => $photoPath,
            ]);

            foreach ($request->items as $itemData) {
                $qtyDiterima = (int) $itemData['qty_diterima'];
                $sku = trim($itemData['sku']);
                $expiredAt = ! empty($itemData['expired_at']) ? $itemData['expired_at'] : null;

                $product = Product::find($itemData['product_id']);
                $pembelianProduct = $pembelian->pembelianProducts()
                    ->where('product_id', $itemData['product_id'])
                    ->first();

                $pembelianProduct?->update([
                    'qty_diterima' => $qtyDiterima,
                ]);

                if (! $pembelianProduct) {
                    continue;
                }

                // Check if updating existing stock or creating new
                if (! empty($itemData['stock_id'])) {
                    // UPDATE existing stock
                    $stock = Stock::find($itemData['stock_id']);

                    if ($stock && $stock->pembelian_id == $pembelian->id) {
                        $oldQty = $stock->qty;

                        $stock->update([
                            'sku' => $sku,
                            'qty' => $qtyDiterima,
                            'harga_beli' => $pembelianProduct->harga_beli,
                            'subtotal' => $qtyDiterima * $pembelianProduct->harga_beli,
                            'expired_at' => $expiredAt,
                        ]);

                        // Log movement if qty changed
                        if ($oldQty != $qtyDiterima) {
                            $diff = $qtyDiterima - $oldQty;
                            StockMovement::create([
                                'product_id' => $itemData['product_id'],
                                'user_id' => auth()->id(),
                                'type' => $diff > 0 ? 'in' : 'adjustment',
                                'reference_type' => Pembelian::class,
                                'reference_id' => $pembelian->id,
                                'qty_in' => $diff > 0 ? $diff : 0,
                                'qty_out' => $diff < 0 ? abs($diff) : 0,
                                'balance' => $product->stocks()->sum('qty'),
                                'notes' => "Stock update - SKU: {$sku}, Old Qty: {$oldQty}, New Qty: {$qtyDiterima}",
                            ]);
                        }
                    }
                } else {
                    // CREATE new stock
                    Stock::create([
                        'pembelian_id' => $pembelian->id,
                        'product_id' => $itemData['product_id'],
                        'sku' => $sku,
                        'harga_beli' => $pembelianProduct->harga_beli,
                        'qty' => $qtyDiterima,
                        'subtotal' => $qtyDiterima * $pembelianProduct->harga_beli,
                        'expired_at' => $expiredAt,
                        'condition' => 'new',
                        'status' => 'available',
                    ]);

                    // Decrease StockPembelian
                    $stockPembelian = StockPembelian::where([
                        'pembelian_id' => $pembelian->id,
                        'product_id' => $itemData['product_id']
                    ])->first();

                    if ($stockPembelian) {
                        $stockPembelian->decrement('qty', $qtyDiterima);
                        if ($stockPembelian->qty <= 0) {
                            $stockPembelian->delete();
                        }
                    }

                    // Log movement
                    StockMovement::create([
                        'product_id' => $itemData['product_id'],
                        'user_id' => auth()->id(),
                        'type' => 'in',
                        'reference_type' => Pembelian::class,
                        'reference_id' => $pembelian->id,
                        'qty_in' => $qtyDiterima,
                        'balance' => $product->stocks()->sum('qty'),
                        'notes' => "Goods receipt from {$pembelian->supplier->name} - SKU: {$sku}",
                    ]);
                }

                // Update product HPP
                $newHPP = $product->calculateHPP($qtyDiterima, $pembelianProduct->harga_beli);
                $product->update([
                    'harga_beli' => (int) $newHPP,
                ]);
                $product->updateStockValue();
            }

            // Mark as published if status is completed
            if ($request->receipt_status == 'completed' && ! $pembelian->is_published) {
                $pembelian->update(['is_published' => true]);
            }

            DB::commit();

            return redirect()->route('pembelian.penerimaan', $pembelian)
                ->with('toast_success', 'Penerimaan updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('toast_error', $e->getMessage())->withInput();
        }
    }

    public function updatePenerimaan(Request $request, Pembelian $pembelian)
    {
        $request->validate([
            'code_gr' => 'nullable|string',
            'receipt_date' => 'nullable|date',
            'receipt_pic' => 'nullable|string',
            'receipt_status' => 'nullable|in:draft,validated,completed',
            'receipt_photo' => 'nullable|image|max:2048',
        ], [
            'code_gr.string' => 'Kode pembelian harus berupa teks.',
            'receipt_date.date' => 'Tanggal penerimaan harus berupa tanggal yang valid.',
            'receipt_pic.string' => 'PIC penerimaan harus berupa teks.',
            'receipt_status.in' => 'Status penerimaan harus dipilih antara draft, validated, atau completed.',
            'receipt_photo.image' => 'Foto penerimaan harus berupa gambar.',
            'receipt_photo.max' => 'Ukuran foto penerimaan maksimal 2MB.',
        ]);

        DB::beginTransaction();
        try {
            $photoPath = $pembelian->receipt_photo;
            if ($request->hasFile('receipt_photo')) {
                $photoPath = $request->file('receipt_photo')->store('receipt-photos', 'public');
            }

            $pembelian->update([
                'code_gr' => $request->code_gr,
                'receipt_date' => $request->receipt_date,
                'receipt_pic' => $request->receipt_pic,
                'receipt_status' => $request->receipt_status,
                'receipt_photo' => $photoPath,
            ]);

            if ($request->receipt_status == 'completed' && ! $pembelian->is_published) {
                $pembelian->update(['is_published' => true]);
            }

            DB::commit();

            return redirect()->route('pembelian.penerimaan', $pembelian)
                ->with('toast_success', 'Penerimaan details updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('toast_error', $e->getMessage())->withInput();
        }
    }

    public function savePenerimaanItem(Request $request, Pembelian $pembelian)
    {
        $validated = $request->validate([
            'stock_id'      => 'nullable|exists:stocks,id',
            'product_id'    => 'required|exists:products,id',
            'sku'           => 'required|string',
            'qty_diterima'  => 'required|integer',
            'expired_at'    => 'nullable|date',
        ], [
            'product_id.required' => 'Produk harus dipilih.',
            'sku.required'        => 'SKU harus diisi.',
            'qty_diterima.required' => 'Jumlah diterima harus diisi.',
            'qty_diterima.integer'  => 'Jumlah diterima harus berupa angka.',
        ]);

        DB::beginTransaction();
        try {
            $qtyDiterima = (int) $validated['qty_diterima'];
            $sku         = trim($validated['sku']);
            $expiredAt   = !empty($validated['expired_at']) ? $validated['expired_at'] : null;

            $product = Product::findOrFail($validated['product_id']);

            $pembelianProduct = $pembelian->pembelianProducts()
                ->where('product_id', $validated['product_id'])
                ->first();

            if (!$pembelianProduct) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produk ini tidak terdaftar pada pembelian ini.',
                ], 422);
            }

            $pembelianProduct->update([
                'qty_diterima' => $qtyDiterima,
            ]);

            if (!empty($validated['stock_id'])) {
                // UPDATE existing stock
                $stock = Stock::find($validated['stock_id']);

                if (!$stock || $stock->pembelian_id != $pembelian->id) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Stock tidak ditemukan atau tidak sesuai pembelian ini.',
                    ], 404);
                }

                $oldQty = $stock->qty;

                $stock->update([
                    'sku'         => $sku,
                    'qty'         => $qtyDiterima,
                    'harga_beli'  => $pembelianProduct->harga_beli,
                    'subtotal'    => $qtyDiterima * $pembelianProduct->harga_beli,
                    'expired_at'  => $expiredAt,
                ]);

                if ($oldQty != $qtyDiterima) {
                    $diff = $qtyDiterima - $oldQty;
                    StockMovement::create([
                        'product_id'      => $validated['product_id'],
                        'user_id'         => auth()->id(),
                        'type'            => $diff > 0 ? 'in' : 'adjustment',
                        'reference_type'  => Pembelian::class,
                        'reference_id'    => $pembelian->id,
                        'qty_in'          => $diff > 0 ? $diff : 0,
                        'qty_out'         => $diff < 0 ? abs($diff) : 0,
                        'balance'         => $product->stocks()->sum('qty'),
                        'notes'           => "Stock update (autosave) - SKU: {$sku}, Old Qty: {$oldQty}, New Qty: {$qtyDiterima}",
                    ]);
                }
            } else {
                // CREATE new stock
                $stock = Stock::create([
                    'pembelian_id' => $pembelian->id,
                    'product_id'   => $validated['product_id'],
                    'sku'          => $sku,
                    'harga_beli'   => $pembelianProduct->harga_beli,
                    'qty'          => $qtyDiterima,
                    'subtotal'     => $qtyDiterima * $pembelianProduct->harga_beli,
                    'expired_at'   => $expiredAt,
                    'condition'    => 'new',
                    'status'       => 'available',
                ]);

                // Decrease StockPembelian
                $stockPembelian = StockPembelian::where([
                    'pembelian_id' => $pembelian->id,
                    'product_id'   => $validated['product_id'],
                ])->first();

                if ($stockPembelian) {
                    $stockPembelian->decrement('qty', $qtyDiterima);
                    if ($stockPembelian->qty <= 0) {
                        $stockPembelian->delete();
                    }
                }

                StockMovement::create([
                    'product_id'     => $validated['product_id'],
                    'user_id'        => auth()->id(),
                    'type'           => 'in',
                    'reference_type' => Pembelian::class,
                    'reference_id'   => $pembelian->id,
                    'qty_in'         => $qtyDiterima,
                    'balance'        => $product->stocks()->sum('qty'),
                    'notes'          => "Goods receipt (autosave) from {$pembelian->supplier->name} - SKU: {$sku}",
                ]);
            }

            // Update product HPP
            $newHPP = $product->calculateHPP($qtyDiterima, $pembelianProduct->harga_beli);
            $product->update(['harga_beli' => (int) $newHPP]);
            $product->updateStockValue();

            DB::commit();

            return response()->json([
                'success'  => true,
                'stock_id' => $stock->id,
                'message'  => 'Item berhasil disimpan.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updatePenerimaanExpired(Request $request, Pembelian $pembelian)
    {
        $validated = $request->validate([
            'stock_id'   => 'required|exists:stocks,id',
            'expired_at' => 'nullable|date',
        ], [
            'stock_id.required' => 'Item ini belum tersimpan, centang checkbox terlebih dahulu.',
            'stock_id.exists'   => 'Stock tidak ditemukan.',
        ]);

        $stock = Stock::find($validated['stock_id']);

        if (!$stock || $stock->pembelian_id != $pembelian->id) {
            return response()->json([
                'success' => false,
                'message' => 'Stock tidak ditemukan atau tidak sesuai pembelian ini.',
            ], 404);
        }

        $stock->update([
            'expired_at' => !empty($validated['expired_at']) ? $validated['expired_at'] : null,
        ]);

        return response()->json([
            'success'    => true,
            'expired_at' => $stock->expired_at?->format('Y-m-d'),
            'message'    => 'Tanggal expired berhasil diperbarui.',
        ]);
    }

    private function updateStock($request, $pembelian)
    {
        if ($pembelian->is_published) {
            foreach ($request as $productData) {
                $product = Product::find($productData->product_id);
                if ($product->is_serialized && ! empty($productData->serial_numbers)) {
                    $serialNumbers = is_array($productData->serial_numbers)
                        ? $productData->serial_numbers
                        : explode("\n", trim($productData->serial_numbers));

                    foreach ($serialNumbers as $serial) {
                        $serial = trim($serial);
                        if (! empty($serial)) {
                            // Create market stock
                            Stock::updateOrCreate(
                                [
                                    'pembelian_id' => $pembelian->id,
                                    'product_id' => $productData->product_id,
                                    'serial_number' => $serial
                                ],
                                [
                                    'harga_beli' => (int) str_replace(',', '', $productData->harga_beli),
                                    'qty' => 1, // Always 1 for serialized items
                                    'subtotal' => (int) str_replace(',', '', $productData->harga_beli),
                                    'expired_at' => $productData->expired_at ?? null,
                                    'condition' => 'new',
                                ]
                            );

                            // Decrease StockPembelian quantity
                            StockPembelian::where([
                                'pembelian_id' => $pembelian->id,
                                'product_id' => $productData->product_id,
                                'serial_number' => $serial
                            ])->decrement('qty', 1);
                        }
                    }
                } else {
                    // For bulk items
                    $qty = (int) $productData->qty;

                    // Create market stock
                    Stock::updateOrCreate(
                        ['pembelian_id' => $pembelian->id, 'product_id' => $productData->product_id],
                        [
                            'harga_beli' => (int) str_replace(',', '', $productData->harga_beli),
                            'qty' => $qty,
                            'subtotal' => (int) $productData->subtotal,
                            'expired_at' => $productData->expired_at ?? null,
                            'condition' => 'new',
                        ]
                    );

                    // Decrease StockPembelian quantity
                    StockPembelian::where([
                        'pembelian_id' => $pembelian->id,
                        'product_id' => $productData->product_id
                    ])->decrement('qty', $qty);
                }

                $product->update(['harga_beli' => (int) str_replace(',', '', $productData->harga_beli)]);
            }
        } else {
            if (isset($request->product)) {
                foreach ($request->product as $productData) {
                    $product = Product::find($productData['product_id']);
                    // Process serial numbers for PembelianProduct
                    $serialNumbers = null;
                    if (isset($productData['serial_numbers']) && ! empty($productData['serial_numbers'])) {
                        $serialNumbers = is_array($productData['serial_numbers'])
                            ? $productData['serial_numbers']
                            : array_filter(array_map('trim', explode("\n", $productData['serial_numbers'])));
                    }

                    PembelianProduct::updateOrCreate(
                        ['pembelian_id' => $pembelian->id, 'product_id' => $productData['product_id']],
                        [
                            'harga_beli' => (int) str_replace(',', '', $productData['harga_beli']),
                            'qty' => (int) $productData['qty'],
                            'subtotal' => (int) $productData['subtotal'],
                            // 'expired_at' => $productData['expired'] ?? null,
                            'serial_numbers' => $serialNumbers,
                        ]
                    );

                    // Add StockPembelian for non-published products
                    if (Product::find($productData['product_id'])->is_serialized && ! empty($serialNumbers)) {
                        foreach ($serialNumbers as $serial) {
                            StockPembelian::updateOrCreate(
                                [
                                    'pembelian_id' => $pembelian->id,
                                    'product_id' => $productData['product_id'],
                                    'serial_number' => $serial
                                ],
                                [
                                    'harga_beli' => (int) str_replace(',', '', $productData['harga_beli']),
                                    'qty' => 1,
                                    'subtotal' => (int) str_replace(',', '', $productData['harga_beli']),
                                    // 'expired_at' => $productData['expired'] ?? null,
                                    'condition' => 'new',
                                    'status' => 'available',
                                ]
                            );
                        }
                    } else {
                        StockPembelian::updateOrCreate(
                            ['pembelian_id' => $pembelian->id, 'product_id' => $productData['product_id']],
                            [
                                'harga_beli' => (int) str_replace(',', '', $productData['harga_beli']),
                                'qty' => (int) $productData['qty'],
                                'subtotal' => (int) $productData['subtotal'],
                                // 'expired_at' => $productData['expired'] ?? null,
                                'condition' => 'new',
                                'status' => 'available',
                            ]
                        );
                    }

                    $product->update(['harga_beli' => (int) str_replace(',', '', $productData['harga_beli'])]);
                }
            }
        }
    }

    public function destroy(Pembelian $pembelian)
    {
        if (auth()->user()->role === 'owner' || ! $pembelian->canBeEditedBy(auth()->user())) {
            return redirect()->route('pembelian.index')
                ->with('toast_error', 'PO ini belum bisa dihapus.');
        }

        $pembelian->stocks()->delete();
        $pembelian->delete();

        return redirect(route('pembelian.index'))->with('toast_success', 'Berhasil Menghapus Data!');
    }

    public function stockDestroy($id)
    {
        $pembelianProduct = PembelianProduct::find($id);
        if (! $pembelianProduct) {
            return redirect()->route('pembelian.index')->with('toast_error', 'Item PO tidak ditemukan.');
        }

        $pembelian = $pembelianProduct->pembelian;
        if (! $pembelian->canBeEditedBy(auth()->user())) {
            return redirect()->route('pembelian.index')
                ->with('toast_error', 'PO ini belum bisa diedit.');
        }

        $pembelianProduct->delete();

        $pembelian->update(
            ['total' => $pembelian->pembelianProducts->sum('subtotal')]
        );

        return redirect()->back()->with('toast_success', 'Berhasil Menghapus Data!');
    }

    public function editPembayaran(Pembelian $pembelian)
    {
        $pembelian->load(['supplier', 'pembelianProducts.product', 'pembelianTransaction']);
        $title = 'Edit Pembayaran Pembelian';
        $paymentHistory = $pembelian->pembelianTransaction?->payment_history ?? [];

        return view('pembelians.pembayaran-edit', compact('pembelian', 'title', 'paymentHistory'));
    }

    public function updatePembayaran(Request $request, Pembelian $pembelian)
    {
        if (auth()->user()->role === 'owner') {
            abort(403);
        }

        $currentAmount = $pembelian->pembelianTransaction?->amount ?? 0;
        $maxAmount = $pembelian->total - $currentAmount;

        $request->validate([
            'payment_date'      => 'required|date',
            'payment_method'    => 'required|in:cash,bank_transfer,giro_cek,lainnya',
            'payment_reference' => 'nullable|string',
            'amount'            => 'required|numeric|min:0|max:' . $maxAmount,
            'notes'             => 'nullable|string',
            'status'            => 'required|in:unpaid,paid,partial',
            'bukti_transfer'    => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ], [
            'payment_date.required' => 'Tanggal pembayaran harus diisi.',
            'payment_date.date' => 'Tanggal pembayaran harus berupa tanggal yang valid.',
            'payment_method.required' => 'Metode pembayaran harus dipilih.',
            'payment_method.in' => 'Metode pembayaran harus dipilih antara cash, bank transfer, giro/cek, atau lainnya.',
            'payment_reference.string' => 'Referensi pembayaran harus berupa teks.',
            'amount.required' => 'Jumlah pembayaran harus diisi.',
            'amount.numeric' => 'Jumlah pembayaran harus berupa angka.',
            'amount.min' => 'Jumlah pembayaran minimal 0.',
            'amount.max' => 'Jumlah pembayaran maksimal :max.',
            'notes.string' => 'Catatan harus berupa teks.',
            'status.required' => 'Status pembayaran harus diisi.',
            'status.in' => 'Status pembayaran harus dipilih antara unpaid, paid, atau partial.',
            'bukti_transfer.file' => 'Bukti transfer harus berupa file.',
            'bukti_transfer.mimes' => 'Bukti transfer harus berupa file dengan format jpg, jpeg, png, atau pdf.',
            'bukti_transfer.max' => 'Ukuran bukti transfer maksimal 2MB.',
        ]);

        DB::beginTransaction();
        try {
            $previousAmount = $pembelian->pembelianTransaction?->amount ?? 0;
            $newTotalAmount = $previousAmount + $request->amount;

            // Handle file upload
            $buktiPath = null;
            if ($request->hasFile('bukti_transfer')) {
                $file = $request->file('bukti_transfer');
                $filename = 'bukti_' . time() . '_' . $pembelian->id . '.' . $file->getClientOriginalExtension();
                $buktiPath = $file->storeAs('bukti_transfer', $filename, 'public');
            }

            if ($pembelian->pembelianTransaction) {
                // Update existing transaction
                $paymentHistory = $pembelian->pembelianTransaction->payment_history ?? [];

                if ($request->amount > 0) {
                    $paymentHistory[] = [
                        'payment_date'      => $request->payment_date,
                        'amount'            => $request->amount,
                        'payment_method'    => $request->payment_method,
                        'payment_reference' => $request->payment_reference,
                        'bukti_transfer'    => $buktiPath ?? null,
                        'notes'             => $request->notes,
                        'created_at'        => now()->toDateTimeString(),
                    ];
                }

                $transactionData = [
                    'payment_date'      => $request->payment_date,
                    'payment_method'    => $request->payment_method,
                    'payment_reference' => $request->payment_reference,
                    'amount'            => $newTotalAmount,
                    'payment_history'   => $paymentHistory,
                    'notes'             => $request->notes,
                    'status'            => $request->status,
                ];

                if ($buktiPath) {
                    $transactionData['bukti_transfer'] = $buktiPath;
                }

                $pembelian->pembelianTransaction->update($transactionData);
            } else {
                // Create new transaction
                $paymentHistory = [];
                if ($request->amount > 0) {
                    $paymentHistory[] = [
                        'payment_date'      => $request->payment_date,
                        'amount'            => $request->amount,
                        'payment_method'    => $request->payment_method,
                        'payment_reference' => $request->payment_reference,
                        'bukti_transfer'    => $buktiPath,
                        'notes'             => $request->notes,
                        'created_at'        => now()->toDateTimeString(),
                    ];
                }

                $transactionData = [
                    'payment_date'      => $request->payment_date,
                    'payment_method'    => $request->payment_method,
                    'payment_reference' => $request->payment_reference,
                    'amount'            => $request->amount,
                    'payment_history'   => $paymentHistory,
                    'notes'             => $request->notes,
                    'status'            => $request->status,
                    'bukti_transfer'    => $buktiPath,
                ];

                $pembelian->pembelianTransaction()->create($transactionData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pembayaran berhasil disimpan'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function approveOwner(Request $request, Pembelian $pembelian)
    {
        if (! in_array(auth()->user()->role, ['owner', 'superadmin'])) {
            abort(403);
        }

        $request->validate([
            'owner_approval_note' => 'nullable|string',
        ]);

        $pembelian->update([
            'owner_approval_status' => 'approved',
            'owner_approved_by' => auth()->id(),
            'owner_approved_at' => now(),
            'owner_approval_note' => $request->owner_approval_note,
        ]);

        return redirect()->back()->with('toast_success', 'PO berhasil di-ACC owner.');
    }

    public function rejectOwner(Request $request, Pembelian $pembelian)
    {
        if (! in_array(auth()->user()->role, ['owner', 'superadmin'])) {
            abort(403);
        }

        $request->validate([
            'owner_approval_note' => 'nullable|string',
        ]);

        $pembelian->update([
            'owner_approval_status' => 'rejected',
            'owner_approved_by' => auth()->id(),
            'owner_approved_at' => now(),
            'owner_approval_note' => $request->owner_approval_note,
            'is_published' => false,
        ]);

        return redirect()->back()->with('toast_success', 'PO ditolak owner dan dikembalikan ke draft revisi.');
    }

    private function generatePoCode(int $supplierId): string
    {
        return DB::transaction(function () use ($supplierId) {
            $supplier = Supplier::find($supplierId);
            $supplierName = preg_replace('/[^A-Za-z0-9]/', '', $supplier->name ?? 'SUPPLIER');
            $today = now()->format('Ymd');

            $countToday = Pembelian::where('supplier_id', $supplierId)
                ->whereDate('created_at', now()->toDateString())
                ->lockForUpdate()
                ->count();

            $noUrut = str_pad($countToday + 1, 3, '0', STR_PAD_LEFT);

            return "{$supplierName}_{$today}_{$noUrut}";
        });
    }
}
