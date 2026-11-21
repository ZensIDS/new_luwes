<?php

namespace App\Exports\LaporanToko;

use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Sell Out Brand, sheet 1: rekap harian per invoice (hanya barang brand terpilih). */
class OutletSellOutBrandInvoiceSheet extends BaseLaporanTokoExport
{
    public function __construct(
        private readonly Request $request,
        private readonly array $data,
        private readonly string $brand,
    ) {}

    protected function makeMeta(): ReportMeta
    {
        return ReportMeta::fromRequest($this->request, 'SELL OUT BRAND - PER INVOICE', 'Sell-Out-Brand', ['outlet', 'kasir', 'brand']);
    }

    protected function sheetName(): string
    {
        return 'Per Invoice';
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

        $rows = $this->mergedRows($this->data['rows'] ?? collect());
        if ($rows === []) {
            $w->notice("Tidak ada penjualan brand \"{$this->brand}\" pada periode ini.");

            return;
        }

        $day = null;
        $dayFirst = null;
        $dayLast = null;
        $invoiceKey = null;
        $invoiceFirst = null;
        $invoiceLast = null;
        $invoiceLabel = '';

        $closeInvoice = function () use ($w, &$invoiceFirst, &$invoiceLast, &$invoiceLabel) {
            if ($invoiceFirst !== null) {
                $w->totalRow('Subtotal '.$invoiceLabel, [5 => 'sum', 7 => 'sum'], [
                    'from' => $invoiceFirst, 'to' => $invoiceLast, 'label_col' => 4, 'sub' => true,
                ]);
            }
            $invoiceFirst = null;
        };
        $closeDay = function () use ($w, &$day, &$dayFirst, &$dayLast) {
            if ($dayFirst !== null) {
                $w->totalRow('Total '.Carbon::parse($day)->format('d/m/Y'), [5 => 'sum', 7 => 'sum'], [
                    'from' => $dayFirst, 'to' => $dayLast, 'label_col' => 4, 'sub' => true,
                ]);
            }
            $dayFirst = null;
        };

        foreach ($rows as $row) {
            $rowDay = $row['date']->format('Y-m-d');
            if ($rowDay !== $day) {
                $closeInvoice();
                $closeDay();
                $day = $rowDay;
                $invoiceKey = null;
                $w->section($row['date']->copy()->locale('id')->isoFormat('dddd, DD MMMM YYYY'));
            }

            $key = $row['outlet'].'|'.$row['invoice'];
            if ($key !== $invoiceKey) {
                $closeInvoice();
                $invoiceKey = $key;
                $invoiceLabel = (string) $row['invoice'];
            }

            $opts = $row['type'] === 'Pengganti Retur'
                ? ['fill' => Theme::INFO_BG, 'font' => Theme::INFO_FG]
                : [];

            // Kolom Jenis dibuang: barang pengganti retur ditandai di nama produk (dan warna baris).
            $product = $row['product'].($row['type'] === 'Pengganti Retur' ? ' (Pengganti Retur)' : '');

            $r = $w->row([
                $row['date'], $row['invoice'], $row['barcode'], $product, $row['qty'], $row['unit_price'], $row['subtotal'],
            ], $opts);

            $invoiceFirst ??= $r;
            $invoiceLast = $r;
            $dayFirst ??= $r;
            $dayLast = $r;
        }
        $closeInvoice();
        $closeDay();

        $w->totalRow('TOTAL', [5 => 'sum', 7 => 'sum'], ['grand' => true, 'label_col' => 4]);
    }

    /**
     * Urut tanggal, outlet, invoice; satu produk = satu baris per invoice dan jenis.
     * Item yang sama dari batch berbeda digabung: qty dan subtotal dijumlah, harga satuan dan
     * rafaksi per unit dihitung rata-rata tertimbang qty.
     *
     * @return array<int,array<string,mixed>>
     */
    private function mergedRows(iterable $rows): array
    {
        $merged = [];
        $rafaksiTotal = [];

        foreach ($rows as $row) {
            $date = $row['tanggal'] instanceof \DateTimeInterface
                ? Carbon::instance($row['tanggal'])
                : Carbon::parse($row['tanggal'] ?? now());

            $key = implode('|', [$date->format('Y-m-d'), $row['outlet'], $row['invoice'], $row['type'], $row['barcode'], $row['product']]);

            if (! isset($merged[$key])) {
                $merged[$key] = $row;
                $merged[$key]['date'] = $date;
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

        $list = array_values($merged);
        usort($list, fn ($a, $b) => [$a['date']->format('Y-m-d'), $a['outlet'], $a['invoice'], $a['type'], $a['product']]
            <=> [$b['date']->format('Y-m-d'), $b['outlet'], $b['invoice'], $b['type'], $b['product']]);

        return $list;
    }
}