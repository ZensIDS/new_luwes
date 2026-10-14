<?php

namespace App\Exports;

use App\Exports\LaporanToko\BaseLaporanTokoExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use App\Models\RefundPembelian;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** B7: Retur Toko ke Gudang (type outlet_ke_gudang). */
class ReturOutletExport extends BaseLaporanTokoExport
{
    public function __construct(private readonly Request $request) {}

    protected function makeMeta(): ReportMeta
    {
        return ReportMeta::fromRequest($this->request, 'LAPORAN RETUR TOKO KE GUDANG', 'Retur-Toko-ke-Gudang');
    }

    protected function sheetName(): string
    {
        return 'Retur Toko ke Gudang';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $w->columns([
            ['h' => 'No', 'fmt' => Theme::FMT_NUMBER, 'align' => 'center', 'w' => 6],
            ['h' => 'Tanggal', 'fmt' => Theme::FMT_DATE, 'align' => 'center'],
            ['h' => 'Kode Retur'],
            ['h' => 'Outlet'],
            ['h' => 'Kode Request'],
            ['h' => 'Kode Barang', 'text' => true],
            ['h' => 'Nama Barang', 'max' => 45],
            ['h' => 'Batch', 'text' => true],
            ['h' => 'Expired', 'fmt' => Theme::FMT_DATE, 'align' => 'center'],
            ['h' => 'Qty', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Satuan', 'align' => 'center'],
            ['h' => 'Konversi'],
            ['h' => 'Alasan Retur', 'max' => 40, 'wrap' => true],
            ['h' => 'Penyelesaian', 'align' => 'center'],
            ['h' => 'Status', 'align' => 'center'],
        ])->kop()->info()->header();

        $no = 0;
        $returs = RefundPembelian::with(['outlet', 'deliveryOrder', 'refundPembelianItems.product', 'refundPembelianItems.stock'])
            ->where('type', 'outlet_ke_gudang')
            ->tap(fn ($q) => \App\Support\ReportQuery::betweenDates($q, 'tanggal', $meta->start->toDateString(), $meta->end->toDateString()))
            ->orderBy('tanggal')->orderBy('id')
            ->get();

        (function () use ($returs, $w, &$no) {
                foreach ($returs as $retur) {
                    $done = $retur->status === 'complete';

                    foreach ($retur->refundPembelianItems as $item) {
                        $konversi = $item->product?->konversiDisplay($item->qty ?? 0) ?? '-';
                        $resolution = match ($item->resolution) {
                            'barang' => 'Barang',
                            'uang' => 'Uang',
                            default => null,
                        };

                        $w->row([
                            ++$no,
                            $retur->tanggal,
                            $retur->code,
                            $retur->outlet?->name ?? '-',
                            $retur->deliveryOrder?->code ?? '-',
                            $item->product?->code ?? '-',
                            $item->product?->name ?? '-',
                            $item->sku ?: null,
                            $item->stock?->expired_at ? \Carbon\Carbon::parse($item->stock->expired_at) : null,
                            (float) ($item->qty ?? 0),
                            $item->product?->satuan ?? 'PCS',
                            $konversi !== '-' ? $konversi : null,
                            $item->alasan ?: null,
                            $resolution,
                            $done ? 'Terkirim' : 'Proses',
                        ], ['cells' => [15 => $done
                            ? ['fill' => Theme::OK_BG, 'font' => Theme::OK_FG, 'bold' => true]
                            : ['fill' => Theme::WARN_BG, 'font' => Theme::WARN_FG, 'bold' => true]]]);
                    }
                }
        })();

        $w->totalRow('TOTAL QTY', [10 => 'sum'], ['grand' => true, 'label_col' => 7]);
    }
}