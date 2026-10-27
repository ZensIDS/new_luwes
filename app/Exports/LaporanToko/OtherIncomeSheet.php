<?php

namespace App\Exports\LaporanToko;

use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** E1 sheet 1: detail Pendapatan Lain, satu baris per income. Baris otomatis (Listing) diberi warna info. */
class OtherIncomeSheet extends BaseLaporanTokoExport
{
    public function __construct(private readonly ReportMeta $reportMeta, private readonly Builder $query) {}

    protected function makeMeta(): ReportMeta
    {
        return $this->reportMeta;
    }

    protected function sheetName(): string
    {
        return 'Pendapatan Lain';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $w->columns([
            ['h' => 'Tanggal', 'fmt' => Theme::FMT_DATE, 'align' => 'center'],
            ['h' => 'Kategori', 'max' => 30],
            ['h' => 'Outlet', 'max' => 30],
            ['h' => 'Sumber', 'align' => 'center'],
            ['h' => 'Keterangan', 'max' => 50, 'wrap' => true],
            ['h' => 'Sewa Dari', 'fmt' => Theme::FMT_DATE, 'align' => 'center'],
            ['h' => 'Sewa Sampai', 'fmt' => Theme::FMT_DATE, 'align' => 'center'],
            ['h' => 'Supplier', 'max' => 30],
            ['h' => 'Produk Sewa', 'max' => 40, 'wrap' => true],
            ['h' => 'Jumlah', 'fmt' => Theme::FMT_RUPIAH],
        ])->kop()->info()->header();

        $rows = (clone $this->query)
            ->with(['category', 'outlet', 'supplier', 'product'])
            ->orderBy('tanggal')
            ->orderBy('id')
            ->lazy(1000);

        $count = 0;
        foreach ($rows as $income) {
            $auto = $income->is_auto;
            $category = $income->category;

            $w->row([
                $income->tanggal,
                $category ? $category->name.($category->trashed() ? ' (dihapus)' : '') : '-',
                $income->outlet?->name ?? 'Umum',
                $auto ? 'Otomatis (Listing)' : 'Manual',
                $auto ? 'Produk: '.($income->product_name_snapshot ?? '-') : $income->keterangan,
                $income->sewa_mulai,
                $income->sewa_selesai,
                $income->supplier?->name,
                $income->product?->name ?? ($auto ? null : $income->product_name_snapshot),
                $income->jumlah,
            ], $auto ? ['fill' => Theme::INFO_BG, 'font' => Theme::INFO_FG] : []);

            $count++;
        }

        // Periode kosong: tanpa baris total supaya pesan "Tidak ada data" tampil.
        if ($count > 0) {
            $w->totalRow('TOTAL', [10 => 'sum'], ['grand' => true]);
        }
    }
}