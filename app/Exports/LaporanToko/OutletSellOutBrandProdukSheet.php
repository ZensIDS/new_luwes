<?php

namespace App\Exports\LaporanToko;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sell Out Brand, sheet 3: rekap per produk.
 * Qty Bersih = Qty Nota - Qty Retur + Qty Pengganti (sama dengan angka Penjualan Outlet).
 */
class OutletSellOutBrandProdukSheet extends BaseLaporanTokoExport
{
    public function __construct(
        private readonly Request $request,
        private readonly array $data,
        private readonly string $brand,
    ) {}

    protected function makeMeta(): ReportMeta
    {
        return ReportMeta::fromRequest($this->request, 'SELL OUT BRAND - REKAP PRODUK', 'Sell-Out-Brand', ['outlet', 'kasir', 'brand']);
    }

    protected function sheetName(): string
    {
        return 'Rekap Produk';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $w->columns([
            ['h' => 'Barcode', 'text' => true],
            ['h' => 'Produk', 'max' => 45],
            ['h' => 'Qty Nota', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Qty Retur', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Qty Pengganti Retur', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Qty Bersih', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Total Bersih (Rp)', 'fmt' => Theme::FMT_RUPIAH],
        ])->kop()->info()->header();

        // Qty nota dan retur dari service; pengganti dan nilai rupiah dari baris laporan.
        $products = [];
        foreach (($this->data['brandStats'] ?? []) as $key => $stat) {
            $products[$key] = [
                'barcode' => $stat['barcode'], 'product' => $stat['product'],
                'gross' => (float) $stat['gross'], 'returned' => (float) $stat['returned'],
                'replace' => 0.0, 'total' => 0.0,
            ];
        }
        foreach (($this->data['rows'] ?? []) as $row) {
            $key = $row['barcode'].'|'.$row['product'];
            $products[$key] ??= [
                'barcode' => $row['barcode'], 'product' => $row['product'],
                'gross' => 0.0, 'returned' => 0.0, 'replace' => 0.0, 'total' => 0.0,
            ];
            if ($row['type'] === 'Pengganti Retur') {
                $products[$key]['replace'] += (float) $row['qty'];
            }
            $products[$key]['total'] += (float) $row['subtotal'];
        }

        if ($products === []) {
            $w->notice("Tidak ada penjualan brand \"{$this->brand}\" pada periode ini.");

            return;
        }

        // Terlaris (qty bersih) di atas.
        uasort($products, fn ($a, $b) => [$b['gross'] - $b['returned'] + $b['replace'], $a['product']]
            <=> [$a['gross'] - $a['returned'] + $a['replace'], $b['product']]);

        foreach ($products as $p) {
            $r = $w->currentRow();
            $w->row([
                $p['barcode'], $p['product'], $p['gross'], $p['returned'], $p['replace'],
                SheetWriter::f('C'.$r.'-D'.$r.'+E'.$r), $p['total'],
            ]);
        }

        $w->totalRow('TOTAL', [3 => 'sum', 4 => 'sum', 5 => 'sum', 6 => 'sum', 7 => 'sum'], ['grand' => true, 'label_col' => 2]);
    }
}
