<?php

namespace App\Exports\LaporanToko;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** E2 sheet 2: rekap per kategori (jumlah transaksi, total, % dari total). */
class OtherIncomeRekapSheet extends BaseLaporanTokoExport
{
    /** @param array<int,array{name:string,count:int,total:float}> $recap */
    public function __construct(private readonly ReportMeta $reportMeta, private readonly array $recap) {}

    protected function makeMeta(): ReportMeta
    {
        return $this->reportMeta;
    }

    protected function sheetName(): string
    {
        return 'Rekap per Kategori';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $w->columns([
            ['h' => 'Kategori', 'min' => 24, 'max' => 40],
            ['h' => 'Jumlah Transaksi', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Total', 'fmt' => Theme::FMT_RUPIAH],
            ['h' => '% dari Total', 'fmt' => Theme::FMT_PERCENT],
        ])->kop()->info()->header();

        $n = count($this->recap);
        if ($n === 0) {
            return;
        }

        // Baris TOTAL ada tepat setelah $n baris data; persen = total kategori / total keseluruhan (berumus).
        $totalRef = $w->ref(3, $w->currentRow() + $n);

        foreach ($this->recap as $item) {
            $own = $w->ref(3);
            $w->row([
                $item['name'],
                $item['count'],
                $item['total'],
                SheetWriter::f("IF({$totalRef}=0,0,{$own}/{$totalRef})"),
            ]);
        }

        $w->totalRow('TOTAL', [2 => 'sum', 3 => 'sum', 4 => 'sum'], ['grand' => true]);
    }
}