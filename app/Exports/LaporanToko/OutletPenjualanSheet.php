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

    /** Pola toko (huruf 18, A4 portrait, wrap, tanpa footer): todolist_font_laporan_toko. */
    protected function tokoLayout(): bool
    {
        return true;
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $w->columns([
            ['h' => 'Tanggal', 'fmt' => Theme::FMT_DATETIME, 'align' => 'center'],
            ['h' => 'Invoice', 'wrap' => true],
            ['h' => 'Barcode', 'text' => true],
            ['h' => 'Produk', 'max' => 24, 'wrap' => true],
            ['h' => 'Qty', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Harga Satuan', 'fmt' => Theme::FMT_RUPIAH],
            ['h' => 'Subtotal', 'fmt' => Theme::FMT_RUPIAH],
        ])->kop()->info()->header();

        $rows = $this->mergeSameProduct($this->data['rows'] ?? collect());
        $groupKey = null;
        $groupFirst = null;
        $groupLast = null;
        $groupLabel = '';

        $closeGroup = function () use ($w, &$groupFirst, &$groupLast, &$groupLabel) {
            if ($groupFirst !== null) {
                $w->totalRow('Subtotal '.$groupLabel, [5 => 'sum', 7 => 'sum'], [
                    'from' => $groupFirst, 'to' => $groupLast, 'label_col' => 4, 'sub' => true,
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

            // Kolom Jenis dibuang: barang pengganti retur ditandai di nama produk (dan warna baris).
            $product = $row['product'].($row['type'] === 'Pengganti Retur' ? ' (Pengganti Retur)' : '');

            $r = $w->row([
                $date, $row['invoice'], $row['barcode'], $product, $row['qty'], $row['unit_price'], $row['subtotal'],
            ], $opts);

            $groupFirst ??= $r;
            $groupLast = $r;
        }
        $closeGroup();

        $w->totalRow('TOTAL', [5 => 'sum', 7 => 'sum'], ['grand' => true, 'label_col' => 4]);
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