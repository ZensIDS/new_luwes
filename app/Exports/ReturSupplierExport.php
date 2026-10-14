<?php

namespace App\Exports;

use App\Exports\LaporanGudang\Konversi;
use App\Exports\LaporanGudang\Status;
use App\Exports\LaporanToko\BaseLaporanTokoExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use App\Models\RefundPembelian;
use App\Support\ReportQuery;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** B3: Laporan Retur ke Supplier (type gudang_ke_supplier). */
class ReturSupplierExport extends BaseLaporanTokoExport
{
    public function __construct(private readonly Request $request) {}

    protected function makeMeta(): ReportMeta
    {
        return ReportMeta::fromRequest($this->request, 'LAPORAN RETUR KE SUPPLIER', 'Retur-Supplier');
    }

    protected function sheetName(): string
    {
        return 'Retur ke Supplier';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $w->columns([
            ['h' => 'No', 'fmt' => Theme::FMT_NUMBER, 'align' => 'center', 'w' => 6],
            ['h' => 'Tanggal', 'fmt' => Theme::FMT_DATE, 'align' => 'center'],
            ['h' => 'Kode Retur'],
            ['h' => 'Kode PO'],
            ['h' => 'Supplier'],
            ['h' => 'Kode Barang', 'text' => true],
            ['h' => 'Nama Barang', 'max' => 45],
            ['h' => 'Batch', 'text' => true],
            ['h' => 'Qty Retur', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Satuan', 'align' => 'center'],
            ['h' => 'Konversi'],
            ['h' => 'Harga Satuan', 'fmt' => Theme::FMT_RUPIAH, 'min' => 14],
            ['h' => 'Nilai Retur', 'fmt' => Theme::FMT_RUPIAH, 'min' => 16],
            ['h' => 'Alasan Retur', 'max' => 40, 'wrap' => true],
            ['h' => 'Penyelesaian', 'align' => 'center'],
            ['h' => 'Status', 'align' => 'center'],
        ])->kop()->info()->header();

        $returs = RefundPembelian::with(['supplier', 'refundPembelianItems.product', 'refundPembelianItems.stock.pembelian'])
            ->where('type', 'gudang_ke_supplier')
            ->tap(fn ($q) => ReportQuery::betweenDates($q, 'tanggal', $meta->start->toDateString(), $meta->end->toDateString()))
            ->orderBy('tanggal')->orderBy('id')
            ->get();

        $no = 0;
        foreach ($returs as $retur) {
            $status = $retur->status === 'retur' ? 'Proses' : 'Selesai';

            foreach ($retur->refundPembelianItems as $item) {
                $r = $w->currentRow();
                $qty = (float) ($item->qty ?? 0);
                $harga = (float) ($item->harga ?? 0);
                $resolution = match ($item->resolution) {
                    'barang' => 'Barang',
                    'uang' => 'Uang',
                    default => null,
                };

                $w->row([
                    ++$no,
                    $retur->tanggal,
                    $retur->code,
                    $item->stock?->pembelian?->code ?? '-',
                    $retur->supplier?->name ?? '-',
                    $item->product?->code ?? '-',
                    $item->product?->name ?? '-',
                    $item->sku ?: null,
                    $qty,
                    $item->product?->satuan ?? 'PCS',
                    Konversi::of($item->product, $qty),
                    $harga > 0 ? $harga : null,
                    $harga > 0 ? SheetWriter::f("I{$r}*L{$r}") : null,
                    $item->alasan ?: null,
                    $resolution,
                    $status,
                ], ['cells' => array_filter([16 => Status::cell($status)])]);
            }
        }

        $w->totalRow('TOTAL', [9 => 'sum', 13 => 'sum'], ['grand' => true, 'label_col' => 7]);
    }
}