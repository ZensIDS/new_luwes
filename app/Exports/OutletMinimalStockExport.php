<?php

namespace App\Exports;

use App\Exports\LaporanToko\BaseLaporanTokoExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use App\Services\OutletLaporanService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** B5: Minimal Stock Gudang & Alokasi PO (per produk, K6), kolom dikelompokkan dan status berwarna. */
class OutletMinimalStockExport extends BaseLaporanTokoExport
{
    /** Stok masih di atas minimum tapi tidak lebih dari 120% minimum dianggap "Kritis". */
    private const CRITICAL_RATIO = 1.2;

    public function __construct(private readonly Request $request) {}

    protected function makeMeta(): ReportMeta
    {
        $asOf = $this->request->filled('tanggal_selesai') ? Carbon::parse($this->request->input('tanggal_selesai')) : now();

        return ReportMeta::fromRequest($this->request, 'LAPORAN MINIMAL STOCK GUDANG & ALOKASI PO', 'Minimal-Stock-Toko', [], $asOf);
    }

    protected function sheetName(): string
    {
        return 'Minimal Stock';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $data = app(OutletLaporanService::class)->minimumStock($this->request);
        $asOf = Carbon::parse($data['asOf'] ?? now());

        $w->columns([
            ['h' => 'Barcode', 'text' => true],
            ['h' => 'Produk', 'max' => 45],
            ['h' => 'Kategori'],
            ['h' => 'Periode Penjualan', 'align' => 'center'],
            ['h' => 'Rata-rata Terjual / Bulan (Semua Outlet)', 'fmt' => Theme::FMT_DECIMAL],
            ['h' => 'Penjualan Pertama', 'fmt' => Theme::FMT_DATE, 'align' => 'center'],
            ['h' => 'Min Stock Manual (Default)', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Min Stock Dasar', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Adjustment %', 'fmt' => Theme::FMT_DECIMAL],
            ['h' => 'Min Stock Final', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Stok Gudang Saat Ini', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Saran Qty PO', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Status Stock', 'align' => 'center'],
            ['h' => 'Sumber Min Stock', 'align' => 'center', 'min' => 24],
            ['h' => 'Per Tanggal', 'fmt' => Theme::FMT_DATE, 'align' => 'center'],
        ])->kop()->info()
            ->groupHeader([
                ['label' => 'Produk', 'from' => 1, 'to' => 3],
                ['label' => 'Penjualan', 'from' => 4, 'to' => 6],
                ['label' => 'Min Stock', 'from' => 7, 'to' => 10],
                ['label' => 'Stok & Saran', 'from' => 11, 'to' => 15],
            ])
            ->header();

        foreach ($data['rows'] ?? [] as $row) {
            $stock = (float) $row['stock_qty'];
            $min = (float) $row['min_stock'];
            $manual = $row['source'] === 'manual';
            $hasAdjustment = (float) $row['adjustment_pct'] > 0;

            if ($row['status'] === 'out_of_stock') {
                $status = ['Out of Stock', Theme::DANGER_BG, Theme::DANGER_FG];
            } elseif ($min > 0 && $stock <= $min * self::CRITICAL_RATIO) {
                $status = ['Kritis', Theme::WARN_BG, Theme::WARN_FG];
            } else {
                $status = ['Normal', Theme::OK_BG, Theme::OK_FG];
            }

            // Nomor kolom mulai dari 1: 8 = Min Dasar, 9 = Adjustment %, 10 = Min Final, 13 = Status Stock, 14 = Sumber.
            $cells = [13 => ['fill' => $status[1], 'font' => $status[2], 'bold' => true]];
            if ($manual) {
                // Belum 3 bulan penjualan: min stock dasar memakai nilai manual.
                $cells[8] = ['fill' => Theme::INFO_BG, 'font' => Theme::INFO_FG];
                $cells[14] = ['fill' => Theme::INFO_BG, 'font' => Theme::INFO_FG, 'bold' => true];
            }
            if ($hasAdjustment) {
                // Ada adjustment aktif: tandai Adjustment % dan Min Final.
                $cells[9] = ['fill' => Theme::WARN_BG, 'font' => Theme::WARN_FG, 'bold' => true];
                $cells[10] = ['fill' => Theme::WARN_BG, 'font' => Theme::WARN_FG, 'bold' => true];
            }

            $w->row([
                $row['barcode'], $row['product'], $row['category'],
                Carbon::parse($row['window_start'])->format('d/m/Y').' s/d '.Carbon::parse($row['window_end'])->format('d/m/Y'),
                (float) $row['average_sold'],
                $row['first_sale'] ? Carbon::parse($row['first_sale']) : null,
                (int) $row['manual_min_stock'],
                (int) $row['base_min_stock'],
                (float) $row['adjustment_pct'],
                (int) $row['min_stock'],
                $stock,
                (float) $row['suggested_po_qty'],
                $status[0],
                $row['calculation_status'],
                $asOf,
            ], ['cells' => $cells]);
        }

        $from = $w->firstDataRow();
        $to = $w->currentRow() - 1;
        $outRange = "M{$from}:M{$to}";

        $w->totalRow('TOTAL', [
            2 => 'count',
            12 => 'sum',
            // Hitung baris "Out of Stock" yang terlihat (ikut filter).
            13 => SheetWriter::f("SUMPRODUCT(SUBTOTAL(103,OFFSET(M{$from},ROW({$outRange})-ROW(M{$from}),0))*({$outRange}=\"Out of Stock\"))"),
        ], [
            'grand' => true,
            'fmt' => [
                2 => '#,##0" produk"',
                12 => '#,##0" qty"',
                13 => '#,##0" out of stock"',
            ],
        ]);
    }
}