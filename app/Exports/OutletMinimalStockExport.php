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

    /** Pola toko (huruf 18, A4 portrait, wrap, tanpa footer): todolist_font_laporan_toko. */
    protected function tokoLayout(): bool
    {
        return true;
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $data = app(OutletLaporanService::class)->minimumStock($this->request);

        $w->columns([
            ['h' => 'Barcode', 'text' => true],
            ['h' => 'Produk', 'max' => 28, 'wrap' => true],
            ['h' => 'Min Stock Final', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Stok Gudang Saat Ini', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Saran Qty PO', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Status Stock', 'align' => 'center'],
        ])->kop()->info()
            ->groupHeader([
                ['label' => 'Produk', 'from' => 1, 'to' => 2],
                ['label' => 'Stok & Saran', 'from' => 3, 'to' => 6],
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

            // Nomor kolom mulai dari 1: 3 = Min Stock Final, 6 = Status Stock.
            $cells = [6 => ['fill' => $status[1], 'font' => $status[2], 'bold' => true]];
            if ($hasAdjustment) {
                // Ada adjustment aktif: tandai Min Final (kuning).
                $cells[3] = ['fill' => Theme::WARN_BG, 'font' => Theme::WARN_FG, 'bold' => true];
            } elseif ($manual) {
                // Belum 3 bulan penjualan: min stock memakai nilai manual (biru).
                $cells[3] = ['fill' => Theme::INFO_BG, 'font' => Theme::INFO_FG, 'bold' => true];
            }

            $w->row([
                $row['barcode'], $row['product'],
                (int) $row['min_stock'],
                $stock,
                (float) $row['suggested_po_qty'],
                $status[0],
            ], ['cells' => $cells]);
        }

        $from = $w->firstDataRow();
        $to = $w->currentRow() - 1;
        $outRange = "F{$from}:F{$to}";

        $w->totalRow('TOTAL', [
            2 => 'count',
            5 => 'sum',
            // Hitung baris "Out of Stock" yang terlihat (ikut filter).
            6 => SheetWriter::f("SUMPRODUCT(SUBTOTAL(103,OFFSET(F{$from},ROW({$outRange})-ROW(F{$from}),0))*({$outRange}=\"Out of Stock\"))"),
        ], [
            'grand' => true,
            'fmt' => [
                2 => '#,##0" produk"',
                5 => '#,##0" qty"',
                6 => '#,##0" out of stock"',
            ],
        ]);
    }
}