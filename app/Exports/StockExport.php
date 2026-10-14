<?php

namespace App\Exports;

use App\Exports\LaporanGudang\Konversi;
use App\Exports\LaporanGudang\Status;
use App\Exports\LaporanToko\BaseLaporanTokoExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use App\Models\ProductMinimumAdjustment;
use App\Models\Stock;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** B7: Laporan Stok Barang, laporan posisi (per tanggal cetak), satu baris per batch. */
class StockExport extends BaseLaporanTokoExport
{
    public function __construct(private readonly Request $request) {}

    protected function makeMeta(): ReportMeta
    {
        return ReportMeta::fromRequest($this->request, 'LAPORAN STOK BARANG', 'Stok-Barang', [], now());
    }

    protected function sheetName(): string
    {
        return 'Stok Barang';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $w->columns([
            ['h' => 'Kode Barang', 'text' => true],
            ['h' => 'Nama Barang', 'max' => 45],
            ['h' => 'Kategori'],
            ['h' => 'Batch', 'text' => true],
            ['h' => 'Expired Date', 'fmt' => Theme::FMT_DATE, 'align' => 'center'],
            ['h' => 'Satuan', 'align' => 'center'],
            ['h' => 'Stok Batch', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Konversi (Batch)'],
            ['h' => 'Total Stok Produk', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Min Stok', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Selisih', 'fmt' => '+#,##0;-#,##0;0'],
            ['h' => 'Status Stok', 'align' => 'center'],
            ['h' => 'Status Expired', 'align' => 'center', 'min' => 15],
            ['h' => 'Lokasi'],
        ])->kop()->info()->header();

        // Dimuat sekali. Total stok produk = SUM(stocks.qty) semua batch (sama dengan menu Stok/Produk);
        // Min Stok, Selisih, dan Status Stok dinilai terhadap total ini, bukan qty satu batch.
        $adjustments = ProductMinimumAdjustment::activeOn(now()->toDateString())
            ->orderByDesc('active_from')->orderByDesc('id')
            ->get()->keyBy('product_id');

        $totals = Stock::selectRaw('product_id, SUM(qty) as total_qty')
            ->groupBy('product_id')->pluck('total_qty', 'product_id');

        // lazy(): dibaca per potongan (relasi ikut dimuat per potongan), urutan produk tetap stabil.
        $stocks = Stock::query()->with(['product.category'])
            ->orderBy('product_id')->orderBy('id')
            ->lazy(1000);

        foreach ($stocks as $s) {
            $r = $w->currentRow();
            $product = $s->product;
            $baseMin = (float) ($product?->min_stock ?? 0);
            $adj = $adjustments->get($s->product_id);
            $min = $adj ? (int) ceil($baseMin * (1 + $adj->adjustment_percentage / 100)) : (int) $baseMin;
            $total = (int) ($totals[$s->product_id] ?? 0);
            $diff = $total - $min;

            $statusStok = $total > $min ? 'Aman' : ($total > 0 ? 'Kritis' : 'Habis');
            $statusExp = $s->expired_at ? ($s->expired_at->isPast() ? 'Expired' : 'Belum Expired') : null;
            $qty = (float) ($s->qty ?? 0);

            $w->row([
                $product?->code ?? '-',
                $product?->name ?? '-',
                $product?->category?->name ?? '-',
                $s->sku ?: null,
                $s->expired_at,
                $product?->satuan ?? 'PCS',
                $qty,
                Konversi::of($product, $qty),
                (float) $total,
                (float) $min,
                SheetWriter::f("I{$r}-J{$r}"),
                $statusStok,
                $statusExp,
                $s->location ?: null,
            ], ['cells' => array_filter([
                11 => $diff < 0 ? ['font' => Theme::DANGER_FG, 'bold' => true] : null,
                12 => Status::cell($statusStok),
                13 => Status::cell($statusExp),
            ])]);
        }

        $w->totalRow('TOTAL STOK BATCH', [7 => 'sum'], ['grand' => true, 'label_col' => 2]);
    }
}