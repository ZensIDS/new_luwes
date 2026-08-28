<?php

namespace App\Http\Controllers;

use App\Exports\StockOpnameTemplateExport;
use App\Models\Product;
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

        return view('stocks.index', [
            'kategoriOptions' => $kategoriOptions,
            'lokasiOptions'   => $lokasiOptions,
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

        $orderColIndex = (int) $request->input('order.0.column', 3);
        $orderDir      = strtolower($request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        // Kolom yang boleh dipakai untuk sorting (mapping index kolom DataTables -> kolom SQL asli)
        // Kolom lain (Konversi, Stock Outlet, Action) sengaja tidak sortable karena
        // datanya computed/relasi terpisah, bukan kolom langsung.
        $sortableColumns = [
            2 => 'products.code',
            3 => 'products.name',
            5 => 's.harga_beli',
            7 => 's.qty_reserved',
            8 => 'g.total_qty',
            9 => 's.created_at',
            10 => 's.expired_at',
            11 => 's.status',
        ];
        $orderBy = $sortableColumns[$orderColIndex] ?? 'products.name';

        // Grouping per product: satu baris representatif (stok terakhir) + total qty (SUM),
        // dihitung SEKALI lewat SQL groupBy -- bukan N query terpisah per baris seperti sebelumnya.
        $grouped = Stock::query()
            ->select('product_id')
            ->selectRaw('MAX(id) as last_stock_id')
            ->selectRaw('SUM(qty) as total_qty')
            ->where('qty', '>', 0)
            ->groupBy('product_id');

        // recordsTotal: total produk (tanpa filter search/kategori/lokasi) yang masih punya stock > 0
        $recordsTotal = DB::table(DB::raw("({$grouped->toSql()}) as g"))
            ->mergeBindings($grouped->getQuery())
            ->count();

        // Join ke products, categories, dan stocks (baris representatif) langsung di SQL
        // supaya search/filter/sort semuanya jalan di database.
        $base = DB::table(DB::raw("({$grouped->toSql()}) as g"))
            ->mergeBindings($grouped->getQuery())
            ->join('products', 'products.id', '=', 'g.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->join('stocks as s', 's.id', '=', 'g.last_stock_id');

        if ($kategori) {
            $base->where('categories.name', $kategori);
        }

        if ($lokasi) {
            $base->where('products.lokasi', $lokasi);
        }

        if ($searchValue !== '') {
            $base->where(function ($q) use ($searchValue) {
                $q->where('products.name', 'like', "%{$searchValue}%")
                    ->orWhere('products.code', 'like', "%{$searchValue}%")
                    ->orWhere('s.sku', 'like', "%{$searchValue}%")
                    ->orWhere('s.serial_number', 'like', "%{$searchValue}%");
            });
        }

        $recordsFiltered = (clone $base)->count();

        $rows = $base
            ->orderBy($orderBy, $orderDir)
            ->offset($start)
            ->limit($length)
            ->get([
                'g.product_id',
                'g.total_qty',
                's.id as stock_id',
                's.sku',
                's.serial_number',
                's.harga_beli',
                's.qty_reserved',
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

        // Data yang belum ikut di-join di atas (ownerStock.owner, pembelian.supplier):
        // ambil terpisah, tapi HANYA untuk baris di halaman ini (max 100 row), bukan semua data.
        $stockIds = $rows->pluck('stock_id')->all();

        $ownerStockMap = Stock::whereIn('id', $stockIds)
            ->with('ownerStock.owner')
            ->get()
            ->keyBy('id')
            ->map(fn($s) => $s->ownerStock?->qty ?? 0);

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

            $stockOutlet = $ownerStockMap->get($row->stock_id, 0);

            return [
                'product_id'     => $row->product_id,
                'stock_id'       => $row->stock_id,
                'code'           => $row->serial_number ?: $row->product_code,
                'product_name'   => $row->product_name,
                'konversi'       => $konversiDisplay($row->total_qty) ? '' : '', // ditangani di frontend via konversi_qty dsb
                'konversi_qty'   => $konversiQty,
                'satuan_besar'   => $satuanBesar,
                'satuan'         => $satuan,
                'harga_beli'     => (float) $row->harga_beli,
                'stock_outlet'   => (int) $stockOutlet,
                'qty_reserved'   => (int) $row->qty_reserved,
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
        return view('stocks.kartu');
    }

    public function searchStock(Request $request)
    {
        $search = trim((string) $request->get('q', ''));
        $page = max((int) $request->get('page', 1), 1);
        $perPage = 20;

        $query = Product::query()
            ->whereHas('stocks', fn($q) => $q->whereNotNull('sku'));

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

        // Ambil semua stok (semua SKU/batch) untuk produk ini
        $stocks = Stock::with('pembelian.supplier')
            ->where('product_id', $product->id)
            ->whereNotNull('sku')
            ->orderBy('sku')
            ->get();

        $productStocks = $stocks->map(function ($s) {
            return [
                'stock_id'      => $s->id,
                'sku'           => $s->sku,
                'qty_available' => (int) ($s->qty_available ?? 0),
                'status'        => $s->status,
                'supplier'      => $s->pembelian?->supplier?->name ?? '-',
            ];
        });

        $totalProductStock = $productStocks->sum('qty_available');

        $suppliersDisplay = $productStocks
            ->pluck('supplier')
            ->filter(fn($s) => $s && $s !== '-')
            ->unique()
            ->values()
            ->implode(', ');

        // Gabungkan transaksi dari semua SKU/batch produk ini,
        // running balance tetap dihitung per SKU (batch) secara independen
        $allTransactions = collect();

        foreach ($stocks as $stock) {
            $movements = StockMovement::where('product_id', $product->id)
                ->where(function ($q) use ($stock) {
                    $q->where('notes', 'like', "%SKU: {$stock->sku}%")
                        ->orWhere(function ($q2) use ($stock) {
                            $q2->where('reference_type', 'App\Models\Pembelian')
                                ->where('reference_id', $stock->pembelian_id);
                        });
                })
                ->orderBy('created_at', 'asc')
                ->get();

            $keteranganMap = $this->buildKeteranganMap($movements, $stock);

            $runningStock = 0;
            $currentPrice = $stock->harga_beli;

            foreach ($movements as $movement) {
                $stokAwal = $runningStock;
                $masuk = $movement->qty_in ?? 0;
                $keluar = $movement->qty_out ?? 0;
                $stokAkhir = $stokAwal + $masuk - $keluar;
                $nilai = $stokAkhir * $currentPrice;

                $allTransactions->push([
                    'sort_key'   => $movement->created_at->format('Y-m-d H:i:s') . '-' . str_pad($movement->id, 10, '0', STR_PAD_LEFT),
                    'tanggal'    => $movement->created_at->format('Y-m-d'),
                    'sku'        => $stock->sku,
                    'stok_awal'  => $stokAwal,
                    'masuk'      => $masuk,
                    'keluar'     => $keluar,
                    'stok_akhir' => $stokAkhir,
                    'harga'      => $currentPrice,
                    'nilai'      => $nilai,
                    'keterangan' => $keteranganMap[$movement->id] ?? '-',
                ]);

                $runningStock = $stokAkhir;
            }
        }

        $result = $allTransactions
            ->sortBy('sort_key')
            ->values()
            ->map(function ($t) {
                unset($t['sort_key']);
                return $t;
            });

        return response()->json([
            'product' => [
                'id'           => $product->id,
                'name'         => $product->name,
                'code'         => $product->code,
                'suppliers'    => $suppliersDisplay ?: '-',
                'konversi_qty' => $product->konversi_qty,
                'satuan_besar' => $product->satuan_besar,
                'satuan'       => $product->satuan,
            ],
            'transactions' => $result->values(),
            'product_summary' => [
                'total_qty' => $totalProductStock,
                'breakdown' => $productStocks->values(),
            ],
        ]);
    }

    /**
     * Ganti buildKartuKeterangan() lama yang query per baris.
     * Fungsi ini query StockAdjustment & RefundPembelianItem SEKALI SAJA
     * (pakai whereIn), lalu hasilnya dipetakan per movement_id di memory.
     */
    protected function buildKeteranganMap($movements, Stock $stock): array
    {
        $map = [];

        // Kelompokkan reference_id per tipe, supaya bisa 1x query per tipe (bukan per baris)
        $adjustmentIds = $movements
            ->where('reference_type', StockAdjustment::class)
            ->pluck('reference_id')
            ->unique()
            ->values();

        $refundMovementIds = $movements
            ->where('reference_type', RefundPembelian::class)
            ->pluck('reference_id')
            ->unique()
            ->values();

        // 1x query untuk semua StockAdjustment terkait
        $adjustments = $adjustmentIds->isNotEmpty()
            ? StockAdjustment::whereIn('id', $adjustmentIds)
            ->where('stock_id', $stock->id)
            ->get()
            ->keyBy('id')
            : collect();

        // 1x query untuk semua RefundPembelianItem terkait
        $refundItems = $refundMovementIds->isNotEmpty()
            ? RefundPembelianItem::whereIn('refund_pembelian_id', $refundMovementIds)
            ->where('product_id', $stock->product_id)
            ->where(function ($query) use ($stock) {
                $query->where('stock_id', $stock->id)
                    ->orWhere('sku', $stock->sku);
            })
            ->orderByDesc('id')
            ->get()
            ->groupBy('refund_pembelian_id') // ambil yang 'latest' per refund_pembelian_id nanti
            : collect();

        foreach ($movements as $movement) {
            $parts = [];

            $this->appendKeteranganPart($parts, $movement->notes);

            if ($movement->reference_type === StockAdjustment::class) {
                $adjustment = $adjustments->get($movement->reference_id);

                if ($adjustment) {
                    $this->appendKeteranganPart($parts, $adjustment->keterangan);
                    $this->appendKeteranganPart($parts, $adjustment->reason);
                }
            }

            if ($movement->reference_type === RefundPembelian::class) {
                $refundItem = optional($refundItems->get($movement->reference_id))->first();

                if ($refundItem && ! empty($refundItem->alasan)) {
                    $this->appendKeteranganPart($parts, 'Alasan retur: ' . $refundItem->alasan);
                }
            }

            $map[$movement->id] = ! empty($parts) ? implode(' | ', $parts) : '-';
        }

        return $map;
    }

    protected function appendKeteranganPart(array &$parts, ?string $value): void
    {
        $value = trim((string) $value);

        if ($value === '') {
            return;
        }

        $normalizedValue = mb_strtolower($value);

        foreach ($parts as $part) {
            $normalizedPart = mb_strtolower($part);

            if (
                $normalizedPart === $normalizedValue
                || str_contains($normalizedPart, $normalizedValue)
                || str_contains($normalizedValue, $normalizedPart)
            ) {
                return;
            }
        }

        $parts[] = $value;
    }

    //opname
    public function opname(Request $request)
    {
        $lokasiOptions = Product::whereNotNull('lokasi')
            ->where('lokasi', '!=', '')
            ->distinct()
            ->orderBy('lokasi')
            ->pluck('lokasi');

        $supplierOptions = \App\Models\Supplier::orderBy('name')
            ->whereHas('pembelians.stocks', fn($q) => $q->where('qty', '>=', 0)->whereNotNull('sku'))
            ->get(['id', 'name']);

        return view('stocks.opname', [
            'lokasiOptions'   => $lokasiOptions,
            'supplierOptions' => $supplierOptions,
        ]);
    }

    public function getOpnameData(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
        ]);

        $query = Stock::with('product', 'pembelian.supplier')
            ->where('qty', '>=', 0)
            ->whereNotNull('sku')
            ->orderBy('product_id')
            ->orderBy('sku');

        $query->whereHas('pembelian', fn($q) => $q->where('supplier_id', $request->input('supplier_id')));

        if ($lokasi = $request->input('lokasi')) {
            $query->whereHas('product', fn($q) => $q->where('lokasi', $lokasi));
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

        if ($supplierId = $request->input('supplier_id')) {
            $query->whereHas('pembelian', fn($q) => $q->where('supplier_id', $supplierId));
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
