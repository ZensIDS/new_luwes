<?php

namespace App\Exports\LaporanToko;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithProperties;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Kelas dasar satu sheet Laporan Toko (A1).
 *
 * Turunan cukup mengisi tiga hal:
 *   - makeMeta()  : ReportMeta (judul, periode, filter, nama file)
 *   - sheetName() : nama tab sheet
 *   - build()     : isi sheet memakai SheetWriter
 *
 * Untuk laporan lebih dari satu sheet, bungkus dengan WithMultipleSheets
 * dan kembalikan beberapa turunan kelas ini (data cukup dihitung sekali di pembungkus).
 */
abstract class BaseLaporanTokoExport implements WithTitle, WithProperties, WithEvents
{
    private ?ReportMeta $meta = null;

    abstract protected function makeMeta(): ReportMeta;

    /** Dibuat sekali per export supaya query label filter dan waktu cetak tidak berulang. */
    protected function meta(): ReportMeta
    {
        return $this->meta ??= $this->makeMeta();
    }

    abstract protected function sheetName(): string;

    abstract protected function build(SheetWriter $writer, Worksheet $sheet, ReportMeta $meta): void;

    public function fileName(): string
    {
        return $this->meta()->fileName();
    }

    public function title(): string
    {
        // Excel: maksimal 31 karakter dan tanpa  \ / ? * [ ] :
        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '-', $this->sheetName()), 0, 31);
    }

    public function properties(): array
    {
        return $this->meta()->properties();
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $writer = new SheetWriter($sheet, $this->meta());

                $this->build($writer, $sheet, $this->meta());
                $writer->finish();
            },
        ];
    }
}