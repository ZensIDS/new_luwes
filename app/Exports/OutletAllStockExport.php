<?php

namespace App\Exports;

use App\Exports\LaporanToko\BaseLaporanTokoExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use App\Services\OutletLaporanService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** B4: All Stock Outlet, dikelompokkan per outlet dengan subtotal persediaan. */
class OutletAllStockExport extends BaseLaporanTokoExport
{
    public function __construct(private readonly Request $request) {}

    protected function makeMeta(): ReportMeta
    {
        return ReportMeta::fromRequest($this->request, 'LAPORAN ALL STOCK OUTLET', 'All-Stock-Toko', ['outlet'], now());
    }

    protected function sheetName(): string
    {
        return 'All Stock';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $data = app(OutletLaporanService::class)->allStock($this->request);

        $w->columns([
            ['h' => 'Outlet'],
            ['h' => 'Barcode', 'text' => true],
            ['h' => 'Produk', 'max' => 45],
            ['h' => 'Kategori'],
            ['h' => 'Qty', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Satuan', 'align' => 'center'],
            ['h' => 'HPP', 'fmt' => Theme::FMT_RUPIAH, 'min' => 14],
            ['h' => 'Pajak', 'fmt' => Theme::FMT_RUPIAH, 'min' => 14],
            ['h' => 'HPP + Pajak', 'fmt' => Theme::FMT_RUPIAH, 'min' => 14],
            ['h' => 'Persediaan Sebelum Pajak', 'fmt' => Theme::FMT_RUPIAH, 'min' => 18],
            ['h' => 'Persediaan Setelah Pajak', 'fmt' => Theme::FMT_RUPIAH, 'min' => 18],
        ])->kop()->info()->header();

        $outletId = null;
        $first = null;
        $label = '';

        $close = function () use ($w, &$first, &$label) {
            if ($first !== null) {
                $w->totalRow('Subtotal '.$label, [5 => 'sum', 10 => 'sum', 11 => 'sum'], [
                    'from' => $first, 'to' => $w->currentRow() - 1, 'label_col' => 3, 'sub' => true,
                ]);
            }
            $first = null;
        };

        foreach ($data['rows'] ?? [] as $row) {
            if ($row['outlet_id'] !== $outletId) {
                $close();
                $outletId = $row['outlet_id'];
                $label = (string) $row['outlet'];
                $w->section('Outlet: '.$label);
            }

            $r = $w->currentRow();
            $w->row([
                $row['outlet'], $row['barcode'], $row['product'], $row['category'],
                (float) $row['qty'], $row['satuan'],
                (float) $row['hpp'], (float) $row['tax'],
                SheetWriter::f("G{$r}+H{$r}"),   // HPP + Pajak
                SheetWriter::f("E{$r}*G{$r}"),   // Persediaan sebelum pajak
                SheetWriter::f("E{$r}*I{$r}"),   // Persediaan setelah pajak
            ]);
            $first ??= $r;
        }
        $close();

        $w->totalRow('TOTAL PERSEDIAAN', [5 => 'sum', 10 => 'sum', 11 => 'sum'], [
            'grand' => true, 'label_col' => 3,
        ]);
    }
}