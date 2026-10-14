<?php

namespace App\Exports;

use App\Exports\LaporanGudang\Konversi;
use App\Exports\LaporanGudang\Status;
use App\Exports\LaporanToko\BaseLaporanTokoExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use App\Models\Pembelian;
use App\Support\ReportQuery;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * B1: Laporan PO, dikelompokkan per PO dengan subtotal.
 * Kolom "No PR" dihapus (PO dan PR tidak punya relasi di database).
 */
class LaporanPOExport extends BaseLaporanTokoExport
{
    private const RECEIPT = ['draft' => 'Draft', 'validated' => 'Divalidasi', 'completed' => 'Selesai'];

    public function __construct(private readonly Request $request) {}

    protected function makeMeta(): ReportMeta
    {
        return ReportMeta::fromRequest($this->request, 'LAPORAN PO (PURCHASE ORDER)', 'PO');
    }

    protected function sheetName(): string
    {
        return 'Laporan PO';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $w->columns([
            ['h' => 'No', 'fmt' => Theme::FMT_NUMBER, 'align' => 'center', 'w' => 6],
            ['h' => 'Tanggal PO', 'fmt' => Theme::FMT_DATE, 'align' => 'center'],
            ['h' => 'Kode PO'],
            ['h' => 'Supplier'],
            ['h' => 'Kode Barang', 'text' => true],
            ['h' => 'Nama Barang', 'max' => 45],
            ['h' => 'Qty', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Satuan', 'align' => 'center'],
            ['h' => 'Konversi'],
            ['h' => 'Harga Satuan', 'fmt' => Theme::FMT_RUPIAH, 'min' => 14],
            ['h' => 'Harga Total', 'fmt' => Theme::FMT_RUPIAH, 'min' => 16],
            ['h' => 'Qty Diterima', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Belum Diterima', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Status Penerimaan', 'align' => 'center', 'min' => 16],
            ['h' => 'Persetujuan Owner', 'align' => 'center', 'min' => 18],
            ['h' => 'Keterangan', 'max' => 40, 'wrap' => true],
        ])->kop()->info()->header();

        $orders = Pembelian::with(['supplier', 'pembelianProducts.product'])
            ->tap(fn ($q) => ReportQuery::betweenDates($q, 'created_at', $meta->start->toDateString(), $meta->end->toDateString()))
            ->orderBy('created_at')->orderBy('id')
            ->get();

        $no = 0;
        foreach ($orders as $po) {
            if ($po->pembelianProducts->isEmpty()) {
                continue;
            }

            $receipt = self::RECEIPT[$po->receipt_status ?? 'draft'] ?? ucfirst((string) $po->receipt_status);
            $approval = Pembelian::OWNER_APPROVAL_STATUSES[$po->owner_approval_status] ?? ucfirst((string) $po->owner_approval_status);
            $first = null;

            foreach ($po->pembelianProducts as $pp) {
                $r = $w->currentRow();
                $qty = (float) ($pp->qty ?? 0);
                $received = (float) ($pp->qty_diterima ?? 0);

                $cells = array_filter([
                    14 => Status::cell($receipt),
                    15 => Status::cell($approval),
                    13 => $qty - $received > 0 ? ['font' => Theme::WARN_FG, 'bold' => true] : null,
                ]);

                $w->row([
                    ++$no,
                    $po->created_at,
                    $po->code,
                    $po->supplier?->name ?? '-',
                    $pp->product?->code ?? '-',
                    $pp->product?->name ?? '-',
                    $qty,
                    $pp->product?->satuan ?? 'PCS',
                    Konversi::of($pp->product, $qty),
                    (float) ($pp->harga_beli ?? 0),
                    (float) ($pp->subtotal ?? 0),
                    $received,
                    SheetWriter::f("G{$r}-L{$r}"),
                    $receipt,
                    $approval,
                    $po->owner_approval_note ?: null,
                ], ['cells' => $cells]);

                $first ??= $r;
            }

            $w->totalRow('Subtotal '.$po->code, [7 => 'sum', 11 => 'sum', 12 => 'sum', 13 => 'sum'], [
                'from' => $first, 'to' => $w->currentRow() - 1, 'label_col' => 6, 'sub' => true,
            ]);
        }

        $w->totalRow('TOTAL', [7 => 'sum', 11 => 'sum', 12 => 'sum', 13 => 'sum'], ['grand' => true, 'label_col' => 6]);
    }
}