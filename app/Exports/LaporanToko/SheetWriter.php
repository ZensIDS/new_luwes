<?php

namespace App\Exports\LaporanToko;

use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Penulis sheet bergaya seragam untuk Laporan Toko.
 *
 * Urutan pemakaian:
 *   $w = new SheetWriter($sheet, $meta);
 *   $w->columns([...])->kop()->info()->header();
 *   $w->row([...]); $w->section('...'); $w->totalRow('TOTAL', [8 => 'sum'], ['grand' => true]);
 *   $w->finish();
 *
 * Definisi kolom (1 elemen per kolom):
 *   'h'     judul kolom (wajib)
 *   'fmt'   format angka, lihat Theme::FMT_*
 *   'align' left | center | right (default: right bila ada 'fmt' angka, selain itu left)
 *   'text'  true = paksa teks (barcode, kode) agar tidak jadi notasi ilmiah
 *   'wrap'  true = teks dibungkus
 *   'w'     lebar tetap; tanpa ini lebar dihitung otomatis dari isi
 *   'min' / 'max'  batas lebar otomatis (default 8 / 40)
 *
 * Nomor kolom pada semua method bersifat 1-based (kolom A = 1).
 */
final class SheetWriter
{
    private int $row = 1;
    private int $colCount = 0;
    /** @var array<int,array<string,mixed>> index 1-based */
    private array $cols = [];
    /** @var array<int,int> */
    private array $maxLen = [];

    private ?int $groupRow = null;
    private ?int $headerRow = null;
    private ?int $firstData = null;
    private ?int $lastBodyRow = null;
    private ?int $tableEnd = null;
    private int $dataCount = 0;

    /** Style yang harus dipasang setelah format kolom (alignment/format angka khusus). */
    private array $post = [];

    public function __construct(private readonly Worksheet $sheet, private readonly ReportMeta $meta)
    {
        $this->sheet->getParent()?->getDefaultStyle()->getFont()
            ->setName(Theme::FONT)->setSize(Theme::FONT_SIZE);
    }

    /** Tandai nilai sebagai rumus Excel: SheetWriter::f('H12*I12'). */
    public static function f(string $expression): array
    {
        return ['f' => ltrim($expression, '=')];
    }

    public function columns(array $cols): static
    {
        $this->cols = [];
        $i = 1;
        foreach ($cols as $def) {
            $this->cols[$i] = $def;
            $words = preg_split('/\s+/', (string) $def['h']) ?: [''];
            $longest = max(array_map('mb_strlen', $words));
            $this->maxLen[$i] = max($longest, min(mb_strlen((string) $def['h']), 14));
            $i++;
        }
        $this->colCount = count($this->cols);

        return $this;
    }

    public function col(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index);
    }

    /** Alamat sel, mis. ref(8, 12) => "H12"; tanpa $row = baris yang sedang ditulis. */
    public function ref(int $col, ?int $row = null): string
    {
        return $this->col($col).($row ?? $this->row);
    }

    public function currentRow(): int
    {
        return $this->row;
    }

    public function firstDataRow(): ?int
    {
        return $this->firstData;
    }

    // ── A2: kop ─────────────────────────────────────────────────────────────

    public function kop(): static
    {
        $settings = $this->meta->settings;
        $logo = $this->colCount >= 4 && $this->addLogo();
        $from = $logo ? 3 : 1;

        $contact = implode('  |  ', array_filter([
            ! empty($settings['telp']) ? 'Telp: '.$settings['telp'] : null,
            ! empty($settings['email']) ? 'Email: '.$settings['email'] : null,
            ! empty($settings['website']) ? $settings['website'] : null,
        ]));

        $lines = [
            [1, (string) ($settings['name'] ?? config('app.name')), ['bold' => true, 'size' => 16, 'color' => Theme::PRIMARY], 24],
            [2, (string) ($settings['address'] ?? ''), ['color' => Theme::MUTED], 16],
            [3, $contact, ['color' => Theme::MUTED], 16],
        ];

        foreach ($lines as [$r, $text, $font, $height]) {
            if ($text !== '') {
                $this->str($this->col($from).$r, $text);
                $this->style($this->col($from).$r, ['font' => $this->font($font)]);
            }
            $this->sheet->getRowDimension($r)->setRowHeight($height);
        }

        $this->style('A3:'.$this->col($this->colCount).'3', [
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => Theme::PRIMARY]]],
        ]);

        $this->sheet->getRowDimension(4)->setRowHeight(6);
        $this->row = 5;

        return $this;
    }

    // ── A3: judul + blok informasi ──────────────────────────────────────────

    public function info(): static
    {
        $this->str('A'.$this->row, $this->meta->title);
        $this->style('A'.$this->row, ['font' => $this->font(['bold' => true, 'size' => 14, 'color' => Theme::PRIMARY])]);
        $this->sheet->getRowDimension($this->row)->setRowHeight(22);
        $this->row++;

        $first = $this->meta->snapshot
            ? ['Per Tanggal' => (string) $this->meta->snapshotLabel()]
            : ['Periode' => $this->meta->periodLabel()];

        $items = $first
            + $this->meta->info
            + ['Waktu Cetak' => $this->meta->printedAtLabel(), 'Dicetak Oleh' => $this->meta->printedBy];

        $labels = array_keys($items);
        $two = $this->colCount >= 6;
        $half = $two ? (int) ceil(count($items) / 2) : count($items);
        $rightCol = max(3, intdiv($this->colCount, 2) + 1);

        foreach ($labels as $n => $label) {
            $inRight = $two && $n >= $half;
            $col = $inRight ? $rightCol : 1;
            $r = $this->row + ($inRight ? $n - $half : $n);
            $this->richInfo($this->col($col).$r, $label, (string) $items[$label]);
        }

        $this->row += $half;
        $this->sheet->getRowDimension($this->row)->setRowHeight(6);
        $this->row++;

        return $this;
    }

    // ── A5: header tabel ────────────────────────────────────────────────────

    /** Baris pengelompokan kolom di atas header. $groups = [['label' => 'Produk', 'from' => 1, 'to' => 4], ...] */
    public function groupHeader(array $groups): static
    {
        $r = $this->row;
        $range = 'A'.$r.':'.$this->col($this->colCount).$r;

        $this->style($range, [
            'fill' => $this->fill(Theme::SECONDARY),
            'font' => $this->font(['bold' => true, 'color' => Theme::PRIMARY]),
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => $this->borders(),
        ]);

        foreach ($groups as $g) {
            $this->str($this->col($g['from']).$r, (string) $g['label']);
            if ($g['to'] > $g['from']) {
                $this->sheet->mergeCells($this->col($g['from']).$r.':'.$this->col($g['to']).$r);
            }
        }

        $this->sheet->getRowDimension($r)->setRowHeight(20);
        $this->groupRow = $r;
        $this->row++;

        return $this;
    }

    public function header(): static
    {
        $r = $this->row;
        foreach ($this->cols as $i => $def) {
            $this->str($this->col($i).$r, (string) $def['h']);
        }

        $this->style('A'.$r.':'.$this->col($this->colCount).$r, [
            'fill' => $this->fill(Theme::PRIMARY),
            'font' => $this->font(['bold' => true, 'color' => Theme::ON_PRIMARY]),
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => $this->borders(),
        ]);
        $this->sheet->getRowDimension($r)->setRowHeight(30);

        $this->headerRow = $r;
        $this->firstData = $r + 1;
        $this->row++;

        return $this;
    }

    // ── isi tabel ───────────────────────────────────────────────────────────

    /**
     * Baris data. $values berurutan sesuai kolom (null = kosong).
     * Opsi: 'fill' / 'font' (hex) / 'bold' untuk satu baris penuh; 'fmt' => [kolom => format] menimpa format kolom;
     *       'cells' => [nomorKolom => ['fill' => hex, 'font' => hex, 'bold' => bool]] untuk sel tertentu.
     */
    public function row(array $values, array $opts = []): int
    {
        $r = $this->row++;
        $this->dataCount++;

        foreach (array_values($values) as $n => $v) {
            $this->put($n + 1, $r, $v);
        }

        $range = 'A'.$r.':'.$this->col($this->colCount).$r;
        if (isset($opts['fill']) || isset($opts['font']) || ! empty($opts['bold'])) {
            $this->style($range, $this->rowStyle($opts));
        } elseif ($this->dataCount % 2 === 0) {
            $this->style($range, ['fill' => $this->fill(Theme::ZEBRA)]);
        }

        foreach ($opts['cells'] ?? [] as $col => $cellOpts) {
            $this->style($this->col($col).$r, $this->rowStyle($cellOpts));
        }

        foreach ($opts['fmt'] ?? [] as $col => $fmt) {
            $this->post[] = [$this->col($col).$r, ['numberFormat' => ['formatCode' => $fmt]]];
        }

        $this->lastBodyRow = $r;

        return $r;
    }

    /** Baris pemisah kelompok (mis. nama outlet / periode rafaksi), tanpa merge agar tetap bisa difilter. */
    public function section(string $label, array $opts = []): int
    {
        $r = $this->row++;
        $this->str($this->col(1).$r, $label);
        $this->post[] = [$this->col(1).$r, ['alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]]];
        $this->style('A'.$r.':'.$this->col($this->colCount).$r, [
            'fill' => $this->fill($opts['fill'] ?? Theme::SECONDARY),
            'font' => $this->font(['bold' => true, 'color' => $opts['font'] ?? Theme::PRIMARY]),
        ]);
        $this->lastBodyRow = $r;

        return $r;
    }

    public function blank(float $height = 6): static
    {
        $this->sheet->getRowDimension($this->row)->setRowHeight($height);
        $this->row++;

        return $this;
    }

    /**
     * Baris total/subtotal memakai rumus (A7). Rumus SUBTOTAL ikut berubah saat data difilter
     * dan mengabaikan SUBTOTAL lain di dalam rentang, jadi aman dipakai untuk total keseluruhan.
     *
     * $calc: [nomorKolom => 'sum' | 'count' | 'avg' | SheetWriter::f('...') | angka]
     * Opsi: 'from','to' (rentang baris, default seluruh data), 'label_col' (default 1),
     *       'grand' (true = total akhir; baris ini dikeluarkan dari autofilter),
     *       'sub' (true = subtotal ringan per kelompok, bukan total akhir),
     *       'fmt' => [nomorKolom => format] untuk menimpa format kolom.
     */
    public function totalRow(string $label, array $calc, array $opts = []): int
    {
        $r = $this->row++;
        $from = $opts['from'] ?? $this->firstData ?? $r;
        $to = $opts['to'] ?? ($r - 1);
        $hasRange = $to >= $from;

        $labelCol = $opts['label_col'] ?? 1;
        $this->str($this->col($labelCol).$r, $label);
        $this->post[] = [$this->col($labelCol).$r, ['alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]]];

        foreach ($calc as $col => $what) {
            $coord = $this->col($col).$r;
            // Nilai total biasanya lebih besar dari isi baris: sisakan lebar agar tidak jadi ####.
            $this->track($col, str_contains((string) ($this->cols[$col]['fmt'] ?? ''), 'Rp') ? 17 : 12);
            $range = $this->col($col).$from.':'.$this->col($col).$to;

            if (is_array($what)) {
                $this->sheet->setCellValue($coord, '='.$what['f']);
            } elseif (is_string($what)) {
                $fn = ['sum' => 109, 'count' => 103, 'avg' => 101][$what] ?? 109;
                if ($hasRange) {
                    $this->sheet->setCellValue($coord, "=SUBTOTAL({$fn},{$range})");
                } else {
                    $this->sheet->setCellValueExplicit($coord, 0, DataType::TYPE_NUMERIC);
                }
            } else {
                $this->sheet->setCellValueExplicit($coord, $what, DataType::TYPE_NUMERIC);
            }
        }

        foreach ($opts['fmt'] ?? [] as $col => $fmt) {
            $this->post[] = [$this->col($col).$r, ['numberFormat' => ['formatCode' => $fmt]]];
        }

        $sub = ! empty($opts['sub']);
        $this->style('A'.$r.':'.$this->col($this->colCount).$r, [
            'fill' => $this->fill($sub ? Theme::SECONDARY : Theme::TOTAL_BG),
            'font' => $this->font(['bold' => true, 'color' => Theme::PRIMARY]),
            'borders' => $sub ? [] : ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => Theme::PRIMARY]]],
        ]);

        if (! empty($opts['grand'])) {
            $this->tableEnd = $this->lastBodyRow ?? ($r - 1);
        } else {
            $this->lastBodyRow = $r;
        }

        return $r;
    }

    // ── A5, A6: penutup (format kolom, lebar, filter, freeze, cetak) ───────

    public function finish(): void
    {
        if ($this->headerRow === null) {
            return;
        }

        if ($this->dataCount === 0 && $this->row === $this->firstData) {
            $this->str('A'.$this->row, 'Tidak ada data pada periode / filter yang dipilih.');
            $this->post[] = ['A'.$this->row, ['alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]]];
            $this->style('A'.$this->row, ['font' => $this->font(['italic' => true, 'color' => Theme::MUTED])]);
            $this->row++;
        }

        $h = $this->headerRow;
        $first = $this->firstData;
        $lastRow = $this->row - 1;
        $tableEnd = $this->tableEnd ?? $lastRow;
        $lastL = $this->col($this->colCount);

        // Format + perataan per kolom (satu panggilan per kolom, bukan per sel).
        foreach ($this->cols as $i => $def) {
            $L = $this->col($i);
            $numeric = isset($def['fmt']) && $def['fmt'] !== Theme::FMT_TEXT;
            $align = $def['align'] ?? ($numeric ? 'right' : 'left');

            $style = ['alignment' => [
                'horizontal' => $align,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => ! empty($def['wrap']),
            ]];
            if (isset($def['fmt'])) {
                $style['numberFormat'] = ['formatCode' => $def['fmt']];
            } elseif (! empty($def['text'])) {
                $style['numberFormat'] = ['formatCode' => Theme::FMT_TEXT];
            }

            if ($lastRow >= $first) {
                $this->style("{$L}{$first}:{$L}{$lastRow}", $style);
            }

            $width = $def['w'] ?? max(
                $def['min'] ?? 8,
                min($def['max'] ?? 40, (int) ceil(($this->maxLen[$i] ?? 8) * 1.15) + 2)
            );
            $this->sheet->getColumnDimension($L)->setWidth($width);
        }

        foreach ($this->post as [$range, $style]) {
            $this->style($range, $style);
        }

        // Border tipis seluruh tabel.
        $this->style("A{$h}:{$lastL}{$lastRow}", ['borders' => $this->borders()]);

        // Filter + freeze pane di bawah header.
        if ($tableEnd > $h) {
            $this->sheet->setAutoFilter("A{$h}:{$lastL}{$tableEnd}");
        }
        $this->sheet->freezePane('A'.($h + 1));

        // Tampilan.
        $this->sheet->setShowGridlines(false);
        $this->sheet->getSheetView()->setZoomScale(95);
        $this->sheet->getTabColor()->setRGB(Theme::PRIMARY);

        // Cetak: landscape A4, muat satu halaman lebar, header berulang.
        $page = $this->sheet->getPageSetup();
        $page->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setHorizontalCentered(true)
            ->setPrintArea("A1:{$lastL}{$lastRow}")
            ->setRowsToRepeatAtTopByStartAndEnd($this->groupRow ?? $h, $h);

        $this->sheet->getPageMargins()->setTop(0.5)->setBottom(0.6)->setLeft(0.4)->setRight(0.4);
        $this->sheet->getHeaderFooter()->setOddFooter(
            '&L&8'.str_replace('&', '&&', $this->meta->title).'&R&8Halaman &P dari &N'
        );

        $this->sheet->setSelectedCell('A1');
    }

    // ── internal ────────────────────────────────────────────────────────────

    private function put(int $col, int $row, mixed $v): void
    {
        if ($v === null || $v === '') {
            return;
        }

        $def = $this->cols[$col] ?? [];
        $coord = $this->col($col).$row;

        if (is_array($v) && isset($v['f'])) {
            $this->sheet->setCellValue($coord, '='.$v['f']);
            $this->track($col, str_contains((string) ($def['fmt'] ?? ''), 'Rp') ? 15 : 10);

            return;
        }

        if ($v instanceof \DateTimeInterface) {
            $this->sheet->setCellValueExplicit($coord, ExcelDate::PHPToExcel($v), DataType::TYPE_NUMERIC);
            $this->track($col, ($def['fmt'] ?? '') === Theme::FMT_DATETIME ? 16 : 10);

            return;
        }

        if (! empty($def['text']) || ! (is_int($v) || is_float($v))) {
            // Selalu eksplisit string: mencegah konversi angka/notasi ilmiah dan "formula injection".
            $this->sheet->setCellValueExplicit($coord, (string) $v, DataType::TYPE_STRING);
            $this->track($col, mb_strlen((string) $v));

            return;
        }

        $this->sheet->setCellValueExplicit($coord, $v, DataType::TYPE_NUMERIC);
        $fmt = $def['fmt'] ?? '';
        $len = strlen(number_format(abs((float) $v), $fmt === Theme::FMT_DECIMAL ? 2 : 0));
        $this->track($col, $len + (str_contains($fmt, 'Rp') ? 4 : 0) + ($v < 0 ? 1 : 0));
    }

    private function track(int $col, int $len): void
    {
        if ($len > ($this->maxLen[$col] ?? 0)) {
            $this->maxLen[$col] = $len;
        }
    }

    private function str(string $coord, string $text): void
    {
        $this->sheet->setCellValueExplicit($coord, $text, DataType::TYPE_STRING);
    }

    private function richInfo(string $coord, string $label, string $value): void
    {
        $rt = new RichText();
        $l = $rt->createTextRun($label.' : ');
        $l->getFont()->setName(Theme::FONT)->setSize(Theme::FONT_SIZE)->setBold(true)
            ->setColor(new Color('FF'.Theme::MUTED));
        $v = $rt->createTextRun($value);
        $v->getFont()->setName(Theme::FONT)->setSize(Theme::FONT_SIZE)
            ->setColor(new Color('FF'.Theme::TEXT));

        $this->sheet->getCell($coord)->setValue($rt);
    }

    private function style(string $range, array $style): void
    {
        $this->sheet->getStyle($range)->applyFromArray($style);
    }

    private function fill(string $rgb): array
    {
        return ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rgb]];
    }

    private function font(array $o): array
    {
        return array_filter([
            'name' => Theme::FONT,
            'size' => $o['size'] ?? Theme::FONT_SIZE,
            'bold' => $o['bold'] ?? false,
            'italic' => $o['italic'] ?? false,
            'color' => ['rgb' => $o['color'] ?? Theme::TEXT],
        ], fn ($x) => $x !== null);
    }

    private function borders(): array
    {
        return ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => Theme::BORDER]]];
    }

    private function rowStyle(array $o): array
    {
        $style = [];
        if (isset($o['fill'])) {
            $style['fill'] = $this->fill($o['fill']);
        }
        if (isset($o['font']) || ! empty($o['bold'])) {
            $style['font'] = ['bold' => ! empty($o['bold']), 'color' => ['rgb' => $o['font'] ?? Theme::TEXT]];
        }

        return $style;
    }

    private function addLogo(): bool
    {
        try {
            $disk = Storage::disk('public');
            $logo = $this->meta->settings['logo'] ?? null;

            if ($logo && $disk->exists($logo)) {
                $path = $disk->path($logo);
            } elseif (is_file(public_path('img/logo.jpeg'))) {
                $path = public_path('img/logo.jpeg');
            } else {
                return false;
            }

            $drawing = new Drawing();
            $drawing->setName('Logo');
            $drawing->setPath($path);
            $drawing->setHeight(64);
            $drawing->setCoordinates('A1');
            $drawing->setOffsetX(4);
            $drawing->setOffsetY(4);
            $drawing->setWorksheet($this->sheet);

            return true;
        } catch (\Throwable) {
            return false; // logo hanya hiasan, jangan sampai export gagal
        }
    }
}