<?php

namespace App\Exports;

use App\Exports\LaporanGudang\Konversi;
use App\Exports\LaporanGudang\Status;
use App\Exports\LaporanToko\BaseLaporanTokoExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use App\Models\PickingList;
use App\Support\ReportQuery;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * B6: Laporan Picking & Packing, dikelompokkan per Picking List.
 * Kolom "Pack" dan "Packer" dihapus: tidak ada data packing terpisah di database.
 */
class LaporanPickingPackingExport extends BaseLaporanTokoExport
{
    private const STATUS = ['draft' => 'Draft', 'in_progress' => 'Proses', 'completed' => 'Selesai'];

    public function __construct(private readonly Request $request) {}

    protected function makeMeta(): ReportMeta
    {
        return ReportMeta::fromRequest($this->request, 'LAPORAN PICKING & PACKING', 'Picking-Packing');
    }

    protected function sheetName(): string
    {
        return 'Picking & Packing';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $w->columns([
            ['h' => 'Tanggal', 'fmt' => Theme::FMT_DATE, 'align' => 'center'],
            ['h' => 'Kode Picking'],
            ['h' => 'Kode DO'],
            ['h' => 'Tujuan'],
            ['h' => 'Kode Barang', 'text' => true],
            ['h' => 'Nama Barang', 'max' => 45],
            ['h' => 'Batch', 'text' => true],
            ['h' => 'Lokasi'],
            ['h' => 'Qty Order', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Qty Pick', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Selisih', 'fmt' => '+#,##0;-#,##0;0'],
            ['h' => 'Satuan', 'align' => 'center'],
            ['h' => 'Konversi (Pick)'],
            ['h' => 'Status', 'align' => 'center'],
            ['h' => 'Picker'],
            ['h' => 'Mulai', 'fmt' => Theme::FMT_DATETIME, 'align' => 'center'],
            ['h' => 'Selesai', 'fmt' => Theme::FMT_DATETIME, 'align' => 'center'],
            ['h' => 'Keterangan', 'max' => 40, 'wrap' => true],
        ])->kop()->info()->header();

        $pickings = PickingList::with(['requestOrder.owner', 'items.product', 'deliveryOrder', 'picker'])
            ->tap(fn ($q) => ReportQuery::betweenDates($q, 'created_at', $meta->start->toDateString(), $meta->end->toDateString()))
            ->orderBy('created_at')->orderBy('id')
            ->get();

        foreach ($pickings as $pk) {
            if ($pk->items->isEmpty()) {
                continue;
            }

            $status = self::STATUS[$pk->status ?? ''] ?? ucfirst((string) $pk->status);
            $picker = $pk->picker?->name ?: ($pk->picker_name ?: null);
            $first = null;

            foreach ($pk->items as $item) {
                $r = $w->currentRow();
                $ord = (float) ($item->qty_to_pick ?? 0);
                $picked = (float) ($item->qty_picked ?? 0);

                $w->row([
                    $pk->created_at,
                    $pk->code,
                    $pk->deliveryOrder?->code ?? '-',
                    $pk->requestOrder?->owner?->name ?? '-',
                    $item->product?->code ?? '-',
                    $item->product?->name ?? '-',
                    $item->sku ?: null,
                    $item->location ?: null,
                    $ord,
                    $picked,
                    SheetWriter::f("J{$r}-I{$r}"),
                    $item->product?->satuan ?? 'PCS',
                    Konversi::of($item->product, $picked),
                    $status,
                    $picker,
                    $pk->started_at,
                    $pk->completed_at,
                    $pk->notes ?: null,
                ], ['cells' => array_filter([
                    11 => $picked < $ord ? ['font' => Theme::DANGER_FG, 'bold' => true] : null,
                    14 => Status::cell($status),
                ])]);

                $first ??= $r;
            }

            $w->totalRow('Subtotal '.$pk->code, [9 => 'sum', 10 => 'sum', 11 => 'sum'], [
                'from' => $first, 'to' => $w->currentRow() - 1, 'label_col' => 6, 'sub' => true,
            ]);
        }

        $w->totalRow('TOTAL', [9 => 'sum', 10 => 'sum', 11 => 'sum'], ['grand' => true, 'label_col' => 6]);
    }
}