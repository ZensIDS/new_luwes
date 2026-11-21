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

/** B3: Retur Penjualan Outlet; barang retur, barang pengganti, dan selisih dibedakan warna. */
class OutletReturExport extends BaseLaporanTokoExport
{
    public function __construct(private readonly Request $request) {}

    protected function makeMeta(): ReportMeta
    {
        return ReportMeta::fromRequest($this->request, 'LAPORAN RETUR PENJUALAN OUTLET', 'Retur-Penjualan-Toko', ['outlet']);
    }

    protected function sheetName(): string
    {
        return 'Retur Penjualan';
    }

    /** Pola toko (huruf 18, A4 portrait, wrap, tanpa footer): todolist_font_laporan_toko. */
    protected function tokoLayout(): bool
    {
        return true;
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $data = app(OutletLaporanService::class)->returns($this->request);

        // Pola toko: 9 kolom. Invoice, Outlet, Petugas, dan Keterangan dibuang; kedua barcode dan
        // nama barang (retur dan pengganti) dipertahankan (K11).
        $w->columns([
            ['h' => 'Tanggal', 'fmt' => Theme::FMT_DATETIME, 'align' => 'center'],
            ['h' => 'Kode Retur', 'wrap' => true],
            ['h' => 'Barcode', 'text' => true],
            ['h' => 'Barang Retur', 'max' => 24, 'wrap' => true],
            ['h' => 'Qty', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Barcode', 'text' => true],
            ['h' => 'Barang Pengganti', 'max' => 24, 'wrap' => true],
            ['h' => 'Qty', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Selisih', 'fmt' => Theme::FMT_RUPIAH],
        ])->kop()->info()
            ->groupHeader([
                ['label' => 'Transaksi Retur', 'from' => 1, 'to' => 2],
                ['label' => 'Barang Retur', 'from' => 3, 'to' => 5],
                ['label' => 'Barang Pengganti', 'from' => 6, 'to' => 8],
                ['label' => 'Selisih', 'from' => 9, 'to' => 9],
            ])
            ->header();

        foreach ($data['rows'] ?? [] as $row) {
            $hasReturn = (float) $row['qty_retur'] > 0;
            $hasReplacement = (float) $row['qty_pengganti'] > 0;
            $diff = (float) $row['selisih'];

            $cells = [];
            if ($hasReturn) {
                foreach ([3, 4, 5] as $c) {
                    $cells[$c] = ['fill' => Theme::DANGER_BG, 'font' => Theme::DANGER_FG];
                }
            }
            if ($hasReplacement) {
                foreach ([6, 7, 8] as $c) {
                    $cells[$c] = ['fill' => Theme::INFO_BG, 'font' => Theme::INFO_FG];
                }
            }
            if ($diff > 0) {
                $cells[9] = ['fill' => Theme::OK_BG, 'font' => Theme::OK_FG, 'bold' => true];
            } elseif ($diff < 0) {
                $cells[9] = ['fill' => Theme::DANGER_BG, 'font' => Theme::DANGER_FG, 'bold' => true];
            }

            $w->row([
                $row['tanggal'] instanceof \DateTimeInterface ? $row['tanggal'] : Carbon::parse($row['tanggal']),
                $row['kode_retur'],
                $hasReturn ? $row['barcode_retur'] : null,
                $hasReturn ? $row['barang_retur'] : null,
                $hasReturn ? (float) $row['qty_retur'] : null,
                $hasReplacement ? $row['barcode_pengganti'] : null,
                $hasReplacement ? $row['barang_pengganti'] : null,
                $hasReplacement ? (float) $row['qty_pengganti'] : null,
                $diff !== 0.0 ? $diff : null,
            ], ['cells' => $cells]);
        }

        $count = (int) ($data['summary']['jumlah_retur'] ?? 0);
        $w->totalRow("REKAP ({$count} transaksi retur)", [5 => 'sum', 8 => 'sum', 9 => 'sum'], [
            'grand' => true, 'label_col' => 1,
        ]);
    }
}