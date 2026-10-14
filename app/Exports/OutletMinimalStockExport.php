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

/** B5: Minimal Stock & Alokasi PO, kolom dikelompokkan dan status berwarna. */
class OutletMinimalStockExport extends BaseLaporanTokoExport
{
    /** Stok masih di atas minimum tapi tidak lebih dari 120% minimum dianggap "Kritis". */
    private const CRITICAL_RATIO = 1.2;

    public function __construct(private readonly Request $request) {}

    protected function makeMeta(): ReportMeta
    {
        $asOf = $this->request->filled('tanggal_selesai') ? Carbon::parse($this->request->input('tanggal_selesai')) : now();

        return ReportMeta::fromRequest($this->request, 'LAPORAN MINIMAL STOCK & ALOKASI PO OUTLET', 'Minimal-Stock-Toko', ['outlet'], $asOf);
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
            ['h' => 'Outlet'],
            ['h' => 'Barcode', 'text' => true],
            ['h' => 'Produk', 'max' => 45],
            ['h' => 'Kategori'],
            ['h' => 'Periode Penjualan', 'align' => 'center'],
            ['h' => 'Rata-rata Terjual / Bulan', 'fmt' => Theme::FMT_DECIMAL],
            ['h' => 'Penjualan Pertama', 'fmt' => Theme::FMT_DATE, 'align' => 'center'],
            ['h' => 'Jumlah PO (3 Bulan)', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'PO / Bulan', 'fmt' => Theme::FMT_DECIMAL],
            ['h' => 'Faktor', 'fmt' => Theme::FMT_DECIMAL],
            ['h' => 'Min Stock Manual', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Min Stock Hasil Hitung', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Stok Gudang Saat Ini', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Saran Qty PO', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Status Stock', 'align' => 'center'],
            ['h' => 'Status Perhitungan', 'align' => 'center', 'min' => 20],
            ['h' => 'Per Tanggal', 'fmt' => Theme::FMT_DATE, 'align' => 'center'],
        ])->kop()->info()
            ->groupHeader([
                ['label' => 'Produk', 'from' => 1, 'to' => 4],
                ['label' => 'Penjualan', 'from' => 5, 'to' => 7],
                ['label' => 'PO', 'from' => 8, 'to' => 10],
                ['label' => 'Stok', 'from' => 11, 'to' => 13],
                ['label' => 'Saran', 'from' => 14, 'to' => 17],
            ])
            ->header();

        foreach ($data['rows'] ?? [] as $row) {
            $stock = (float) $row['stock_qty'];
            $min = (float) $row['min_stock'];
            $manual = $row['factor'] === null;

            if ($row['status'] === 'out_of_stock') {
                $status = ['Out of Stock', Theme::DANGER_BG, Theme::DANGER_FG];
            } elseif ($min > 0 && $stock <= $min * self::CRITICAL_RATIO) {
                $status = ['Kritis', Theme::WARN_BG, Theme::WARN_FG];
            } else {
                $status = ['Normal', Theme::OK_BG, Theme::OK_FG];
            }

            $cells = [15 => ['fill' => $status[1], 'font' => $status[2], 'bold' => true]];
            if ($manual) {
                // Belum 3 bulan penjualan: min stock memakai nilai manual.
                $cells[12] = ['fill' => Theme::INFO_BG, 'font' => Theme::INFO_FG];
                $cells[16] = ['fill' => Theme::INFO_BG, 'font' => Theme::INFO_FG, 'bold' => true];
            }

            $w->row([
                $row['outlet'], $row['barcode'], $row['product'], $row['category'],
                Carbon::parse($row['window_start'])->format('d/m/Y').' s/d '.Carbon::parse($row['window_end'])->format('d/m/Y'),
                (float) $row['average_sold'],
                $row['first_sale'] ? Carbon::parse($row['first_sale']) : null,
                (int) $row['po_count'],
                (float) $row['po_per_month'],
                $row['factor'] === null ? '-' : (float) $row['factor'],
                (int) $row['manual_min_stock'],
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
        $outRange = "O{$from}:O{$to}";

        $w->totalRow('TOTAL', [
            3 => 'count',
            14 => 'sum',
            // Hitung baris "Out of Stock" yang terlihat (ikut filter).
            15 => SheetWriter::f("SUMPRODUCT(SUBTOTAL(103,OFFSET(O{$from},ROW({$outRange})-ROW(O{$from}),0))*({$outRange}=\"Out of Stock\"))"),
        ], [
            'grand' => true,
            'fmt' => [
                3 => '#,##0" produk"',
                14 => '#,##0" qty"',
                15 => '#,##0" out of stock"',
            ],
        ]);
    }
}