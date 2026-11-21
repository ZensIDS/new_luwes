<?php

namespace App\Exports\LaporanToko;

use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Sell Out Brand, sheet 2: rekap per hari (jumlah invoice, qty, total). */
class OutletSellOutBrandHarianSheet extends BaseLaporanTokoExport
{
    public function __construct(
        private readonly Request $request,
        private readonly array $data,
        private readonly string $brand,
    ) {}

    protected function makeMeta(): ReportMeta
    {
        return ReportMeta::fromRequest($this->request, 'SELL OUT BRAND - REKAP HARIAN', 'Sell-Out-Brand', ['outlet', 'kasir', 'brand']);
    }

    protected function sheetName(): string
    {
        return 'Rekap Harian';
    }

    /** Pola toko (huruf 18, A4 portrait, wrap, tanpa footer): todolist_font_laporan_toko. */
    protected function tokoLayout(): bool
    {
        return true;
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $w->columns([
            ['h' => 'Tanggal', 'fmt' => Theme::FMT_DATE, 'align' => 'center'],
            ['h' => 'Jumlah Invoice', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Qty Total', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Total (Rp)', 'fmt' => Theme::FMT_RUPIAH],
        ])->kop()->info()->header();

        $days = [];
        foreach (($this->data['rows'] ?? []) as $row) {
            $date = $row['tanggal'] instanceof \DateTimeInterface
                ? Carbon::instance($row['tanggal'])
                : Carbon::parse($row['tanggal'] ?? now());
            $key = $date->format('Y-m-d');

            $days[$key] ??= ['date' => $date->copy()->startOfDay(), 'invoices' => [], 'sale' => 0.0, 'replace' => 0.0, 'total' => 0.0];
            $days[$key]['invoices'][$row['outlet'].'|'.$row['invoice']] = true;
            $days[$key][$row['type'] === 'Pengganti Retur' ? 'replace' : 'sale'] += (float) $row['qty'];
            $days[$key]['total'] += (float) $row['subtotal'];
        }
        ksort($days);

        if ($days === []) {
            $w->notice("Tidak ada penjualan brand \"{$this->brand}\" pada periode ini.");

            return;
        }

        foreach ($days as $d) {
            // Qty Total = qty penjualan + qty pengganti retur (kolom rinciannya dibuang).
            $w->row([
                $d['date'], count($d['invoices']), $d['sale'] + $d['replace'], $d['total'],
            ]);
        }

        $w->totalRow('TOTAL', [3 => 'sum', 4 => 'sum'], ['grand' => true]);
    }
}