<?php

namespace App\Exports;

use App\Exports\LaporanToko\BaseLaporanTokoExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use App\Services\OutletLaporanService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** B2: Rafaksi Outlet, dikelompokkan per rafaksi dengan persentase diskon dan rekap berumus. */
class OutletRafaksiExport extends BaseLaporanTokoExport
{
    public function __construct(private readonly Request $request) {}

    protected function makeMeta(): ReportMeta
    {
        return ReportMeta::fromRequest(
            $this->request, 'LAPORAN RAFAKSI OUTLET', 'Rafaksi-Toko', ['outlet', 'rafaksi'],
            null, ReportMeta::promotionRange($this->request)
        );
    }

    protected function sheetName(): string
    {
        return 'Rafaksi';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $data = app(OutletLaporanService::class)->rafaksi($this->request);
        $promotions = $data['promotions'] ?? collect();

        $w->columns([
            ['h' => 'Barcode', 'text' => true],
            ['h' => 'Nama Produk', 'max' => 45],
            ['h' => 'Qty Produk Terjual', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Harga Akhir / Unit', 'fmt' => Theme::FMT_RUPIAH, 'min' => 14],
            ['h' => 'Diskon Rafaksi / Unit', 'fmt' => Theme::FMT_RUPIAH, 'min' => 14],
            ['h' => '% Diskon', 'fmt' => Theme::FMT_PERCENT],
            ['h' => 'Total Diskon', 'fmt' => Theme::FMT_RUPIAH, 'min' => 16],
            ['h' => 'Total Penjualan', 'fmt' => Theme::FMT_RUPIAH, 'min' => 16],
            ['h' => 'Keterangan Rafaksi', 'max' => 40],
        ])->kop()->info()->header();

        $rows = collect($data['rows'] ?? [])->sortBy([['keterangan', 'asc'], ['product', 'asc']])->values();

        $key = null;
        $first = null;
        $label = '';
        $percent = fn (int $r) => SheetWriter::f("IF(H{$r}>0,G{$r}/H{$r},0)");

        $close = function () use ($w, &$first, &$label, $percent) {
            if ($first !== null) {
                $r = $w->currentRow();
                $w->totalRow('Subtotal '.$label, [3 => 'sum', 6 => $percent($r), 7 => 'sum', 8 => 'sum'], [
                    'from' => $first, 'to' => $r - 1, 'label_col' => 2, 'sub' => true,
                ]);
            }
            $first = null;
        };

        foreach ($rows as $row) {
            if ($row['keterangan'] !== $key) {
                $close();
                $key = $row['keterangan'];
                $label = (string) $key;
                $w->section('Rafaksi: '.$this->groupLabel($label, $promotions));
            }

            $r = $w->currentRow();
            $w->row([
                $row['barcode'], $row['product'], (float) $row['qty'],
                (float) $row['unit_price'], (float) $row['discount_per_unit'],
                $percent($r),
                (float) $row['discount_total'], (float) $row['subtotal'],
                $row['keterangan'],
            ]);
            $first ??= $r;
        }
        $close();

        $r = $w->currentRow();
        $w->totalRow('REKAP', [3 => 'sum', 6 => $percent($r), 7 => 'sum', 8 => 'sum'], [
            'grand' => true, 'label_col' => 2,
        ]);
    }

    /** Tambahkan periode bila nama rafaksi cocok tepat dengan satu data rafaksi. */
    private function groupLabel(string $names, $promotions): string
    {
        $parts = array_map('trim', explode(',', $names));
        if (count($parts) !== 1) {
            return $names;
        }

        $matches = collect($promotions)->where('name', $parts[0]);
        if ($matches->count() !== 1) {
            return $names;
        }

        $promo = $matches->first();
        $range = implode(' s/d ', array_filter([
            $promo->start_at?->format('d/m/Y'),
            $promo->end_at?->format('d/m/Y'),
        ]));

        return $range !== '' ? "{$names} ({$range})" : $names;
    }
}