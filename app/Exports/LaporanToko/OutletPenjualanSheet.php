<?php

namespace App\Exports\LaporanToko;

use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** B1 sheet 1: detail penjualan, satu baris per item, tanpa rowspan. */
class OutletPenjualanSheet extends BaseLaporanTokoExport
{
    public function __construct(private readonly Request $request, private readonly array $data) {}

    protected function makeMeta(): ReportMeta
    {
        return ReportMeta::fromRequest($this->request, 'LAPORAN PENJUALAN OUTLET', 'Penjualan-Toko', ['outlet', 'kasir']);
    }

    protected function sheetName(): string
    {
        return 'Penjualan';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $w->columns([
            ['h' => 'Tanggal', 'fmt' => Theme::FMT_DATETIME, 'align' => 'center'],
            ['h' => 'Invoice'],
            ['h' => 'Outlet'],
            ['h' => 'Kasir'],
            ['h' => 'Jenis', 'align' => 'center'],
            ['h' => 'Barcode', 'text' => true],
            ['h' => 'Produk', 'max' => 45],
            ['h' => 'Qty', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Harga Satuan', 'fmt' => Theme::FMT_RUPIAH],
            ['h' => 'Rafaksi / Unit', 'fmt' => Theme::FMT_RUPIAH],
            ['h' => 'Subtotal', 'fmt' => Theme::FMT_RUPIAH],
            ['h' => 'Payment Method'],
        ])->kop()->info()->header();

        $rows = $this->mergeSameProduct($this->data['rows'] ?? collect());
        $groupKey = null;
        $groupFirst = null;
        $groupLast = null;
        $groupLabel = '';

        $closeGroup = function () use ($w, &$groupFirst, &$groupLast, &$groupLabel) {
            if ($groupFirst !== null) {
                $w->totalRow('Subtotal '.$groupLabel, [8 => 'sum', 11 => 'sum'], [
                    'from' => $groupFirst, 'to' => $groupLast, 'label_col' => 7, 'sub' => true,
                ]);
            }
            $groupFirst = null;
        };

        foreach ($rows as $row) {
            $key = $row['outlet'].'|'.$row['invoice'];
            if ($key !== $groupKey) {
                $closeGroup();
                $groupKey = $key;
                $groupLabel = (string) $row['invoice'];
            }

            $date = $row['tanggal'] instanceof \DateTimeInterface
                ? $row['tanggal']
                : ($row['tanggal'] ? Carbon::parse($row['tanggal']) : null);

            $opts = $row['type'] === 'Pengganti Retur'
                ? ['fill' => Theme::INFO_BG, 'font' => Theme::INFO_FG]
                : [];

            $r = $w->row([
                $date, $row['invoice'], $row['outlet'], $row['kasir'], $row['type'],
                $row['barcode'], $row['product'], $row['qty'], $row['unit_price'],
                $row['rafaksi'], $row['subtotal'], $row['payment_method'],
            ], $opts);

            $groupFirst ??= $r;
            $groupLast = $r;
        }
        $closeGroup();

        $w->totalRow('TOTAL', [8 => 'sum', 11 => 'sum'], ['grand' => true, 'label_col' => 7]);
    }

    /**
     * Satu produk = satu baris per invoice. Item yang sama dari batch berbeda
     * (termasuk stok minus) digabung: qty dan subtotal dijumlah, harga satuan dan
     * rafaksi per unit dihitung rata-rata tertimbang qty.
     *
     * @return array<int,array<string,mixed>>
     */
    private function mergeSameProduct(iterable $rows): array
    {
        $merged = [];
        $rafaksiTotal = [];

        foreach ($rows as $row) {
            $key = implode('|', [$row['outlet'], $row['invoice'], $row['type'], $row['barcode'], $row['product']]);

            if (! isset($merged[$key])) {
                $merged[$key] = $row;
                $merged[$key]['qty'] = 0.0;
                $merged[$key]['subtotal'] = 0.0;
                $rafaksiTotal[$key] = 0.0;
            }

            $merged[$key]['qty'] += (float) $row['qty'];
            $merged[$key]['subtotal'] += (float) $row['subtotal'];
            $rafaksiTotal[$key] += (float) $row['rafaksi'] * (float) $row['qty'];
        }

        foreach ($merged as $key => &$row) {
            $qty = $row['qty'];
            $row['unit_price'] = $qty > 0 ? $row['subtotal'] / $qty : 0.0;
            $row['rafaksi'] = $qty > 0 ? $rafaksiTotal[$key] / $qty : 0.0;
        }
        unset($row);

        return array_values($merged);
    }
}