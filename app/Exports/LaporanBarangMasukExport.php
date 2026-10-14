<?php

namespace App\Exports;

use App\Exports\LaporanGudang\Konversi;
use App\Exports\LaporanGudang\StockMovementExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use App\Models\Pembelian;
use App\Models\StockMovement;
use App\Support\ReportQuery;
use Illuminate\Database\Eloquent\Builder;

/** B9: Laporan Barang Masuk (pergerakan stok dengan qty masuk). */
class LaporanBarangMasukExport extends StockMovementExport
{
    protected function makeMeta(): ReportMeta
    {
        return ReportMeta::fromRequest($this->request, 'LAPORAN BARANG MASUK', 'Barang-Masuk');
    }

    protected function sheetName(): string
    {
        return 'Barang Masuk';
    }

    protected function columnDefs(): array
    {
        return [
            ['h' => 'Tanggal', 'fmt' => Theme::FMT_DATETIME, 'align' => 'center'],
            ['h' => 'No Dokumen'],
            ['h' => 'Supplier'],
            ['h' => 'Kode Barang', 'text' => true],
            ['h' => 'Nama Barang', 'max' => 45],
            ['h' => 'Batch', 'text' => true],
            ['h' => 'Qty Masuk', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Satuan', 'align' => 'center'],
            ['h' => 'Konversi'],
            ['h' => 'Keterangan', 'max' => 50, 'wrap' => true],
        ];
    }

    protected function baseQuery(): Builder
    {
        return StockMovement::query()->where('qty_in', '>', 0);
    }

    protected function referenceRelations(): array
    {
        return [Pembelian::class => ['supplier']];
    }

    protected function movementRow(object $m, array $refs): array
    {
        $ref = ReportQuery::ref($refs, $m);
        $qty = (float) ($m->qty_in ?? 0);

        return [[
            $m->created_at,
            $ref?->code ?? '-',
            $m->reference_type === Pembelian::class ? ($ref?->supplier?->name ?? '-') : '-',
            $m->product?->code ?? '-',
            $m->product?->name ?? '-',
            $this->batchFromNotes($m->notes),
            $qty,
            $m->product?->satuan ?? 'PCS',
            Konversi::of($m->product, $qty),
            $m->notes ?: null,
        ], []];
    }

    protected function addTotals(SheetWriter $w): void
    {
        $w->totalRow('TOTAL', [7 => 'sum'], ['grand' => true, 'label_col' => 5]);
    }
}