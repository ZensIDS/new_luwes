<?php

namespace App\Exports;

use App\Exports\LaporanGudang\Konversi;
use App\Exports\LaporanGudang\Status;
use App\Exports\LaporanToko\BaseLaporanTokoExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use App\Models\DeliveryOrder;
use App\Support\ReportQuery;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** B5: Laporan Pengiriman Barang, dikelompokkan per Surat Jalan dengan subtotal qty. */
class LaporanPengirimanExport extends BaseLaporanTokoExport
{
    private const STATUS = ['draft' => 'Draft', 'sent' => 'Dikirim', 'delivered' => 'Diterima', 'completed' => 'Selesai'];

    public function __construct(private readonly Request $request) {}

    protected function makeMeta(): ReportMeta
    {
        return ReportMeta::fromRequest($this->request, 'LAPORAN PENGIRIMAN BARANG', 'Pengiriman-Barang');
    }

    protected function sheetName(): string
    {
        return 'Pengiriman Barang';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $w->columns([
            ['h' => 'Tanggal', 'fmt' => Theme::FMT_DATE, 'align' => 'center'],
            ['h' => 'No Surat Jalan'],
            ['h' => 'No Dokumen'],
            ['h' => 'Tujuan'],
            ['h' => 'Kode Barang', 'text' => true],
            ['h' => 'Nama Barang', 'max' => 45],
            ['h' => 'Batch', 'text' => true],
            ['h' => 'Expired', 'fmt' => Theme::FMT_DATE, 'align' => 'center'],
            ['h' => 'Qty', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Satuan', 'align' => 'center'],
            ['h' => 'Konversi'],
            ['h' => 'Status', 'align' => 'center'],
            ['h' => 'Keterangan', 'max' => 40, 'wrap' => true],
        ])->kop()->info()->header();

        $deliveries = DeliveryOrder::with(['owner', 'requestOrder', 'items.product'])
            ->tap(fn ($q) => ReportQuery::betweenDates($q, 'delivery_date', $meta->start->toDateString(), $meta->end->toDateString()))
            ->orderBy('delivery_date')->orderBy('id')
            ->get();

        foreach ($deliveries as $do) {
            if ($do->items->isEmpty()) {
                continue;
            }

            $status = self::STATUS[$do->status ?? ''] ?? ucfirst((string) $do->status);
            $first = null;

            foreach ($do->items as $item) {
                $qty = (float) ($item->qty ?? 0);
                $r = $w->row([
                    $do->delivery_date,
                    $do->code,
                    $do->requestOrder?->code ?? '-',
                    $do->owner?->name ?? '-',
                    $item->product?->code ?? '-',
                    $item->product?->name ?? '-',
                    $item->sku ?: null,
                    $item->expired_at,
                    $qty,
                    $item->product?->satuan ?? 'PCS',
                    Konversi::of($item->product, $qty),
                    $status,
                    $do->notes ?: null,
                ], ['cells' => array_filter([12 => Status::cell($status)])]);

                $first ??= $r;
            }

            $w->totalRow('Subtotal '.$do->code, [9 => 'sum'], [
                'from' => $first, 'to' => $w->currentRow() - 1, 'label_col' => 6, 'sub' => true,
            ]);
        }

        $w->totalRow('TOTAL', [9 => 'sum'], ['grand' => true, 'label_col' => 6]);
    }
}