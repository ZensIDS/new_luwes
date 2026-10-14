<?php

namespace App\Exports\LaporanGudang;

use App\Exports\LaporanToko\BaseLaporanTokoExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** B2 sheet 2: rekap per supplier (jumlah PO, total qty, total harga), diurutkan dari pembelian terbesar. */
class PembelianBarangRekapSheet extends BaseLaporanTokoExport
{
    public function __construct(private readonly Request $request, private readonly Collection $orders) {}

    protected function makeMeta(): ReportMeta
    {
        return ReportMeta::fromRequest($this->request, 'REKAP PEMBELIAN BARANG PER SUPPLIER', 'Pembelian-Barang');
    }

    protected function sheetName(): string
    {
        return 'Rekap Supplier';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $w->columns([
            ['h' => 'Supplier', 'min' => 28],
            ['h' => 'Jumlah PO', 'fmt' => Theme::FMT_NUMBER, 'min' => 12],
            ['h' => 'Total Qty', 'fmt' => Theme::FMT_NUMBER, 'min' => 12],
            ['h' => 'Total Harga', 'fmt' => Theme::FMT_RUPIAH, 'min' => 18],
        ])->kop()->info()->header();

        $rekap = $this->orders
            ->filter(fn ($po) => $po->pembelianProducts->isNotEmpty())
            ->groupBy(fn ($po) => $po->supplier?->name ?? '-')
            ->map(fn ($pos) => [
                'po' => $pos->count(),
                'qty' => $pos->sum(fn ($po) => $po->pembelianProducts->sum(fn ($pp) => (float) ($pp->qty ?? 0))),
                'total' => $pos->sum(fn ($po) => $po->pembelianProducts->sum(fn ($pp) => (float) ($pp->subtotal ?? 0))),
            ])
            ->sortByDesc('total');

        foreach ($rekap as $supplier => $row) {
            $w->row([(string) $supplier, $row['po'], (float) $row['qty'], (float) $row['total']]);
        }

        $w->totalRow('TOTAL', [2 => 'sum', 3 => 'sum', 4 => 'sum'], ['grand' => true]);
    }
}