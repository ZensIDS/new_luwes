<?php

namespace App\Exports;

use App\Exports\LaporanGudang\Konversi;
use App\Exports\LaporanToko\BaseLaporanTokoExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use App\Models\Outlet;
use App\Models\OutletPrice;
use App\Services\PriceFolderIndex;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel Master Harga Jual (pengganti print), laporan posisi per waktu cetak.
 *
 * Kolom sama seperti tabel index (tanpa Action). Angka tetap angka murni (bisa difilter/dijumlah di Excel):
 * komponen persen disimpan sebagai persen, komponen nominal sebagai Rupiah.
 *
 * Perhitungan harga memakai closure `$decorate` dari controller (decoratePrices), jadi angka di Excel
 * pasti sama dengan tabel index. Baris dimuat per 200 id agar outlet besar tidak memuat semuanya sekaligus.
 */
class OutletPriceExport extends BaseLaporanTokoExport
{
    /** Kolom komponen harga: [kolom nilai, kolom tipe], urut sama dengan tabel index. */
    private const COMPONENTS = [
        ['pajak_value', 'pajak_type'],
        ['disc_brand_value', 'disc_brand_type'],
        ['disc_tambahan_value', 'disc_tambahan_type'],
        ['disc_tambahan_2_value', 'disc_tambahan_2_type'],
        ['disc_toko_value', 'disc_toko_type'],
        ['margin_value', 'margin_type'],
    ];

    private const FMT_STOCK = '#,##0;[Red]-#,##0';

    /**
     * @param  Collection<int,int>  $ids  id aturan harga, sudah berurutan (A-Z nama produk)
     * @param  Closure(Collection<int,OutletPrice>):void  $decorate
     */
    public function __construct(
        private readonly Request $request,
        private readonly int $outletId,
        private readonly ?string $folder,
        private readonly Collection $ids,
        private readonly Closure $decorate,
    ) {}

    /** Nama file: Laporan-Master-Harga-Jual-<outlet>[-Folder-<folder>]_<tanggal>.xlsx */
    protected function makeMeta(): ReportMeta
    {
        $outletName = Outlet::whereKey($this->outletId)->value('name') ?? '-';
        $folderLabel = $this->folder === null
            ? null
            : ($this->folder === PriceFolderIndex::OTHER ? PriceFolderIndex::OTHER_LABEL : $this->folder);

        $base = ReportMeta::fromRequest($this->request, 'MASTER HARGA JUAL', 'Master-Harga-Jual', [], now());

        $info = [
            'Outlet' => $outletName,
            'Cakupan' => $folderLabel === null ? 'Semua barang' : 'Folder '.$folderLabel,
            'Total Produk' => number_format($this->ids->count(), 0, ',', '.'),
        ];

        $fileLabel = 'Master Harga Jual '.$outletName.($folderLabel === null ? '' : ' Folder '.$folderLabel);

        return new ReportMeta(
            $base->title,
            $fileLabel,
            $base->start,
            $base->end,
            $info,
            $base->settings,
            $base->printedBy,
            $base->printedAt,
            $base->snapshot,
        );
    }

    protected function sheetName(): string
    {
        return 'Master Harga Jual';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $w->columns([
            ['h' => 'Barcode', 'text' => true, 'align' => 'left', 'min' => 14],
            ['h' => 'Nama Product', 'max' => 45],
            ['h' => 'Satuan', 'align' => 'center'],
            ['h' => 'HPP / Satuan Kecil', 'fmt' => Theme::FMT_RUPIAH],
            ['h' => 'HPP / Satuan Besar', 'fmt' => Theme::FMT_RUPIAH],
            ['h' => 'Pajak', 'fmt' => Theme::FMT_RUPIAH, 'align' => 'center', 'min' => 11],
            ['h' => 'Disc Reg', 'fmt' => Theme::FMT_RUPIAH, 'align' => 'center', 'min' => 11],
            ['h' => 'Disc 1', 'fmt' => Theme::FMT_RUPIAH, 'align' => 'center', 'min' => 11],
            ['h' => 'Disc 2', 'fmt' => Theme::FMT_RUPIAH, 'align' => 'center', 'min' => 11],
            ['h' => 'Disc Toko', 'fmt' => Theme::FMT_RUPIAH, 'align' => 'center', 'min' => 11],
            ['h' => 'Margin', 'fmt' => Theme::FMT_RUPIAH, 'align' => 'center', 'min' => 11],
            ['h' => 'Harga Jual', 'fmt' => Theme::FMT_RUPIAH],
            ['h' => 'Penyesuaian', 'fmt' => Theme::FMT_RUPIAH, 'align' => 'center', 'min' => 12],
            ['h' => 'Catatan', 'max' => 40, 'wrap' => true],
            ['h' => 'Terakhir Update', 'fmt' => Theme::FMT_DATETIME, 'align' => 'center'],
            ['h' => 'Stok Outlet', 'fmt' => self::FMT_STOCK],
            ['h' => 'Konversi Stok', 'min' => 14],
        ])->kop()->info()->groupHeader([
            ['label' => 'Produk', 'from' => 1, 'to' => 3],
            ['label' => 'HPP Terbaru', 'from' => 4, 'to' => 5],
            ['label' => 'Komponen Harga', 'from' => 6, 'to' => 11],
            ['label' => 'Harga', 'from' => 12, 'to' => 13],
            ['label' => 'Keterangan & Stok', 'from' => 14, 'to' => 17],
        ])->header();

        $count = 0;

        foreach ($this->ids->chunk(200) as $chunkIds) {
            $order = $chunkIds->values()->flip();

            $prices = OutletPrice::with('product')
                ->whereIn('id', $chunkIds)
                ->get()
                ->sortBy(fn (OutletPrice $price) => $order[$price->id])
                ->values();

            ($this->decorate)($prices);

            foreach ($prices as $price) {
                $this->writeRow($w, $price);
                $count++;
            }
        }

        // Tanpa data: tidak ada baris total supaya pesan "Tidak ada data" tampil.
        if ($count > 0) {
            $w->totalRow('TOTAL PRODUK', [2 => 'count'], [
                'grand' => true,
                'fmt' => [2 => '#,##0" produk"'],
            ]);
        }
    }

    private function writeRow(SheetWriter $w, OutletPrice $price): void
    {
        $product = $price->product;
        $hpp = (float) ($price->print_hpp ?? 0);
        $konversi = (float) ($product?->konversi_qty ?? 0);
        $stockQty = (int) ($price->outlet_stock_qty ?? 0);

        $values = [
            $product?->code ?: null,
            $product?->name ?: null,
            $product?->satuan ?: '-',
            $hpp,
            $konversi >= 1 ? round($hpp * $konversi, 2) : null,
        ];
        $formats = [];

        // Kolom 6-11: komponen harga; kosong bila nilainya 0.
        foreach (self::COMPONENTS as $n => [$valueColumn, $typeColumn]) {
            [$value, $format] = $this->component((float) ($price->{$valueColumn} ?? 0), $price->{$typeColumn});
            $values[] = $value;
            if ($format !== null) {
                $formats[6 + $n] = $format;
            }
        }

        $values[] = (float) ($price->print_price_net ?? 0); // 12

        [$adjustment, $adjustmentFormat] = $this->component(
            (float) ($price->outlet_adjustment_value ?? 0),
            $price->outlet_adjustment_type
        );
        $values[] = $adjustment; // 13
        if ($adjustmentFormat !== null) {
            $formats[13] = $adjustmentFormat;
        }

        $values[] = $price->note ?: null;                 // 14
        $values[] = $price->updated_at;                   // 15
        $values[] = $stockQty;                            // 16
        $values[] = $stockQty > 0 ? Konversi::of($product, $stockQty) : null; // 17

        $w->row($values, [
            'fmt' => $formats,
            'cells' => [12 => ['bold' => true]],
        ]);
    }

    /**
     * Nilai sel + format khusus. Persen disimpan sebagai pecahan (10% => 0,1) berformat persen;
     * nominal tetap Rupiah (memakai format kolom, jadi format = null). Nol => sel kosong.
     *
     * @return array{0:?float,1:?string}
     */
    private function component(float $value, ?string $type): array
    {
        if ($value == 0.0) {
            return [null, null];
        }

        if ($type === 'percentage') {
            return [$value / 100, floor($value) == $value ? '0%' : '0.00%'];
        }

        return [$value, null];
    }
}