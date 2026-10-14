<?php

namespace App\Exports;

use App\Exports\LaporanGudang\Konversi;
use App\Exports\LaporanGudang\PembelianBarangSheet;
use App\Exports\LaporanGudang\Status;
use App\Exports\LaporanToko\BaseLaporanTokoExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use App\Models\Pembelian;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * B4: Laporan Penerimaan Barang, satu baris per barang pada PO yang sudah diterima.
 * Qty diterima diambil dari item PO (`qty_diterima`); batch, expired, dan kondisi dari stok PO tersebut.
 */
class LaporanPenerimaanBarangExport extends BaseLaporanTokoExport
{
    private const CONDITION = ['new' => 'Baru', 'used' => 'Bekas', 'refurbished' => 'Refurbished'];

    public function __construct(private readonly Request $request) {}

    protected function makeMeta(): ReportMeta
    {
        return ReportMeta::fromRequest($this->request, 'LAPORAN PENERIMAAN BARANG', 'Penerimaan-Barang');
    }

    protected function sheetName(): string
    {
        return 'Penerimaan Barang';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $w->columns([
            ['h' => 'Tanggal Terima', 'fmt' => Theme::FMT_DATE, 'align' => 'center'],
            ['h' => 'No Dokumen'],
            ['h' => 'Kode PO'],
            ['h' => 'Supplier'],
            ['h' => 'Kode Barang', 'text' => true],
            ['h' => 'Nama Barang', 'max' => 45],
            ['h' => 'Batch', 'text' => true, 'max' => 30],
            ['h' => 'Expired', 'fmt' => Theme::FMT_DATE, 'align' => 'center'],
            ['h' => 'Qty PO', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Qty Diterima', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Selisih', 'fmt' => '+#,##0;-#,##0;0'],
            ['h' => 'Satuan', 'align' => 'center'],
            ['h' => 'Konversi'],
            ['h' => 'Kondisi', 'align' => 'center'],
            ['h' => 'Status Penerimaan', 'align' => 'center', 'min' => 16],
        ])->kop()->info()->header();

        $start = $meta->start;
        $end = $meta->end;

        // Tanggal terima = receipt_date; bila kosong, memakai tanggal PO dibuat.
        $orders = Pembelian::with(['supplier', 'pembelianProducts.product', 'stocks'])
            ->whereHas('pembelianProducts', fn ($q) => $q->where('qty_diterima', '>', 0))
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('receipt_date', [$start, $end])
                    ->orWhere(fn ($q2) => $q2->whereNull('receipt_date')->whereBetween('created_at', [$start, $end]));
            })
            ->orderByRaw('COALESCE(receipt_date, created_at)')->orderBy('id')
            ->get();

        foreach ($orders as $po) {
            $date = $po->receipt_date ?? $po->created_at;
            $doc = $po->code_gr ?: $po->code;
            $receipt = PembelianBarangSheet::RECEIPT[$po->receipt_status ?? 'draft'] ?? ucfirst((string) $po->receipt_status);
            $stocksByProduct = $po->stocks->groupBy('product_id');

            foreach ($po->pembelianProducts as $pp) {
                if ((float) ($pp->qty_diterima ?? 0) <= 0) {
                    continue;
                }

                $r = $w->currentRow();
                $qty = (float) ($pp->qty ?? 0);
                $received = (float) $pp->qty_diterima;
                $batches = $stocksByProduct->get($pp->product_id, collect());

                $expired = $batches->pluck('expired_at')->filter()->sort()->first();
                $conditions = $batches->pluck('condition')->filter()->unique()
                    ->map(fn ($c) => self::CONDITION[$c] ?? ucfirst((string) $c))->implode(', ');
                $diff = $received - $qty;

                $w->row([
                    $date, $doc, $po->code, $po->supplier?->name ?? '-',
                    $pp->product?->code ?? '-', $pp->product?->name ?? '-',
                    $batches->pluck('sku')->filter()->unique()->implode(', ') ?: null,
                    $expired,
                    $qty, $received,
                    SheetWriter::f("J{$r}-I{$r}"),
                    $pp->product?->satuan ?? 'PCS',
                    Konversi::of($pp->product, $received),
                    $conditions ?: null,
                    $receipt,
                ], ['cells' => array_filter([
                    11 => $diff < 0 ? ['font' => Theme::DANGER_FG, 'bold' => true] : ($diff > 0 ? ['font' => Theme::WARN_FG, 'bold' => true] : null),
                    15 => Status::cell($receipt),
                ])]);
            }
        }

        $w->totalRow('TOTAL', [9 => 'sum', 10 => 'sum', 11 => 'sum'], ['grand' => true, 'label_col' => 6]);
    }
}