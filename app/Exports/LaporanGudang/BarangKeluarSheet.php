<?php

namespace App\Exports\LaporanGudang;

use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use App\Models\DeliveryOrder;
use App\Models\StockMovement;
use App\Support\ReportQuery;
use Illuminate\Database\Eloquent\Builder;

/** B10 sheet 1: detail Barang Keluar (pergerakan stok dengan qty keluar). */
class BarangKeluarSheet extends StockMovementExport
{
    protected function makeMeta(): ReportMeta
    {
        return ReportMeta::fromRequest($this->request, 'LAPORAN BARANG KELUAR', 'Barang-Keluar');
    }

    protected function sheetName(): string
    {
        return 'Barang Keluar';
    }

    protected function columnDefs(): array
    {
        return [
            ['h' => 'Tanggal', 'fmt' => Theme::FMT_DATETIME, 'align' => 'center'],
            ['h' => 'No Dokumen'],
            ['h' => 'Tujuan'],
            ['h' => 'Kode Barang', 'text' => true],
            ['h' => 'Nama Barang', 'max' => 45],
            ['h' => 'Batch', 'text' => true],
            ['h' => 'Qty Keluar', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Satuan', 'align' => 'center'],
            ['h' => 'Konversi'],
            ['h' => 'Keterangan', 'max' => 50, 'wrap' => true],
        ];
    }

    protected function baseQuery(): Builder
    {
        return StockMovement::query()->where('qty_out', '>', 0);
    }

    protected function referenceRelations(): array
    {
        return [DeliveryOrder::class => ['owner', 'requestOrder.owner']];
    }

    protected function movementRow(object $m, array $refs): array
    {
        $ref = ReportQuery::ref($refs, $m);
        $qty = (float) ($m->qty_out ?? 0);

        return [[
            $m->created_at,
            $ref?->code ?? '-',
            $ref?->owner?->name ?? $ref?->requestOrder?->owner?->name ?? '-',
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