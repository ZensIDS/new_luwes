<?php

namespace App\Http\Controllers;

use App\Exports\StockOpnameTemplateExport;
use App\Models\Pembelian;
use App\Models\Product;
use App\Models\OwnerStock;
use App\Models\RefundPembelian;
use App\Models\RefundPembelianItem;
use App\Models\Stock;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;

class StockController extends Controller
{
    // NEW INDEX STOCK WITHOUT SKU
    public function index()
    {
        // Halaman awal cuma butuh opsi filter (query ringan), TIDAK ambil data stock sama sekali.
        $kategoriOptions = \App\Models\Category::orderBy('name')->pluck('name');

        $lokasiOptions = Product::whereNotNull('lokasi')
            ->where('lokasi', '!=', '')
            ->distinct()
            ->orderBy('lokasi')
            ->pluck('lokasi');

        $supplierOptions = \App\Models\Supplier::whereHas('pembelians.stocks')
            ->orderBy('name')
            ->get(['id', 'name']);

        $statusOptions = Stock::whereNotNull('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        return view('stocks.index', [
            'kategoriOptions' => $kategoriOptions,
            'lokasiOptions'   => $lokasiOptions,
            'supplierOptions' => $supplierOptions,
            'statusOptions'   => $statusOptions,
        ]);
    }

    public function getIndexData(Request $request)
    {
        $draw        = (int) $request->input('draw');
        $start       = max((int) $request->input('start', 0), 0);
        $length      = (int) $request->input('length', 25);
        $length      = $length > 0 ? min($length, 100) : 25; // guard, jangan biarkan client minta ribuan per page
        $searchValue = trim((string) ($request->input('search.value', '')));
        $kategori    = $request->input('kategori');
        $lokasi      = $request->input('lokasi');
        $supplier    = $request->input('supplier_id');
        $status      = $request->input('status');

        $orderColIndex = (int) $request->input('order.0.column', 2);
        $orderDir      = strtolower($request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        // Mapping index kolom DataTables (urutan kolom di stocks/index.blade.php) -> ekspresi SQL.
        // 0 No | 1 Code | 2 Product | 3 Konversi | 4 Harga Beli | 5 Stock Outlet |
        // 6 Qty Reserved | 7 Qty Warehouse | 8 Created | 9 Expired | 10 Status | 11 Action
        // (Mapping lama masih pakai urutan kolom sebelum kolom SKU dihapus, sehingga
        //  sort di kolom Qty Warehouse malah mengurutkan berdasarkan qty_reserved.)
        $sortableColumns = [
            1  => 'products.code',
            2  => 'products.name',
            3  => 'g.total_qty',
            4  => 's.harga_beli',
            5  => 'COALESCE(o.total_owner_qty, 0)',
            6  => 'g.total_reserved',
            7  => 'g.total_qty',
            8  => 's.created_at',
            9  => 's.expired_at',
            10 => 's.status',
        ];
        $orderBy = $sortableColumns[$orderColIndex] ?? 'products.name';

        // ==== SUMBER KEBENARAN STOK: SUM(stocks.qty) per produk (semua batch/SKU) ====
        // Angka ini sama dengan Product::withStockTotals() dan Product::total_stock.
        //  - total_qty      = SUM(qty)          -> stok fisik gudang (semua batch, bukan cuma 1 baris)
        //  - total_reserved = SUM(qty_reserved) -> reservasi (sudah termasuk di total_qty)
        //  - last_stock_id  = baris "representatif" (batch terakhir yang MASIH ada isinya) untuk
        //                     kolom harga/created/expired/status
        // Produk dengan total 0 disembunyikan; total negatif sengaja tetap muncul supaya anomali kelihatan.
        $grouped = Stock::query()
            ->select('product_id')
            ->selectRaw('COALESCE(MAX(CASE WHEN qty > 0 THEN id END), MAX(id)) as last_stock_id')
            ->selectRaw('SUM(qty) as total_qty')
            ->selectRaw('SUM(qty_reserved) as total_reserved')
            ->groupBy('product_id')
            ->havingRaw('SUM(qty) <> 0')
            ->toBase(); // toBase() = global scope SoftDeletes ikut diterapkan

        // Stok di outlet: SUM semua owner_stocks per produk (bukan hanya dari 1 baris stock)
        $ownerTotals = DB::table('owner_stocks')
            ->whereNull('deleted_at')
            ->select('product_id')
            ->selectRaw('SUM(qty) as total_owner_qty')
            ->groupBy('product_id');

        // recordsTotal: total produk (tanpa filter search/kategori/lokasi) yang punya stok
        $recordsTotal = DB::query()->fromSub($grouped, 'g')->count();

        // Join ke products, categories, dan stocks (baris representatif) langsung di SQL
        // supaya search/filter/sort semuanya jalan di database.
        // leftJoin ke pembelians + suppliers supaya search bisa menjangkau nama supplier,
        // dan stock tanpa pembelian_id (mis. stok opname manual) tidak ikut hilang.
        $base = DB::query()->fromSub($grouped, 'g')
            ->join('products', 'products.id', '=', 'g.product_id')
            ->whereNull('products.deleted_at')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->join('stocks as s', 's.id', '=', 'g.last_stock_id')
            ->leftJoinSub($ownerTotals, 'o', 'o.product_id', '=', 'g.product_id')
            ->leftJoin('pembelians', 'pembelians.id', '=', 's.pembelian_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'pembelians.supplier_id');

        if ($kategori) {
            $base->where('categories.name', $kategori);
        }

        if ($lokasi) {
            $base->where('products.lokasi', $lokasi);
        }

        if ($supplier) {
            $base->where('pembelians.supplier_id', $supplier);
        }

        if ($status) {
            $base->where('s.status', $status);
        }

        // ==== SEARCH: meniru "smart search" DataTables, tapi tetap ringan ====
        // Input dipecah per kata; SEMUA kata harus ketemu (AND) di salah satu kolom (OR),
        // jadi "wing surya" tetap match "Wings Surya".
        if ($searchValue !== '') {
            $searchWords = preg_split('/\s+/', $searchValue, -1, PREG_SPLIT_NO_EMPTY);
            $searchWords = array_slice($searchWords, 0, 5); // batasi biar tidak disalahgunakan

            $base->where(function ($q) use ($searchWords) {
                foreach ($searchWords as $word) {
                    $q->where(function ($qw) use ($word) {
                        $qw->where('products.name', 'like', "%{$word}%")
                            ->orWhere('products.code', 'like', "%{$word}%")
                            ->orWhere('s.sku', 'like', "%{$word}%")
                            ->orWhere('s.serial_number', 'like', "%{$word}%")
                            ->orWhere('suppliers.name', 'like', "%{$word}%");
                    });
                }
            });
        }

        $recordsFiltered = (clone $base)->count();

        $rows = $base
            ->orderByRaw("{$orderBy} {$orderDir}") // $orderBy dari whitelist, $orderDir sudah divalidasi
            // Tie-breaker unik (g.product_id unik per baris) supaya paging tidak menggandakan/melewatkan baris.
            ->orderBy('g.product_id', $orderDir)
            ->offset($start)
            ->limit($length)
            ->get([
                'g.product_id',
                'g.total_qty',
                'g.total_reserved',
                DB::raw('COALESCE(o.total_owner_qty, 0) as total_owner_qty'),
                's.id as stock_id',
                's.sku',
                's.serial_number',
                's.harga_beli',
                's.created_at',
                's.expired_at',
                's.status',
                's.pembelian_id',
                'products.name as product_name',
                'products.code as product_code',
                'products.konversi_qty',
                'products.satuan_besar',
                'products.satuan',
                'categories.name as category_name',
                'products.lokasi',
            ]);

        // Data yang belum ikut di-join di atas (ownerStock.owner):
        // ambil terpisah, tapi HANYA untuk baris di halaman ini (max 100 row), bukan semua data.
        $ownerStockMap = OwnerStock::whereIn('product_id', $rows->pluck('product_id'))
            ->where('qty', '>', 0)
            ->where(function ($query) {
                $query->whereNull('expired_at')->orWhereDate('expired_at', '>=', today());
            })
            ->selectRaw('product_id, SUM(qty) as total_qty')
            ->groupBy('product_id')
            ->pluck('total_qty', 'product_id');

        $pembelianIds = $rows->pluck('pembelian_id')->filter()->unique()->all();
        $supplierMap = \App\Models\Pembelian::whereIn('id', $pembelianIds)
            ->with('supplier:id,name')
            ->get()
            ->keyBy('id')
            ->map(fn($p) => $p->supplier?->name ?? '-');

        $data = $rows->map(function ($row) use ($ownerStockMap, $supplierMap) {
            $konversiQty  = $row->konversi_qty;
            $satuanBesar  = $row->satuan_besar;
            $satuan       = $row->satuan ?? 'PCS';

            $konversiDisplay = function ($qty) use ($konversiQty, $satuanBesar, $satuan) {
                $qty = (int) $qty;
                if (! $konversiQty || ! $satuanBesar) {
                    return null;
                }
                $boxes = intdiv($qty, $konversiQty);
                $rem   = $qty % $konversiQty;
                if ($rem === 0) return "{$boxes} {$satuanBesar}";
                if ($boxes > 0) return "{$boxes} {$satuanBesar} {$rem} {$satuan}";
                return "{$qty} {$satuan}";
            };

            $stockOutlet = $ownerStockMap->get($row->product_id, 0);

            return [
                'product_id'     => $row->product_id,
                'stock_id'       => $row->stock_id,
                'code'           => $row->serial_number ?: $row->product_code,
                'product_name'   => $row->product_name,
                'konversi_qty'   => $row->konversi_qty,
                'satuan_besar'   => $row->satuan_besar,
                'satuan'         => $row->satuan ?? 'PCS',
                'harga_beli'     => (float) $row->harga_beli,
                'stock_outlet'   => (int) $stockOutlet,
                'qty_reserved'   => (int) $row->total_reserved,
                'qty_warehouse'  => (int) $row->total_qty,
                'created_at'     => $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('h:i a / d-M-Y') : '-',
                'expired_at'     => $row->expired_at ? \Carbon\Carbon::parse($row->expired_at)->format('d-M-Y') : '-',
                'status'         => $row->status,
                'supplier'       => $row->pembelian_id ? ($supplierMap->get($row->pembelian_id) ?? '-') : '-',
            ];
        });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data->values(),
        ]);
    }

    // OLD INDEX STOCK WITH SKU
    // public function index()
    // {
    //     return view('stocks.index', [
    //         'stocks' => Stock::with([
    //             'product.category',
    //             'pembelian.supplier',
    //             'ownerStock.owner',
    //         ])
    //             ->orderBy('created_at', 'desc')
    //             ->orderBy('expired_at')
    //             ->get()
    //     ]);
    // }

    public function show(Stock $stock)
    {
        $stock->delete();

        $total = $stock->pembelian->stocks->sum('subtotal');
        $stock->pembelian->update(['total' => $total]);

        return redirect()->back()->with('toast_success', 'Berhasil Menghapus Data!');
    }

    public function destroy(Stock $stock)
    {
        dd(
            'destory Stock',
            $stock->toArray(),
            $stock->pembelian->toArray()
        );
        // $stock->delete();

        return redirect()->back()->with('toast_success', 'Berhasil Menghapus Data!');
    }

    public function history(Stock $stock)
    {
        $activities = Activity::forSubject($stock)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($activity) {
                return [
                    'source'     => 'activity',
                    'date'       => $activity->created_at->format('d M Y H:i'),
                    'user'       => $activity->causer?->name ?? 'System',
                    'event'      => $activity->event,
                    'properties' => $activity->properties,
                ];
            });

        $movements = StockMovement::where('product_id', $stock->product_id)
            ->whereNull('owner_id')
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($movement) {
                return [
                    'source'  => 'movement',
                    'date'    => $movement->created_at->format('d M Y H:i'),
                    'user'    => $movement->user?->name ?? 'System',
                    'type'    => $movement->type,
                    'qty_in'  => $movement->qty_in,
                    'qty_out' => $movement->qty_out,
                    'balance' => $movement->balance,
                    'notes'   => $movement->notes,
                ];
            });

        return response()->json(['success' => true, 'activities' => $activities, 'movements' => $movements]);
    }

    //kartu
    public function kartu(Request $request)
    {
        $kategoriOptions = \App\Models\Category::orderBy('name')->pluck('name');
        $lokasiOptions = Product::whereNotNull('lokasi')
            ->where('lokasi', '!=', '')
            ->distinct()
            ->orderBy('lokasi')
            ->pluck('lokasi');
        $supplierOptions = \App\Models\Supplier::whereHas('pembelians.stocks', fn ($query) => $query->whereNotNull('sku'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('stocks.kartu', compact('kategoriOptions', 'lokasiOptions', 'supplierOptions'));
    }

    public function searchStock(Request $request)
    {
        $search = trim((string) $request->get('q', ''));
        $kategori = $request->get('kategori');
        $lokasi = $request->get('lokasi');
        $supplier = $request->get('supplier_id');
        $page = max((int) $request->get('page', 1), 1);
        $perPage = 20;

        // Semua produk yang punya baris stok (termasuk batch tanpa SKU), supaya
        // totalnya sama dengan menu Stok / Produk.
        $query = Product::query()->whereHas('stocks');

        if ($kategori) {
            $query->whereHas('category', fn ($q) => $q->where('name', $kategori));
        }

        if ($lokasi) {
            $query->where('lokasi', $lokasi);
        }

        if ($supplier) {
            $query->whereHas('stocks.pembelian', fn ($q) => $q->where('supplier_id', $supplier));
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "{$search}%");
            });
        }

        $total = (clone $query)->count();

        $products = $query
            ->orderBy('name')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function ($product) {
                return [
                    'id'   => $product->id,
                    'text' => "{$product->name} | {$product->code}",
                ];
            });

        return response()->json([
            'results' => $products,
            'pagination' => [
                'more' => ($page * $perPage) < $total,
            ],
        ]);
    }

    public function getKartuData(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id'
        ], [
            'product_id.required' => 'Produk harus dipilih.',
            'product_id.exists' => 'Produk yang dipilih tidak ditemukan.',
        ]);

        $product = Product::find($request->product_id);

        if (! $product) {
            return response()->json(['error' => 'Produk tidak ditemukan'], 404);
        }

        return response()->json(app(\App\Services\KartuStokBuilder::class)->build($product));
    }

    //opname
    public function opname(Request $request)
    {
        $kategoriOptions = \App\Models\Category::orderBy('name')->pluck('name');
        $lokasiOptions = Product::whereNotNull('lokasi')
            ->where('lokasi', '!=', '')
            ->distinct()
            ->orderBy('lokasi')
            ->pluck('lokasi');

        $supplierOptions = \App\Models\Supplier::orderBy('name')
            ->whereHas('pembelians.stocks', fn($q) => $q->where('qty', '>=', 0)->whereNotNull('sku'))
            ->get(['id', 'name']);

        return view('stocks.opname', [
            'kategoriOptions' => $kategoriOptions,
            'lokasiOptions'   => $lokasiOptions,
            'supplierOptions' => $supplierOptions,
        ]);
    }

    public function getOpnameData(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'kategori' => 'nullable|string',
            'lokasi' => 'nullable|string',
        ]);

        $query = Stock::with('product', 'pembelian.supplier')
            ->where('qty', '>=', 0)
            ->whereNotNull('sku')
            ->orderBy('product_id')
            ->orderBy('sku');

        $query->whereHas('pembelian', fn ($q) => $q->where('supplier_id', $request->input('supplier_id')));

        if ($lokasi = $request->input('lokasi')) {
            $query->whereHas('product', fn($q) => $q->where('lokasi', $lokasi));
        }

        if ($kategori = $request->input('kategori')) {
            $query->whereHas('product.category', fn($q) => $q->where('name', $kategori));
        }

        $stocks = $query->get()
            ->filter(fn($stock) => $stock->product !== null) // produk sudah dihapus -> skip dari opname
            ->map(function ($stock) {
                return [
                    'id'            => $stock->id,
                    'product_id'    => $stock->product_id,
                    'product_name'  => $stock->product->name,
                    'product_code'  => $stock->product->code,
                    'sku'           => $stock->sku,
                    'satuan'        => $stock->product->satuan ?? 'pcs',
                    'qty'           => $stock->qty,
                    'qty_reserved'  => $stock->qty_reserved,
                    'qty_available' => $stock->qty_available,
                    'keterangan'    => $stock->adjustment?->keterangan ?? '',
                    'supplier'      => $stock->pembelian?->supplier?->name ?? '-',
                    'kategori'      => $stock->product->category?->name ?? '-',
                    'lokasi'        => $stock->product->lokasi ?? '-',
                ];
            });

        return response()->json(['stocks' => $stocks->values()]);
    }

    public function saveOpname(Request $request)
    {
        $request->validate([
            'adjustment_date'           => 'required|date',
            'items'                     => 'required|array',
            'items.*.stock_id'          => 'required|exists:stocks,id',
            'items.*.selisih'           => 'required|numeric',
            'items.*.system_qty'        => 'nullable|numeric',
            'items.*.physical_qty'      => 'nullable|numeric',
            'items.*.keterangan'        => 'nullable|string',
        ], [
            'adjustment_date.required'  => 'Tanggal penyesuaian harus diisi.',
            'adjustment_date.date'      => 'Tanggal penyesuaian harus berupa tanggal yang valid.',
            'items.required'            => 'Item harus diisi.',
            'items.array'               => 'Item harus berupa array.',
            'items.*.stock_id.required' => 'Stok harus dipilih.',
            'items.*.stock_id.exists'   => 'Stok yang dipilih tidak ditemukan.',
            'items.*.selisih.required'  => 'Selisih harus diisi.',
            'items.*.selisih.numeric'   => 'Selisih harus berupa angka.',
            'items.*.keterangan.string' => 'Keterangan harus berupa teks.',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->items as $item) {
                if ($item['selisih'] != 0) {
                    $stock = Stock::find($item['stock_id']);

                    // Create adjustment record
                    $savedAdj = StockAdjustment::create([
                        'adjustment_date' => $request->adjustment_date,
                        'product_id'      => $stock->product_id,
                        'stock_id'        => $stock->id,
                        'sku'             => $stock->sku,
                        'quantity'        => $item['selisih'],
                        'system_qty'      => $item['system_qty'] ?? $stock->qty,
                        'physical_qty'    => $item['physical_qty'] ?? ($stock->qty + $item['selisih']),
                        'reason'          => $item['keterangan'] ?? null,
                        'status'          => 'Selesai',
                        'keterangan'      => $item['keterangan'] ?? null,
                    ]);

                    $newQty = $stock->qty + $item['selisih'];
                    $stock->update(['qty' => $newQty]);

                    // Log movement
                    StockMovement::create([
                        'product_id'     => $stock->product_id,
                        'user_id'        => auth()->id(),
                        'type'           => 'adjustment',
                        'reference_type' => StockAdjustment::class,
                        'reference_id'   => $savedAdj->id,
                        'qty_in'         => $item['selisih'] > 0 ? $item['selisih'] : 0,
                        'qty_out'        => $item['selisih'] < 0 ? abs($item['selisih']) : 0,
                        'balance'        => $newQty,
                        'notes'          => "Stock opname adjustment - SKU: {$stock->sku} - " . ($item['keterangan'] ?? 'Stock adjustment'),
                    ]);
                }
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Stok opname berhasil disimpan']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
        }
    }

    // OLD OPNAME TEMPLATE WITH SKU
    // public function exportOpnameTemplate(Request $request)
    // {
    //     $settings = json_decode(Storage::disk('public')->get('settings.json'), true) ?? [];

    //     $query = Stock::with('product')
    //         ->where('qty', '>=', 0)
    //         ->whereNotNull('sku')
    //         ->orderBy('product_id')
    //         ->orderBy('sku');

    //     if ($lokasi = $request->input('lokasi')) {
    //         $query->whereHas('product', fn($q) => $q->where('lokasi', $lokasi));
    //     }

    //     if ($supplierId = $request->input('supplier_id')) {
    //         $query->whereHas('pembelian', fn($q) => $q->where('supplier_id', $supplierId));
    //     }

    //     $stocks = $query->get();
    //     $date   = date('Y-m-d');

    //     return Excel::download(
    //         new StockOpnameTemplateExport($stocks, $date, $settings),
    //         'Template_Stock_Opname-'.$date.'.xlsx'
    //     );
    // }

    // NEW OPNAME TEMPLATE WITHOUT SKU
    public function exportOpnameTemplate(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
        ]);
        $settings = json_decode(Storage::disk('public')->get('settings.json'), true) ?? [];

        $query = Stock::with('product')
            ->selectRaw('
                product_id,
                SUM(qty) as total_qty,
                MAX(id) as last_stock_id
            ')
            ->where('qty', '>=', 0)
            ->groupBy('product_id')
            ->orderBy('product_id');

        if ($lokasi = $request->input('lokasi')) {
            $query->whereHas('product', fn($q) => $q->where('lokasi', $lokasi));
        }

        $query->whereHas('pembelian', fn ($q) => $q->where('supplier_id', $request->input('supplier_id')));

        if ($kategori = $request->input('kategori')) {
            $query->whereHas('product.category', fn($q) => $q->where('name', $kategori));
        }

        // Konversi ke collection Stock-like agar kompatibel dengan StockOpnameTemplateExport
        $stocks = $query->get()->map(function ($row) {
            $stock = Stock::find($row->last_stock_id);
            $stock->qty = (int) ($row->total_qty ?? 0);
            return $stock;
        });

        $date = date('Y-m-d');

        return Excel::download(
            new StockOpnameTemplateExport($stocks, $date, $settings),
            'Template_Stock_Opname-' . $date . '.xlsx'
        );
    }
}
