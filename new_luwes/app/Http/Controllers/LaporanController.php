<?php

namespace App\Http\Controllers;

use App\Exports\DeliveryOrderSingleExport;
use App\Exports\KartuStokExport;
use App\Exports\LabaRugiExport;
use App\Exports\LaporanAktifitasExport;
use App\Exports\LaporanBarangKeluarExport;
use App\Exports\LaporanBarangMasukExport;
use App\Exports\LaporanPembelianBarangExport;
use App\Exports\LaporanPenerimaanBarangExport;
use App\Exports\LaporanPengirimanExport;
use App\Exports\LaporanPergerakanExport;
use App\Exports\LaporanPickingPackingExport;
use App\Exports\LaporanPOExport;
use App\Exports\LaporanPRExport;
use App\Exports\PembelianExport;
use App\Exports\PembelianSingleExport;
use App\Exports\PembelianSupplierExport;
use App\Exports\PenerimaanExport;
use App\Exports\PengeluaranExport;
use App\Exports\PenjualanExport;
use App\Exports\PenjualanKasirExport;
use App\Exports\PenjualanSupplierExport;
use App\Exports\PickingListSingleExport;
use App\Exports\RequestOrderSingleExport;
use App\Exports\ReturOutletExport;
use App\Exports\ReturOutletSingleExport;
use App\Exports\ReturPembelianSingleExport;
use App\Exports\ReturSupplierExport;
use App\Exports\StockExport;
use App\Exports\StockOpnameExport;
use App\Models\DeliveryOrder;
use App\Models\Outlet;
use App\Models\Pembelian;
use App\Models\PickingList;
use App\Models\ProductMinimumAdjustment;
use App\Models\RefundPembelian;
use App\Models\RequestOrder;
use App\Models\Stock;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class LaporanController extends Controller
{
    public function index()
    {
        return view('laporan.index', [
            'cashiers' => User::where('role', 'staff-outlet')->get(),
            'outlets' => Outlet::get(),
            'suppliers' => Supplier::get(),
        ]);
    }

    public function exportPembelian(Request $request, $id = null)
    {
        $settings = json_decode(Storage::disk('public')->get('settings.json'), true) ?? [];

        if ($id) {
            $pembelian = Pembelian::with(['supplier', 'pembelianProducts.product'])->findOrFail($id);
            $safeCode = preg_replace('/[^A-Za-z0-9\-]/', '_', $pembelian->code);

            return Excel::download(new PembelianSingleExport($pembelian, $settings), 'Dokumen_PO-'.$safeCode.'.xlsx');

            // return Excel::download(new PembelianSingleExport($pembelian, $settings), 'Dokumen_PO-'.$pembelian->code.'.xlsx');
        }

        return Excel::download(new PembelianExport($request, $settings), 'laporan-pembelian.xlsx');
    }

    public function exportPickingList(Request $request, $id = null)
    {
        $settings = json_decode(Storage::disk('public')->get('settings.json'), true) ?? [];

        if ($id) {
            $pickinglist = PickingList::with(['requestOrder', 'items.product'])->findOrFail($id);
            $lokasi = $request->query('lokasi') ?: null;

            return Excel::download(new PickingListSingleExport($pickinglist, $settings, $lokasi), 'Dokumen_Picking_list-'.$pickinglist->code.'.xlsx');
        }

        return abort(404);
    }

    public function exportRequestOrder(Request $request, $id = null)
    {
        $settings = json_decode(Storage::disk('public')->get('settings.json'), true) ?? [];

        if ($id) {
            $requestOrder = RequestOrder::with(['owner', 'requestedBy', 'items.product', 'additionalNotes'])->findOrFail($id);

            return Excel::download(new RequestOrderSingleExport($requestOrder, $settings), 'Dokumen_Surat_Permintaan_Barang_(SPB)-'.$requestOrder->code.'.xlsx');
        }

        return abort(404);
    }

    public function exportDeliveryOrder(Request $request, $id = null)
    {
        $settings = json_decode(Storage::disk('public')->get('settings.json'), true) ?? [];

        if ($id) {
            $deliveryOrder = DeliveryOrder::with(['owner', 'requestOrder', 'items.product'])->findOrFail($id);

            return Excel::download(new DeliveryOrderSingleExport($deliveryOrder, $settings), 'Dokumen_Surat_Jalan-'.$deliveryOrder->code.'.xlsx');
        }

        return abort(404);
    }

    public function exportKartuStok(Request $request, $id = null)
    {
        $settings = json_decode(Storage::disk('public')->get('settings.json'), true) ?? [];

        if (! $id) {
            return abort(404);
        }

        $stock = Stock::with(['product', 'pembelian.supplier'])->findOrFail($id);
        $movements = StockMovement::where('product_id', $stock->product_id)
            ->where(function ($q) use ($stock) {
                $q->where('notes', 'like', "%SKU: {$stock->sku}%")
                    ->orWhere(function ($q2) use ($stock) {
                        $q2->where('reference_type', 'App\Models\Pembelian')
                            ->where('reference_id', $stock->pembelian_id);
                    });
            })
            ->orderBy('created_at', 'asc')
            ->get();

        return Excel::download(new KartuStokExport($stock, $movements, $settings), 'Kartu_Stok-'.$stock->sku.'.xlsx');
    }

    public function exportStockOpname(Request $request)
    {
        $settings = json_decode(Storage::disk('public')->get('settings.json'), true) ?? [];

        $today   = date('Y-m-d');
        $mulai   = $request->input('tanggal_mulai', $request->input('tanggal', $today)) ?: $today;
        $selesai = $request->input('tanggal_selesai', $mulai) ?: $mulai;
        $lokasi  = $request->input('lokasi');

        $lokasiFilter = $lokasi ? fn ($q) => $q->where('lokasi', $lokasi) : null;

        $query = StockAdjustment::with(['product', 'stock'])
            ->whereDate('adjustment_date', '>=', $mulai)
            ->whereDate('adjustment_date', '<=', $selesai);

        if ($lokasiFilter) {
            $query->whereHas('product', $lokasiFilter);
        }

        $adjustments = $query->get();

        // No data for the selected date — fall back to all adjustments (no date limit)
        if ($adjustments->isEmpty()) {
            $fallback = StockAdjustment::with(['product', 'stock'])
                ->orderByDesc('adjustment_date')
                ->limit(500);
            if ($lokasiFilter) {
                $fallback->whereHas('product', $lokasiFilter);
            }
            $adjustments = $fallback->get();
        }

        return Excel::download(new StockOpnameExport($adjustments, $mulai, $settings), 'Stock_Opname-'.$mulai.'.xlsx');
    }

    public function exportPenerimaan(Request $request, Pembelian $pembelian, $type = 'po')
    {
        $settings = json_decode(Storage::disk('public')->get('settings.json'), true) ?? [];

        $pembelian->load(['supplier', 'pembelianProducts.product', 'stocks.product']);

        return Excel::download(
            new PenerimaanExport($pembelian, $type, $settings),
            'Penerimaan-'.$type.' '.$pembelian->code.'.xlsx'
        );
    }

    public function exportPembelianSupplier(Request $request)
    {
        return Excel::download(new PembelianSupplierExport($request), 'laporan-pembelian-supplier-outlet.xlsx');
    }

    public function exportPenjualan(Request $request)
    {
        return Excel::download(new PenjualanExport($request), 'laporan-penjualan.xlsx');
    }

    public function exportPenjualanKasir(Request $request)
    {
        return Excel::download(new PenjualanKasirExport($request), 'laporan-penjualan-kasir.xlsx');
    }

    public function exportPenjualanSupplier(Request $request)
    {
        return Excel::download(new PenjualanSupplierExport($request), 'laporan-penjualan-supplier.xlsx');
    }

    public function exportStock()
    {
        return Excel::download(new StockExport, 'laporan-stock.xlsx');
    }

    public function exportPengeluaran()
    {
        return Excel::download(new PengeluaranExport, 'laporan-pengeluaran.xlsx');
    }

    public function exportLabaRugi()
    {
        return Excel::download(new LabaRugiExport, 'laporan-laba-rugi.xlsx');
    }

    public function exportReturSupplier(Request $request)
    {
        $settings = $this->getSettings();
        [$mulai, $selesai] = $this->dateRange($request);

        return Excel::download(
            new ReturSupplierExport($mulai, $selesai, $settings),
            'laporan-retur-supplier.xlsx'
        );
    }

    public function exportReturOutlet(Request $request)
    {
        $settings = $this->getSettings();
        [$mulai, $selesai] = $this->dateRange($request);

        return Excel::download(
            new ReturOutletExport($mulai, $selesai, $settings),
            'laporan-retur-outlet.xlsx'
        );
    }

    public function exportReturPembelianSingle(RefundPembelian $refundPembelian)
    {
        $settings = $this->getSettings();
        $refundPembelian->load('supplier', 'user', 'refundPembelianItems.product', 'refundPembelianItems.stock.pembelian');

        return Excel::download(
            new ReturPembelianSingleExport($refundPembelian, $settings),
            'Dokumen_Retur_Pembelian-'.$refundPembelian->code.'.xlsx'
        );
    }

    public function exportReturOutletSingle(RefundPembelian $refundPembelian)
    {
        $settings = $this->getSettings();
        $refundPembelian->load('outlet', 'deliveryOrder', 'user', 'refundPembelianItems.product', 'refundPembelianItems.stock');

        return Excel::download(
            new ReturOutletSingleExport($refundPembelian, $settings),
            'Dokumen_Retur_Outlet-'.$refundPembelian->code.'.xlsx'
        );
    }

    public function pdfReturPembelianSingle(RefundPembelian $refundPembelian)
    {
        $settings = $this->getSettings();
        $retur    = $refundPembelian->load('supplier', 'user', 'refundPembelianItems.product', 'refundPembelianItems.stock');

        return Pdf::loadView('exports.pdf.retur-pembelian-single', compact('retur', 'settings'))
            ->setPaper('a4', 'landscape')
            ->stream('Dokumen_Retur_Pembelian-'.$retur->code.'.pdf');
    }

    public function pdfReturOutletSingle(RefundPembelian $refundPembelian)
    {
        $settings = $this->getSettings();
        $retur    = $refundPembelian->load('outlet', 'deliveryOrder', 'user', 'refundPembelianItems.product', 'refundPembelianItems.stock');

        return Pdf::loadView('exports.pdf.retur-outlet-single', compact('retur', 'settings'))
            ->setPaper('a4', 'landscape')
            ->stream('Dokumen_Retur_Outlet-'.$retur->code.'.pdf');
    }

    public function pdfReturSupplier(Request $request)
    {
        $settings          = $this->getSettings();
        [$mulai, $selesai] = $this->dateRange($request);

        $rows = RefundPembelian::with([
            'supplier',
            'refundPembelianItems.product',
            'refundPembelianItems.stock.pembelian',
        ])
            ->where('type', 'gudang_ke_supplier')
            ->whereDate('tanggal', '>=', $mulai)
            ->whereDate('tanggal', '<=', $selesai)
            ->orderBy('tanggal')
            ->get()
            ->flatMap(fn ($retur) => $retur->refundPembelianItems->each(fn ($item) => $item->retur = $retur));

        return Pdf::loadView('exports.pdf.laporan-retur-supplier', compact('rows', 'settings', 'mulai', 'selesai'))
            ->setPaper('a4', 'landscape')
            ->stream('Laporan_Retur_Ke_Supplier_Keseluruhan.pdf');
    }

    public function pdfReturOutlet(Request $request)
    {
        $settings          = $this->getSettings();
        [$mulai, $selesai] = $this->dateRange($request);

        $rows = RefundPembelian::with([
            'outlet',
            'deliveryOrder',
            'refundPembelianItems.product',
            'refundPembelianItems.stock',
        ])
            ->where('type', 'outlet_ke_gudang')
            ->whereDate('tanggal', '>=', $mulai)
            ->whereDate('tanggal', '<=', $selesai)
            ->orderBy('tanggal')
            ->get()
            ->flatMap(fn ($retur) => $retur->refundPembelianItems->each(fn ($item) => $item->retur = $retur));

        return Pdf::loadView('exports.pdf.laporan-retur-outlet', compact('rows', 'settings', 'mulai', 'selesai'))
            ->setPaper('a4', 'landscape')
            ->stream('Laporan_Retur_Outlet_Keseluruhan.pdf');
    }

    // ── PDF Methods ──────────────────────────────────────────────────────────

    protected function getSettings(): array
    {
        return json_decode(Storage::disk('public')->get('settings.json'), true) ?? [];
    }

    protected function dateRange(Request $request): array
    {
        return [
            $request->input('tanggal_mulai', now()->startOfMonth()->format('Y-m-d')),
            $request->input('tanggal_selesai', now()->format('Y-m-d')),
        ];
    }

    public function pdfPO(Request $request)
    {
        [$mulai, $selesai] = $this->dateRange($request);
        $settings = $this->getSettings();

        $pembelians = Pembelian::with(['supplier', 'pembelianProducts.product'])
            ->whereDate('created_at', '>=', $mulai)->whereDate('created_at', '<=', $selesai)
            ->orderBy('created_at')->get();

        $rows = [];
        $no   = 1;
        foreach ($pembelians as $p) {
            foreach ($p->pembelianProducts as $pp) {
                $kQty  = $pp->product?->konversiDisplay($pp->qty) ?? '-';
                $qtyRcv = $pp->qty_received ?? $pp->qty;
                $kRcv  = $pp->product?->konversiDisplay($qtyRcv) ?? '-';
                $rows[] = [
                    'no' => $no++,
                    'tanggal' => $p->created_at->isoFormat('DD MMM YYYY'),
                    'kode_po' => $p->code,
                    'no_pr' => $p->requestOrder?->code ?? '-',
                    'supplier' => $p->supplier?->name ?? '-',
                    'kode_barang' => $pp->product?->code ?? '-',
                    'nama_barang' => $pp->product?->name ?? '-',
                    'qty' => $pp->qty.($kQty && $kQty !== '-' ? " ({$kQty})" : ''),
                    'satuan' => $pp->product?->satuan ?? 'PCS',
                    'harga_total' => $pp->subtotal,
                    'qty_diterima' => $qtyRcv.($kRcv && $kRcv !== '-' ? " ({$kRcv})" : ''),
                    'status' => ucfirst($p->status ?? '-'),
                    'keterangan' => '',
                ];
            }
        }

        return Pdf::loadView('exports.pdf.laporan-po', compact('rows', 'settings', 'mulai', 'selesai'))
            ->setPaper('a4', 'landscape')->stream('Laporan_PO.pdf');
    }

    public function pdfPR(Request $request)
    {
        [$mulai, $selesai] = $this->dateRange($request);
        $settings = $this->getSettings();

        $orders = RequestOrder::with(['owner', 'items.product'])
            ->whereDate('request_date', '>=', $mulai)->whereDate('request_date', '<=', $selesai)
            ->orderBy('request_date')->get();

        $rows = [];
        $no   = 1;
        foreach ($orders as $ro) {
            foreach ($ro->items as $item) {
                $k = $item->product?->konversiDisplay($item->qty_requested) ?? '-';
                $rows[] = [
                    'no' => $no++,
                    'tanggal' => \Carbon\Carbon::parse($ro->request_date)->isoFormat('DD MMM YYYY'),
                    'kode_pr' => $ro->code,
                    'outlet' => $ro->owner?->name ?? '-',
                    'kode_barang' => $item->product?->code ?? '-',
                    'nama_barang' => $item->product?->name ?? '-',
                    'qty' => $item->qty_requested.($k && $k !== '-' ? " ({$k})" : ''),
                    'satuan' => $item->product?->satuan ?? 'PCS',
                    'status' => ucfirst($ro->status ?? '-'),
                    'kode_po' => '-',
                    'keterangan' => '',
                ];
            }
        }

        return Pdf::loadView('exports.pdf.laporan-pr', compact('rows', 'settings', 'mulai', 'selesai'))
            ->setPaper('a4', 'landscape')->stream('Laporan_PR.pdf');
    }

    public function pdfBarangMasuk(Request $request)
    {
        [$mulai, $selesai] = $this->dateRange($request);
        $settings = $this->getSettings();

        $movements = StockMovement::with(['product'])
            ->where('qty_in', '>', 0)
            ->whereDate('created_at', '>=', $mulai)->whereDate('created_at', '<=', $selesai)
            ->orderBy('created_at')->get()
            ->map(function ($m) {
                $docCode = '-';
                $supplier = '-';
                if ($m->reference_type && $m->reference_id) {
                    $ref = $m->reference_type::find($m->reference_id);
                    $docCode = $ref?->code ?? '-';
                    if ($m->reference_type === 'App\Models\Pembelian') { $supplier = $ref?->supplier?->name ?? '-'; }
                }
                preg_match('/SKU:\s*(\S+)/', $m->notes ?? '', $matches);
                $m->doc_code = $docCode;
                $m->supplier_n = $supplier;
                $m->batch = $matches[1] ?? '-';

                return $m;
            });

        return Pdf::loadView('exports.pdf.laporan-barang-masuk', compact('movements', 'settings', 'mulai', 'selesai'))
            ->setPaper('a4', 'landscape')->stream('Laporan_Barang_Masuk.pdf');
    }

    public function pdfBarangKeluar(Request $request)
    {
        [$mulai, $selesai] = $this->dateRange($request);
        $settings = $this->getSettings();

        $movements = StockMovement::with(['product'])
            ->where('qty_out', '>', 0)
            ->whereDate('created_at', '>=', $mulai)->whereDate('created_at', '<=', $selesai)
            ->orderBy('created_at')->get()
            ->map(function ($m) {
                $docCode = '-';
                $tujuan = '-';
                if ($m->reference_type && $m->reference_id) {
                    $ref = $m->reference_type::find($m->reference_id);
                    $docCode = $ref?->code ?? '-';
                    $tujuan = $ref?->owner?->name ?? $ref?->requestOrder?->owner?->name ?? '-';
                }
                preg_match('/SKU:\s*(\S+)/', $m->notes ?? '', $matches);
                $m->doc_code = $docCode;
                $m->tujuan = $tujuan;
                $m->batch = $matches[1] ?? '-';

                return $m;
            });

        return Pdf::loadView('exports.pdf.laporan-barang-keluar', compact('movements', 'settings', 'mulai', 'selesai'))
            ->setPaper('a4', 'landscape')->stream('Laporan_Barang_Keluar.pdf');
    }

    public function pdfStok(Request $request)
    {
        $settings  = $this->getSettings();
        $stocks    = Stock::with(['product.category', 'pembelian'])->orderBy('product_id')->get();
        $activeAdjs = ProductMinimumAdjustment::activeOn(now()->toDateString())
            ->orderByDesc('active_from')->orderByDesc('id')
            ->get()->keyBy('product_id');

        return Pdf::loadView('exports.pdf.laporan-stok', compact('stocks', 'settings', 'activeAdjs'))
            ->setPaper('a4', 'landscape')->stream('Laporan_Stok_Barang.pdf');
    }

    public function pdfKartuStok(Request $request, $id)
    {
        $settings = $this->getSettings();

        $stock = Stock::with(['product.category', 'pembelian.supplier'])->findOrFail($id);

        $movements = StockMovement::where('product_id', $stock->product_id)
            ->where(function ($q) use ($stock) {
                $q->where('notes', 'like', "%SKU: {$stock->sku}%")
                    ->orWhere(function ($q2) use ($stock) {
                        $q2->where('reference_type', 'App\Models\Pembelian')
                            ->where('reference_id', $stock->pembelian_id);
                    });
            })
            ->orderBy('created_at', 'asc')
            ->get();

        $transactions = [];
        $runningStock = 0;

        foreach ($movements as $movement) {
            $stokAwal  = $runningStock;
            $masuk     = $movement->qty_in ?? 0;
            $keluar    = $movement->qty_out ?? 0;
            $stokAkhir = $stokAwal + $masuk - $keluar;

            $transactions[] = [
                'tanggal'    => $movement->created_at->isoFormat('DD MMM YYYY'),
                'stok_awal'  => $stokAwal,
                'masuk'      => $masuk,
                'keluar'     => $keluar,
                'stok_akhir' => $stokAkhir,
                'harga'      => $stock->harga_beli,
                'nilai'      => $stokAkhir * $stock->harga_beli,
                'keterangan' => $movement->notes ?? '-',
            ];

            $runningStock = $stokAkhir;
        }

        return Pdf::loadView('exports.pdf.kartu-stok', compact('stock', 'transactions', 'settings'))
            ->setPaper('a4', 'portrait')
            ->stream('Kartu_Stok-'.$stock->sku.'.pdf');
    }

    public function pdfPenerimaanBarang(Request $request)
    {
        [$mulai, $selesai] = $this->dateRange($request);
        $settings = $this->getSettings();

        $stocks = Stock::with(['pembelian.supplier', 'product'])
            ->whereHas('pembelian')
            ->whereDate('created_at', '>=', $mulai)->whereDate('created_at', '<=', $selesai)
            ->orderBy('created_at')->get();

        return Pdf::loadView('exports.pdf.laporan-penerimaan', compact('stocks', 'settings', 'mulai', 'selesai'))
            ->setPaper('a4', 'landscape')->stream('Laporan_Penerimaan_Barang.pdf');
    }

    public function pdfPenerimaanSingle($id)
    {
        $settings = $this->getSettings();

        $pembelian = Pembelian::with([
            'supplier',
            'pembelianProducts.product',
            'stocks.product',
        ])->findOrFail($id);

        return Pdf::loadView('exports.pdf.laporan-penerimaan-single', compact('pembelian', 'settings'))
            ->setPaper('a4', 'portrait')
            ->stream('Penerimaan_Barang-'.($pembelian->code_gr ?? $pembelian->code).'.pdf');
    }

    public function pdfPengiriman(Request $request)
    {
        [$mulai, $selesai] = $this->dateRange($request);
        $settings = $this->getSettings();

        $deliveries = DeliveryOrder::with(['owner', 'requestOrder', 'items.product'])
            ->whereDate('delivery_date', '>=', $mulai)->whereDate('delivery_date', '<=', $selesai)
            ->orderBy('delivery_date')->get();

        $rows = [];
        $no = 1;
        foreach ($deliveries as $do) {
            foreach ($do->items as $item) {
                $k = $item->product?->konversiDisplay($item->qty) ?? '-';
                $rows[] = [
                    'no' => $no++,
                    'tanggal' => \Carbon\Carbon::parse($do->delivery_date)->isoFormat('DD MMM YYYY'),
                    'no_sj' => $do->code,
                    'no_do' => $do->requestOrder?->code ?? '-',
                    'tujuan' => $do->owner?->name ?? '-',
                    'kode_barang' => $item->product?->code ?? '-',
                    'nama_barang' => $item->product?->name ?? '-',
                    'batch' => $item->sku ?? '-',
                    'qty_kirim' => $item->qty.($k && $k !== '-' ? " ({$k})" : ''),
                    'satuan' => $item->product?->satuan ?? 'PCS',
                    'status' => ucfirst($do->status ?? '-'),
                    'keterangan' => '',
                ];
            }
        }

        return Pdf::loadView('exports.pdf.laporan-pengiriman', compact('rows', 'settings', 'mulai', 'selesai'))
            ->setPaper('a4', 'landscape')->stream('Laporan_Pengiriman.pdf');
    }

    public function pdfPicking(Request $request)
    {
        [$mulai, $selesai] = $this->dateRange($request);
        $settings = $this->getSettings();

        $pickings = PickingList::with(['requestOrder.owner', 'items.product'])
            ->whereDate('created_at', '>=', $mulai)->whereDate('created_at', '<=', $selesai)
            ->orderBy('created_at')->get();

        $rows = [];
        $no = 1;
        foreach ($pickings as $pk) {
            foreach ($pk->items as $item) {
                $kOrd  = $item->product?->konversiDisplay($item->qty_to_pick) ?? '-';
                $kPick = $item->product?->konversiDisplay($item->qty_picked) ?? '-';
                $rows[] = [
                    'no' => $no++,
                    'tanggal' => $pk->created_at->isoFormat('DD MMM YYYY'),
                    'kode_picking' => $pk->code,
                    'kode_do' => $pk->deliveryOrder?->code ?? '-',
                    'tujuan' => $pk->requestOrder?->owner?->name ?? '-',
                    'kode_barang' => $item->product?->code ?? '-',
                    'nama_barang' => $item->product?->name ?? '-',
                    'lokasi' => $item->location ?? '-',
                    'qty_order' => $item->qty_to_pick.($kOrd && $kOrd !== '-' ? " ({$kOrd})" : ''),
                    'qty_pick'  => $item->qty_picked.($kPick && $kPick !== '-' ? " ({$kPick})" : ''),
                    'qty_pack'  => $item->qty_picked.($kPick && $kPick !== '-' ? " ({$kPick})" : ''),
                    'status' => ucfirst($pk->status ?? '-'),
                    'picker' => $pk->picker?->name ?? '-',
                    'packer' => '-',
                    'keterangan' => $pk->notes ?? '',
                ];
            }
        }

        return Pdf::loadView('exports.pdf.laporan-picking', compact('rows', 'settings', 'mulai', 'selesai'))
            ->setPaper('a4', 'landscape')->stream('Laporan_Picking_Packing.pdf');
    }

    public function pdfAktifitas(Request $request)
    {
        [$mulai, $selesai] = $this->dateRange($request);
        $settings = $this->getSettings();

        $movements = StockMovement::with(['product'])
            ->whereDate('created_at', '>=', $mulai)->whereDate('created_at', '<=', $selesai)
            ->orderBy('created_at')->get()
            ->map(function ($m) {
                $docCode = '-';
                if ($m->reference_type && $m->reference_id) {
                    $ref = $m->reference_type::find($m->reference_id);
                    $docCode = $ref?->code ?? '-';
                }
                $m->jenis    = $m->qty_in > 0 ? 'Penerimaan' : 'Pengiriman';
                $m->doc_code = $docCode;
                $m->pic = optional($m->product?->suppliers)->pluck('pic_supplier')?->filter()->implode(', ');
                $m->lokasi   = $m->product?->lokasi;
                $m->status   = $m->type;
                $m->qty      = max($m->qty_in ?? 0, $m->qty_out ?? 0);

                return $m;
            });

        return Pdf::loadView('exports.pdf.laporan-aktifitas', compact('movements', 'settings', 'mulai', 'selesai'))
            ->setPaper('a4', 'landscape')->stream('Laporan_Aktifitas_Gudang.pdf');
    }

    public function pdfPembelianBarang(Request $request)
    {
        [$mulai, $selesai] = $this->dateRange($request);
        $settings = $this->getSettings();

        $pembelians = Pembelian::with(['supplier', 'pembelianProducts.product'])
            ->whereDate('created_at', '>=', $mulai)->whereDate('created_at', '<=', $selesai)
            ->orderBy('created_at')->get();

        $rows = [];
        $no = 1;
        foreach ($pembelians as $p) {
            foreach ($p->pembelianProducts as $pp) {
                $k = $pp->product?->konversiDisplay($pp->qty) ?? '-';
                $rows[] = [
                    'no' => $no++,
                    'tanggal' => $p->created_at->isoFormat('DD MMM YYYY'),
                    'kode_po' => $p->code,
                    'supplier' => $p->supplier?->name ?? '-',
                    'kode_barang' => $pp->product?->code ?? '-',
                    'nama_barang' => $pp->product?->name ?? '-',
                    'qty' => $pp->qty.($k && $k !== '-' ? " ({$k})" : ''),
                    'satuan' => $pp->product?->satuan ?? 'PCS',
                    'harga_satuan' => $pp->harga_beli,
                    'total_harga' => $pp->subtotal,
                    'status' => ucfirst($p->status ?? '-'),
                    'keterangan' => '',
                ];
            }
        }

        return Pdf::loadView('exports.pdf.laporan-pembelian', compact('rows', 'settings', 'mulai', 'selesai'))
            ->setPaper('a4', 'landscape')->stream('Laporan_Pembelian_Barang.pdf');
    }

    public function pdfFakturPembelian($id)
    {
        $settings = $this->getSettings();

        $pembelian = Pembelian::with([
            'supplier',
            'pembelianProducts.product',
            'pembelianTransaction',
        ])->findOrFail($id);

        $paymentHistory = $pembelian->pembelianTransaction?->payment_history ?? [];

        return Pdf::loadView('exports.pdf.faktur-pembelian', compact('pembelian', 'paymentHistory', 'settings'))
            ->setPaper('a4', 'portrait')
            ->stream('Faktur_Pembelian-'.$pembelian->code.'.pdf');
    }

    public function pdfOpname(Request $request)
    {
        [$mulai, $selesai] = $this->dateRange($request);
        $settings = $this->getSettings();

        $adjustments = StockAdjustment::with(['product', 'stock'])
            ->whereDate('adjustment_date', '>=', $mulai)->whereDate('adjustment_date', '<=', $selesai)
            ->orderBy('adjustment_date')->get();

        return Pdf::loadView('exports.pdf.laporan-opname', compact('adjustments', 'settings', 'mulai', 'selesai'))
            ->setPaper('a4', 'landscape')->stream('Laporan_Stock_Opname.pdf');
    }

    public function pdfPergerakan(Request $request)
    {
        $settings = $this->getSettings();

        $today = now()->toDateString();
        $activeAdjs = ProductMinimumAdjustment::activeOn($today)
            ->orderByDesc('active_from')
            ->orderByDesc('id')
            ->get()
            ->keyBy('product_id');

        $movementStats = StockMovement::selectRaw('product_id, SUM(qty_out) as total_out, MIN(created_at) as first_date, MAX(created_at) as last_date')
            ->groupBy('product_id')->get()->keyBy('product_id');

        $rows = Stock::with(['product.category'])->get()->map(function ($s) use ($movementStats, $activeAdjs) {
            $stat       = $movementStats[$s->product_id] ?? null;
            $totalOut   = (int) ($stat?->total_out ?? 0);
            $months     = max(1, (int) \Carbon\Carbon::parse($stat?->first_date ?? now())->diffInMonths(now()) + 1);
            $avgKeluar  = round($totalOut / $months, 1);
            $hariTanpa  = $stat ? now()->diffInDays(\Carbon\Carbon::parse($stat->last_date)) : 0;
            $baseMin    = $s->product?->min_stock ?? 0;
            $adj        = $activeAdjs->get($s->product_id);
            $minStok    = $adj
                ? (int) ceil($baseMin * (1 + $adj->adjustment_percentage / 100))
                : (int) $baseMin;
            $qty        = $s->qty ?? 0;
            $qtyReorder = $qty <= $minStok ? max(0, $minStok * 2 - $qty) : 0;
            $kStok      = $s->product?->konversiDisplay($qty) ?? '-';
            $kReorder   = $s->product?->konversiDisplay($qtyReorder) ?? '-';

            return [
                'kode_barang'   => $s->product?->code ?? '-',
                'nama_barang'   => $s->product?->name ?? '-',
                'stok'          => $qty.($kStok && $kStok !== '-' ? " ({$kStok})" : ''),
                'avg_keluar'    => $avgKeluar,
                'hari_tanpa'    => $hariTanpa,
                'kategori'      => $avgKeluar >= 10 ? 'Fast Moving' : ($avgKeluar >= 3 ? 'Medium Moving' : 'Slow Moving'),
                'status_stok'   => $qty > $minStok ? 'Aman' : ($qty > 0 ? 'Kritis' : 'Habis'),
                'min_stok'      => $minStok,
                'saran_reorder' => $qty <= $minStok ? 'Ya' : 'Tidak',
                'qty_reorder'   => $qty <= $minStok ? ($qtyReorder.($kReorder && $kReorder !== '-' ? " ({$kReorder})" : '')) : 0,
                'keterangan'    => '',
            ];
        })->values()->all();

        return Pdf::loadView('exports.pdf.laporan-pergerakan', compact('rows', 'settings'))
            ->setPaper('a4', 'landscape')->stream('Laporan_Pergerakan_Stok.pdf');
    }

    // ── Excel Methods (new) ──────────────────────────────────────────────────

    public function exportLaporanPO(Request $request)
    {
        return Excel::download(new LaporanPOExport($request), 'laporan-po.xlsx');
    }

    public function exportPR(Request $request)
    {
        return Excel::download(new LaporanPRExport($request), 'laporan-pr.xlsx');
    }

    public function exportBarangMasuk(Request $request)
    {
        return Excel::download(new LaporanBarangMasukExport($request), 'laporan-barang-masuk.xlsx');
    }

    public function exportBarangKeluar(Request $request)
    {
        return Excel::download(new LaporanBarangKeluarExport($request), 'laporan-barang-keluar.xlsx');
    }

    public function exportPenerimaanBarang(Request $request)
    {
        return Excel::download(new LaporanPenerimaanBarangExport($request), 'laporan-penerimaan-barang.xlsx');
    }

    public function exportPengiriman(Request $request)
    {
        return Excel::download(new LaporanPengirimanExport($request), 'laporan-pengiriman.xlsx');
    }

    public function exportPickingPacking(Request $request)
    {
        return Excel::download(new LaporanPickingPackingExport($request), 'laporan-picking-packing.xlsx');
    }

    public function exportAktifitas(Request $request)
    {
        return Excel::download(new LaporanAktifitasExport($request), 'laporan-aktifitas-gudang.xlsx');
    }

    public function exportPembelianBarang(Request $request)
    {
        return Excel::download(new LaporanPembelianBarangExport($request), 'laporan-pembelian-barang.xlsx');
    }

    public function exportPergerakan(Request $request)
    {
        return Excel::download(new LaporanPergerakanExport($request), 'laporan-pergerakan-stok.xlsx');
    }
}
