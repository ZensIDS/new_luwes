<?php

namespace App\Exports\LaporanGudang;

use App\Exports\LaporanToko\BaseLaporanTokoExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use App\Models\Pembelian;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** B2 sheet 1: satu baris per barang per PO (datar, siap difilter/pivot). */
class PembelianBarangSheet extends BaseLaporanTokoExport
{
    public const RECEIPT = ['draft' => 'Draft', 'validated' => 'Divalidasi', 'completed' => 'Selesai'];

    public function __construct(private readonly Request $request, private readonly Collection $orders) {}

    protected function makeMeta(): ReportMeta
    {
        return ReportMeta::fromRequest($this->request, 'LAPORAN PEMBELIAN BARANG', 'Pembelian-Barang');
    }

    protected function sheetName(): string
    {
        return 'Pembelian Barang';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $w->columns([
            ['h' => 'Tanggal', 'fmt' => Theme::FMT_DATE, 'align' => 'center'],
            ['h' => 'Kode PO'],
            ['h' => 'Supplier'],
            ['h' => 'Kode Barang', 'text' => true],
            ['h' => 'Nama Barang', 'max' => 45],
            ['h' => 'Qty', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Satuan', 'align' => 'center'],
            ['h' => 'Konversi'],
            ['h' => 'Harga Satuan', 'fmt' => Theme::FMT_RUPIAH, 'min' => 14],
            ['h' => 'Total Harga', 'fmt' => Theme::FMT_RUPIAH, 'min' => 16],
            ['h' => 'Status Penerimaan', 'align' => 'center', 'min' => 16],
            ['h' => 'Persetujuan Owner', 'align' => 'center', 'min' => 18],
            ['h' => 'Keterangan', 'max' => 40, 'wrap' => true],
        ])->kop()->info()->header();

        foreach ($this->orders as $po) {
            $receipt = self::RECEIPT[$po->receipt_status ?? 'draft'] ?? ucfirst((string) $po->receipt_status);
            $approval = Pembelian::OWNER_APPROVAL_STATUSES[$po->owner_approval_status] ?? ucfirst((string) $po->owner_approval_status);

            foreach ($po->pembelianProducts as $pp) {
                $qty = (float) ($pp->qty ?? 0);

                $w->row([
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
                    $receipt,
                    $approval,
                    $po->owner_approval_note ?: null,
                ], ['cells' => array_filter([11 => Status::cell($receipt), 12 => Status::cell($approval)])]);
            }
        }

        $w->totalRow('TOTAL', [6 => 'sum', 10 => 'sum'], ['grand' => true, 'label_col' => 5]);
    }
}