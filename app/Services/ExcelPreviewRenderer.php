<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Mengubah file Excel hasil export laporan menjadi tabel HTML untuk preview (tanpa mengunduh).
 * Memakai file yang SAMA dengan yang diunduh, jadi isi preview selalu persis sama dengan Excel-nya.
 * Hanya MAX_ROWS baris pertama per sheet yang dimuat (read filter) supaya ringan.
 */
class ExcelPreviewRenderer
{
    public const MAX_ROWS = 300;
    public const MAX_COLS = 60;

    /**
     * @return array<int, array{name:string, html:string, total_rows:int, shown_rows:int}>
     */
    public function render(string $path, int $maxRows = self::MAX_ROWS): array
    {
        $reader = IOFactory::createReaderForFile($path);

        $totals = [];
        if (method_exists($reader, 'listWorksheetInfo')) {
            foreach ($reader->listWorksheetInfo($path) as $info) {
                $totals[$info['worksheetName']] = (int) ($info['totalRows'] ?? 0);
            }
        }

        $reader->setReadFilter(new class($maxRows) implements IReadFilter {
            public function __construct(private int $max) {}

            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return $row <= $this->max;
            }
        });

        $spreadsheet = $reader->load($path);
        $sheets = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $name = $sheet->getTitle();
            $highestRow = $sheet->getHighestRow();
            $shown = min($highestRow, $maxRows);
            $total = max($totals[$name] ?? 0, $highestRow);

            $sheets[] = [
                'name' => $name,
                'html' => $this->sheetHtml($sheet, $shown),
                'total_rows' => $total,
                'shown_rows' => $shown,
            ];
        }

        $spreadsheet->disconnectWorksheets();

        return $sheets;
    }

    private function sheetHtml(Worksheet $sheet, int $maxRow): string
    {
        $maxCol = min(Coordinate::columnIndexFromString($sheet->getHighestColumn()), self::MAX_COLS);

        // Sel gabungan: sel awal membawa rowspan/colspan, sel lain di dalam rentang dilewati.
        $span = [];
        $skip = [];
        foreach ($sheet->getMergeCells() as $range) {
            [$from, $to] = Coordinate::rangeBoundaries($range);
            if ($from[1] > $maxRow) {
                continue;
            }
            $rows = min($to[1], $maxRow) - $from[1] + 1;
            $cols = min($to[0], $maxCol) - $from[0] + 1;
            $span[$from[0] . ':' . $from[1]] = [$rows, $cols];
            for ($r = $from[1]; $r <= min($to[1], $maxRow); $r++) {
                for ($c = $from[0]; $c <= min($to[0], $maxCol); $c++) {
                    if ($r !== $from[1] || $c !== $from[0]) {
                        $skip[$c . ':' . $r] = true;
                    }
                }
            }
        }

        $html = '<table class="rpt-pv-table"><tbody>';
        for ($row = 1; $row <= $maxRow; $row++) {
            if (! $sheet->getRowDimension($row)->getVisible()) {
                continue;
            }

            $cells = '';
            $hasContent = false;
            for ($col = 1; $col <= $maxCol; $col++) {
                if (isset($skip[$col . ':' . $row])) {
                    continue;
                }
                if (! $sheet->getColumnDimensionByColumn($col)->getVisible()) {
                    continue;
                }

                $text = '';
                $style = '';
                $coord = Coordinate::stringFromColumnIndex($col) . $row;
                if ($sheet->cellExists($coord)) {
                    $cell = $sheet->getCell($coord);
                    $text = $this->cellText($cell);
                    $style = $this->cellStyle($cell, $text);
                }
                if ($text !== '') {
                    $hasContent = true;
                }

                $attr = '';
                if (isset($span[$col . ':' . $row])) {
                    [$rs, $cs] = $span[$col . ':' . $row];
                    $attr .= ($rs > 1 ? ' rowspan="' . $rs . '"' : '') . ($cs > 1 ? ' colspan="' . $cs . '"' : '');
                }

                $cells .= '<td' . $attr . ($style !== '' ? ' style="' . $style . '"' : '') . '>' . nl2br(e($text)) . '</td>';
            }

            $html .= '<tr' . ($hasContent ? '' : ' class="rpt-pv-blank"') . '>' . $cells . '</tr>';
        }

        return $html . '</tbody></table>';
    }

    private function cellText($cell): string
    {
        try {
            $text = (string) $cell->getFormattedValue();
        } catch (\Throwable) {
            $text = (string) $cell->getValue();
        }

        // Rumus yang tidak bisa dihitung di preview: tampilkan kosong, bukan teks rumus.
        return str_starts_with($text, '=') ? '' : $text;
    }

    private function cellStyle($cell, string $text): string
    {
        $style = $cell->getStyle();
        $css = [];

        $font = $style->getFont();
        if ($font->getBold()) {
            $css[] = 'font-weight:700';
        }
        if ($font->getItalic()) {
            $css[] = 'font-style:italic';
        }
        $color = $this->color($font->getColor()->getARGB());
        if ($color && $color !== '#000000') {
            $css[] = 'color:' . $color;
        }

        $fill = $style->getFill();
        if ($fill->getFillType() === Fill::FILL_SOLID) {
            $bg = $this->color($fill->getStartColor()->getARGB());
            if ($bg && $bg !== '#FFFFFF') {
                $css[] = 'background:' . $bg;
            }
        }

        $h = $style->getAlignment()->getHorizontal();
        if (in_array($h, [Alignment::HORIZONTAL_CENTER, Alignment::HORIZONTAL_CENTER_CONTINUOUS], true)) {
            $css[] = 'text-align:center';
        } elseif ($h === Alignment::HORIZONTAL_RIGHT) {
            $css[] = 'text-align:right';
        } elseif ($h === Alignment::HORIZONTAL_LEFT) {
            $css[] = 'text-align:left';
        } elseif ($text !== '' && preg_match('/^-?[\d.,]+(\s?%)?$/', trim($text)) === 1 && ! is_string($cell->getValue())) {
            $css[] = 'text-align:right';
        }

        return implode(';', $css);
    }

    private function color(?string $argb): ?string
    {
        if (! $argb || strlen($argb) < 6 || ! preg_match('/^[0-9A-Fa-f]{6,8}$/', $argb)) {
            return null;
        }

        return '#' . strtoupper(substr($argb, -6));
    }
}
