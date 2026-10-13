<?php

namespace App\Http\Controllers;

use App\Models\OutletPurchase;
use App\Models\OutletPurchaseItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\OutletStockService;
use App\Support\IndonesianNumber;
use App\Support\OutletAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

            $statusHtml = $purchase->isPending()
                ? '<span class="label label-warning">Menunggu Penerimaan</span>'
                : '<span class="label label-success">Completed</span>';

            $aksiHtml = '<a href="' . route('outlet-purchases.show', $purchase) . '" class="btn btn-xs btn-info">Lihat</a>';
            if ($purchase->isPending()) {
                $aksiHtml .= ' <a href="' . route('outlet-purchases.edit', $purchase) . '" class="btn btn-xs btn-warning">Edit</a>';
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

    public function create(Request $request)
    {
        $this->ensurePurchaseAccess();

        return view('outlet-purchases.create', [
            'purchase' => null,
            'outlets' => OutletAccess::outlets(),
            'selectedOutletId' => OutletAccess::id($request, false),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'initialItems' => $this->initialItems(null),
        ]);
    }

    /**
     * Simpan pembelian dengan status pending. Stock toko BELUM bertambah:
     * stok baru masuk setelah penerimaan barang diselesaikan (receive()).
     */
    public function store(Request $request)
    {
        $this->ensurePurchaseAccess();
        $this->validatePayload($request);
        $outletId = OutletAccess::id($request);

        $purchase = DB::transaction(function () use ($request, $outletId) {
            $purchase = OutletPurchase::create([
                'code' => 'POUT-' . now()->format('YmdHis') . '-' . random_int(10, 99),
                'outlet_id' => $outletId,
                'supplier_id' => $request->supplier_id,
                'created_by' => auth()->id(),
                'purchase_date' => $request->purchase_date,
                'invoice_number' => $request->invoice_number,
                'paid_amount' => $request->input('paid_amount', 0),
                'payment_method' => $request->payment_method,
                'status' => OutletPurchase::STATUS_PENDING,
                'notes' => $request->notes,
            ]);

            $this->syncItems($purchase, $request->items);

            return $purchase;
        });

        return redirect()
            ->route('outlet-purchases.index')
            ->with('toast_success', "Belanja langsung {$purchase->code} tersimpan. Stock toko bertambah setelah penerimaan barang diselesaikan.");
    }

    public function edit(OutletPurchase $outletPurchase)
    {
        $this->ensurePurchaseAccess();
        $this->authorizeOutlet($outletPurchase);

        if (! $outletPurchase->isPending()) {
            return redirect()
                ->route('outlet-purchases.show', $outletPurchase)
                ->with('toast_error', 'Pembelian ini sudah completed dan tidak bisa diedit lagi.');
        }

        $outletPurchase->load('items.product');

        return view('outlet-purchases.create', [
            'purchase' => $outletPurchase,
            'outlets' => OutletAccess::outlets(),
            'selectedOutletId' => $outletPurchase->outlet_id,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'initialItems' => $this->initialItems($outletPurchase),
        ]);
    }

    public function update(Request $request, OutletPurchase $outletPurchase)
    {
        $this->ensurePurchaseAccess();
        $this->authorizeOutlet($outletPurchase);
        $this->validatePayload($request);
        $outletId = OutletAccess::id($request);

        try {
            DB::transaction(function () use ($request, $outletPurchase, $outletId) {
                $purchase = OutletPurchase::lockForUpdate()->findOrFail($outletPurchase->id);
                if (! $purchase->isPending()) {
                    throw new RuntimeException('Pembelian ini sudah completed dan tidak bisa diedit lagi.');
                }

                $purchase->update([
                    'outlet_id' => $outletId,
                    'supplier_id' => $request->supplier_id,
                    'purchase_date' => $request->purchase_date,
                    'invoice_number' => $request->invoice_number,
                    'notes' => $request->notes,
                ]);

                $this->syncItems($purchase, $request->items);
            });
        } catch (RuntimeException $e) {
            return redirect()->route('outlet-purchases.show', $outletPurchase)->with('toast_error', $e->getMessage());
        }

        return redirect()
            ->route('outlet-purchases.index')
            ->with('toast_success', "Belanja langsung {$outletPurchase->code} berhasil diperbarui.");
    }

    /**
     * Untuk pembelian pending, halaman ini adalah halaman penerimaan barang.
     * Untuk pembelian completed, halaman ini hanya detail (read-only).
     */
    public function show(OutletPurchase $outletPurchase)
    {
        $this->ensurePurchaseAccess();
        $this->authorizeOutlet($outletPurchase);

        return view('outlet-purchases.show', [
            'purchase' => $outletPurchase->load(['outlet', 'supplier', 'creator', 'receiver', 'items.product']),
        ]);
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
        ], [
            'items.required' => 'Data item penerimaan wajib diisi.',
            'items.*.qty_received.integer' => 'Qty diterima harus berupa angka bulat.',
            'items.*.qty_received.min' => 'Qty diterima tidak boleh negatif.',
        ]);

        $itemData = $request->input('items', []);

        try {
            DB::transaction(function () use ($outletPurchase, $itemData, $stockService) {
                // Cegah terima ganda (stok & movement akan tercatat dua kali)
                $purchase = OutletPurchase::lockForUpdate()->findOrFail($outletPurchase->id);
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

                    $ownerStockId = null;
                    if ($qtyReceived > 0) {
                        $ownerStock = $stockService->receive(
                            (int) $purchase->outlet_id,
                            (int) $item->product_id,
                            $qtyReceived,
                            (float) $item->harga_beli,
                            [
                                'expired_at' => $item->expired_at,
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
     * Validasi form (dipakai store & update). Angka format Indonesia dikonversi dulu.
     */
    private function validatePayload(Request $request): void
    {
        $request->merge([
            'paid_amount' => IndonesianNumber::parse($request->input('paid_amount')),
            'items' => collect($request->input('items', []))->map(function ($item) {
                $item['harga_beli'] = IndonesianNumber::parse($item['harga_beli'] ?? null);

                return $item;
            })->all(),
        ]);

        $request->validate([
            'outlet_id' => 'required|integer|exists:outlets,id',
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'purchase_date' => 'required|date',
            'invoice_number' => 'nullable|string|max:100',
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.harga_beli' => 'required|numeric|min:0',
            'items.*.batch_number' => 'nullable|string|max:100',
            'items.*.expired_at' => 'nullable|date',
        ]);
    }

    /**
     * Ganti seluruh item pembelian pending (belum ada stok yang masuk, jadi aman dibuat ulang).
     */
    private function syncItems(OutletPurchase $purchase, array $items): void
    {
        $purchase->items()->delete();
        $subtotal = 0;
        $position = 0;

        foreach ($items as $item) {
            $position++;
            $qty = (int) $item['qty'];
            $hargaBeli = (float) $item['harga_beli'];
            $lineSubtotal = $qty * $hargaBeli;

            OutletPurchaseItem::create([
                'outlet_purchase_id' => $purchase->id,
                'product_id' => (int) $item['product_id'],
                'owner_stock_id' => null,
                'qty' => $qty,
                'qty_received' => null,
                'harga_beli' => $hargaBeli,
                'subtotal' => $lineSubtotal,
                'batch_number' => ($item['batch_number'] ?? null) ?: $purchase->code . '-' . $position,
                'expired_at' => $item['expired_at'] ?? null,
            ]);
            $subtotal += $lineSubtotal;
        }

        $purchase->update(['subtotal' => $subtotal]);
    }

    /**
     * Baris item awal untuk form: input lama (validasi gagal) atau item pembelian yang diedit.
     */
    private function initialItems(?OutletPurchase $purchase): array
    {
        $old = old('items');

        if (is_array($old)) {
            $products = Product::whereIn('id', collect($old)->pluck('product_id')->filter())
                ->get(['id', 'code', 'name'])->keyBy('id');

            return collect($old)->values()->map(fn ($item) => [
                'product_id' => $item['product_id'] ?? null,
                'label' => isset($products[$item['product_id'] ?? null])
                    ? $products[$item['product_id']]->code . ' — ' . $products[$item['product_id']]->name
                    : null,
                'qty' => $item['qty'] ?? 1,
                'harga_beli' => $item['harga_beli'] ?? '',
                'expired_at' => $item['expired_at'] ?? '',
                'batch_number' => $item['batch_number'] ?? null,
            ])->all();
        }

        if (! $purchase) {
            return [];
        }

        return $purchase->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'label' => $item->product ? $item->product->code . ' — ' . $item->product->name : null,
            'qty' => $item->qty,
            'harga_beli' => $item->harga_beli,
            'expired_at' => $item->expired_at?->format('Y-m-d') ?? '',
            'batch_number' => $item->batch_number,
        ])->all();
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