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
    /** Jumlah baris data pada tabel yang sedang ditulis (dipakai untuk warna selang-seling). */
    private int $tableRows = 0;

    /** Mode banyak tabel (newTable): rentang tiap tabel [header, baris akhir], judulnya, dan header susulan. */
    private array $spans = [];
    private array $titles = [];
    private array $laterHeaders = [];
    private bool $multi = false;

    /**
     * Ukuran huruf per bagian tabel (opsional): title, header, body, total.
     * Bagian yang tidak diisi memakai baseFontSize(). Dipakai laporan yang meminta ukuran berbeda
     * per bagian (mis. Rafaksi Toko); laporan lain tidak terpengaruh.
     */
    private array $sizes = [];

    /** Style yang harus dipasang setelah format kolom (alignment/format angka khusus). */
    private array $post = [];

    /**
     * Mode siap cetak hitam-putih (plainPrint): header tabel dan baris total berlatar putih bertulisan hitam,
     * garis tabel hitam tipis, judul tabel hitam. Default false = tema biru (laporan lain tidak berubah).
     */
    private bool $plain = false;

    /** Zoom layar khusus (persen); null = Theme::TOKO_ZOOM. */
    private ?int $zoom = null;

    /**
     * @param bool $toko pola toko (todolist_font_laporan_toko). Default false = tampilan lama.
     *                   Bila true, font default workbook menjadi Theme::TOKO_FONT_SIZE supaya lebar
     *                   kolom Excel (satuan karakter font default) tetap sesuai rumus lebar.
     */
    public function __construct(
        private readonly Worksheet $sheet,
        private readonly ReportMeta $meta,
        private readonly bool $toko = false,
    ) {
        $this->sheet->getParent()?->getDefaultStyle()->getFont()
            ->setName(Theme::FONT)->setSize($this->baseFontSize());
    }

    public function isToko(): bool
    {
        return $this->toko;
    }

    /** Ukuran huruf dasar: 18 untuk pola toko, 10 untuk tampilan lama. */
    public function baseFontSize(): int
    {
        return $this->toko ? Theme::TOKO_FONT_SIZE : Theme::FONT_SIZE;
    }

    /** Mode siap cetak hitam-putih (lihat properti $plain). */
    public function plainPrint(bool $on = true): static
    {
        $this->plain = $on;

        return $this;
    }

    /** Zoom layar sheet (persen), mis. 50 untuk huruf besar. */
    public function zoom(int $percent): static
    {
        $this->zoom = $percent;

        return $this;
    }

    /** Atur ukuran huruf per bagian: kop, info, title, header, body, total (bagian yang tidak diisi memakai ukuran dasar). */
    public function fontSizes(array $sizes): static
    {
        $this->sizes = array_filter($sizes, fn ($v) => is_numeric($v) && $v > 0);

        return $this;
    }

    private function size(string $part): int
    {
        return (int) ($this->sizes[$part] ?? $this->baseFontSize());
    }

    /** Pengali lebar kolom: satuan lebar Excel = huruf dasar, jadi huruf yang lebih besar butuh kolom lebih lebar. */
    private function widthScale(): float
    {
        if ($this->sizes === []) {
            return 1.0;
        }

        return max(1.0, max($this->sizes) / $this->baseFontSize());
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
        if ($this->toko) {
            return $this->kopToko();
        }

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
        if ($this->toko) {
            return $this->infoToko();
        }

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

    /** Baris peringatan berwarna (mis. data dipotong), ditulis sebelum header tabel. */
    public function notice(string $text): static
    {
        $r = $this->row++;
        $this->str('A'.$r, $text);
        $this->style('A'.$r.':'.$this->col($this->colCount).$r, [
            'fill' => $this->fill(Theme::WARN_BG),
            'font' => $this->font(['bold' => true, 'color' => Theme::WARN_FG]),
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $this->sheet->getRowDimension($r)->setRowHeight($this->toko ? Theme::TOKO_ROW_NOTICE : 20);

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

        $this->sheet->getRowDimension($r)->setRowHeight($this->toko ? Theme::TOKO_ROW_GROUP : 20);
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

        $this->applyHeaderStyle($r);

        $this->headerRow = $r;
        $this->firstData = $r + 1;
        $this->row++;

        return $this;
    }

    private function applyHeaderStyle(int $r): void
    {
        $this->style('A'.$r.':'.$this->col($this->colCount).$r, [
            'fill' => $this->fill($this->plain ? 'FFFFFF' : Theme::PRIMARY),
            'font' => $this->font(['bold' => true, 'color' => $this->plain ? '000000' : Theme::ON_PRIMARY, 'size' => $this->size('header')]),
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => $this->borders(),
        ]);
        $this->sheet->getRowDimension($r)->setRowHeight($this->toko ? Theme::TOKO_ROW_HEADER : 30);
    }

    /**
     * Mulai satu tabel baru: judul tabel (digabung selebar tabel) di atas, lalu header kolom.
     * Mulai dari tabel kedua, ditambahkan $gap baris kosong sebagai jeda (disarankan 2-4).
     * Dipakai laporan yang memecah data menjadi beberapa tabel dalam satu sheet (mis. Rafaksi).
     * Pada mode ini filter, freeze pane, dan pengulangan header cetak dimatikan karena hanya
     * berlaku untuk satu tabel.
     */
    public function newTable(string $title, int $gap = 3): static
    {
        $later = $this->headerRow !== null;

        if ($later) {
            $this->closeSpan();
            for ($i = 0; $i < $gap; $i++) {
                $this->sheet->getRowDimension($this->row)->setRowHeight($this->toko ? Theme::TOKO_ROW_TITLE : 15);
                $this->row++;
            }
            $this->multi = true;
        }

        $t = $this->row++;
        $this->str($this->col(1).$t, $title);
        $this->titles[] = [$t, $title];

        $savedHeader = $this->headerRow;
        $savedFirst = $this->firstData;
        $this->header();
        $h = $this->headerRow;

        if ($later) {
            // Tabel pertama tetap menjadi acuan format kolom; header susulan distyle ulang di finish().
            $this->headerRow = $savedHeader;
            $this->firstData = $savedFirst;
            $this->laterHeaders[] = $h;
        }

        $this->spans[] = [$h, null];
        $this->tableRows = 0;

        return $this;
    }

    private function closeSpanAt(int $end): void
    {
        $i = count($this->spans) - 1;
        if ($i >= 0 && $this->spans[$i][1] === null) {
            $this->spans[$i][1] = max($this->spans[$i][0], $end);
        }
    }

    private function closeSpan(): void
    {
        $i = count($this->spans) - 1;
        if ($i >= 0 && $this->spans[$i][1] === null) {
            $this->spans[$i][1] = max($this->spans[$i][0], $this->row - 1);
        }
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
        $this->tableRows++;

        foreach (array_values($values) as $n => $v) {
            $this->put($n + 1, $r, $v);
        }

        $range = 'A'.$r.':'.$this->col($this->colCount).$r;
        if (isset($this->sizes['body'])) {
            $this->style($range, ['font' => ['size' => $this->size('body')]]);
        }
        if (isset($opts['fill']) || isset($opts['font']) || ! empty($opts['bold'])) {
            $this->style($range, $this->rowStyle($opts));
        } elseif (! $this->plain && $this->tableRows % 2 === 0) { // mode plainPrint: semua baris putih, tanpa selang-seling
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

    public function blank(?float $height = null): static
    {
        $height ??= $this->toko ? Theme::TOKO_ROW_SPACER : 6;
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
            'fill' => $this->fill($this->plain ? 'FFFFFF' : ($sub ? Theme::SECONDARY : Theme::TOTAL_BG)),
            'font' => $this->font(['bold' => true, 'color' => $this->plain ? '000000' : Theme::PRIMARY, 'size' => $this->size('total')]),
            'borders' => ($sub || $this->plain) ? [] : ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => Theme::PRIMARY]]],
        ]);

        if (! empty($opts['grand'])) {
            $this->tableEnd = $this->lastBodyRow ?? ($r - 1);
            if ($this->spans !== []) {
                $this->closeSpanAt($this->tableEnd);
                $this->spans[] = [$r, $r];
            }
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

        $totalWidth = 0;

        // Format + perataan per kolom (satu panggilan per kolom, bukan per sel).
        foreach ($this->cols as $i => $def) {
            $L = $this->col($i);
            $numeric = isset($def['fmt']) && $def['fmt'] !== Theme::FMT_TEXT;
            $align = $def['align'] ?? ($numeric ? 'right' : 'left');

            $style = ['alignment' => [
                'horizontal' => $align,
                'vertical' => Alignment::VERTICAL_CENTER,
                // K10: pola toko membungkus semua kolom teks (tanpa format angka/tanggal) ke bawah.
                'wrapText' => ! empty($def['wrap']) || ($this->toko && ! isset($def['fmt'])),
            ]];
            if (isset($def['fmt'])) {
                $style['numberFormat'] = ['formatCode' => $def['fmt']];
            } elseif (! empty($def['text'])) {
                $style['numberFormat'] = ['formatCode' => Theme::FMT_TEXT];
            }

            if ($lastRow >= $first) {
                $this->style("{$L}{$first}:{$L}{$lastRow}", $style);
            }

            $factor = $this->toko ? Theme::TOKO_WIDTH_FACTOR : 1.15;
            $cap = $this->toko ? min($def['max'] ?? Theme::TOKO_COL_MAX, Theme::TOKO_COL_MAX) : ($def['max'] ?? 40);
            $width = $def['w'] ?? max(
                $def['min'] ?? 8,
                min($cap, (int) ceil(($this->maxLen[$i] ?? 8) * $factor) + 2)
            );
            // 'exact' => true: lebar dipakai apa adanya (tanpa pengali huruf besar), mis. mengikuti contoh Excel pengguna.
            $width = ! empty($def['exact']) && isset($def['w']) ? (float) $def['w'] : (int) ceil($width * $this->widthScale());
            $this->sheet->getColumnDimension($L)->setWidth($width);
            $totalWidth += $width;
        }

        foreach ($this->post as [$range, $style]) {
            $this->style($range, $style);
        }

        if ($this->multi) {
            $this->finishMulti($totalWidth, $lastRow, $lastL);
        } else {
            // Border tipis seluruh tabel.
            $this->style("A{$h}:{$lastL}{$lastRow}", ['borders' => $this->borders()]);

            if ($this->titles !== []) {
                $this->styleTitles($totalWidth, $lastL);
            }

            // Filter + freeze pane di bawah header.
            if ($tableEnd > $h) {
                $this->sheet->setAutoFilter("A{$h}:{$lastL}{$tableEnd}");
            }
            $this->freeze($h);
        }

        // Tampilan.
        $this->sheet->setShowGridlines(false);
        $this->sheet->getSheetView()->setZoomScale($this->toko ? ($this->zoom ?? Theme::TOKO_ZOOM) : 95);
        $this->sheet->getTabColor()->setRGB(Theme::PRIMARY);

        // Cetak: landscape A4 (pola toko: portrait A4), muat satu halaman lebar, header berulang.
        $page = $this->sheet->getPageSetup();
        $page->setOrientation($this->toko ? PageSetup::ORIENTATION_PORTRAIT : PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setHorizontalCentered(true)
            ->setPrintArea("A1:{$lastL}{$lastRow}");

        if (! $this->multi) {
            $page->setRowsToRepeatAtTopByStartAndEnd($this->groupRow ?? $h, $h);
        }

        if ($this->toko) {
            // K1/K5: margin sempit, tanpa footer dan tanpa nomor halaman.
            $this->sheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.3)->setRight(0.3);
        } else {
            $this->sheet->getPageMargins()->setTop(0.5)->setBottom(0.6)->setLeft(0.4)->setRight(0.4);
            $this->sheet->getHeaderFooter()->setOddFooter(
                '&L&8'.str_replace('&', '&&', $this->meta->title).'&R&8Halaman &P dari &N'
            );
        }

        $this->sheet->setSelectedCell('A1');
    }

    /** Mode banyak tabel: style ulang header susulan, judul tiap tabel, dan border per tabel (jeda tanpa garis). */
    private function finishMulti(float $totalWidth, int $lastRow, string $lastL): void
    {
        $this->closeSpanAt($lastRow);

        foreach ($this->laterHeaders as $hr) {
            $this->applyHeaderStyle($hr);
        }

        $this->styleTitles($totalWidth, $lastL);

        foreach ($this->spans as [$from, $to]) {
            $this->style("A{$from}:{$lastL}".($to ?? $lastRow), ['borders' => $this->borders()]);
        }
    }

    /** Judul tabel: digabung selebar tabel, tebal, wrap; tinggi baris diperkirakan dari panjang teks (sel gabungan tidak auto-fit). */
    private function styleTitles(float $totalWidth, string $lastL): void
    {
        $custom = $this->toko && isset($this->sizes['title']);
        // Ukuran judul khusus: 1 satuan lebar kolom = lebar angka huruf dasar (~0,507 em); huruf tebal ~0,58 em per karakter.
        $perLine = $custom
            ? max(20, (int) floor($totalWidth * 0.507 * $this->baseFontSize() / ($this->size('title') * 0.58)))
            : max(20, (int) floor($totalWidth * 0.8));
        $lineHeight = $custom
            ? $this->size('title') * 1.269 // 40 pt => 50,75 pt (tinggi satu baris Calibri)
            : ($this->toko ? Theme::TOKO_ROW_TITLE * ($this->size('title') / $this->baseFontSize()) : 18);

        foreach ($this->titles as [$r, $text]) {
            if ($this->colCount > 1) {
                $this->sheet->mergeCells("A{$r}:{$lastL}{$r}");
            }
            $this->style("A{$r}:{$lastL}{$r}", [
                'fill' => ['fillType' => Fill::FILL_NONE],
                'font' => $this->font(['bold' => true, 'color' => $this->plain ? '000000' : Theme::PRIMARY, 'size' => $this->size('title')]),
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_NONE]],
            ]);
            $lines = max(1, (int) ceil(mb_strlen($text) / $perLine));
            $this->sheet->getRowDimension($r)->setRowHeight($lines * $lineHeight + ($custom ? 0 : 4));
        }
    }

    // ── pola toko (todolist_font_laporan_toko, Tahap C) ─────────────────────

    /** K7/K9: kop ringkas, hanya nama toko/perusahaan (tanpa alamat, kontak, logo). */
    private function kopToko(): static
    {
        $name = (string) ($this->meta->settings['name'] ?? config('app.name'));

        $this->str('A1', $name);
        $this->style('A1', ['font' => $this->font(['bold' => true, 'color' => Theme::PRIMARY, 'size' => $this->size('kop')])]);
        $this->sheet->getRowDimension(1)->setRowHeight(Theme::TOKO_ROW_KOP);

        $this->style('A1:'.$this->col($this->colCount).'1', [
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => Theme::PRIMARY]]],
        ]);

        $this->sheet->getRowDimension(2)->setRowHeight(Theme::TOKO_ROW_SPACER);
        $this->row = 3;

        return $this;
    }

    /** K7: judul laporan, periode, filter, dan waktu cetak; satu kolom, tanpa "Dicetak Oleh". */
    private function infoToko(): static
    {
        $this->str('A'.$this->row, $this->meta->title);
        $this->style('A'.$this->row, ['font' => $this->font(['bold' => true, 'color' => Theme::PRIMARY, 'size' => $this->size('info')])]);
        $this->sheet->getRowDimension($this->row)->setRowHeight(Theme::TOKO_ROW_TITLE);
        $this->row++;

        $first = $this->meta->snapshot
            ? ['Per Tanggal' => (string) $this->meta->snapshotLabel()]
            : ['Periode' => $this->meta->periodLabel()];

        $items = $first + $this->meta->info + ['Dicetak' => $this->meta->printedAtLabel()];

        foreach ($items as $label => $value) {
            $this->richInfo('A'.$this->row, (string) $label, (string) $value);
            $this->sheet->getRowDimension($this->row)->setRowHeight(Theme::TOKO_ROW_TITLE);
            $this->row++;
        }

        $this->sheet->getRowDimension($this->row)->setRowHeight(Theme::TOKO_ROW_SPACER);
        $this->row++;

        return $this;
    }

    /**
     * Freeze pane di bawah header. Pola toko (K6): bila kop+info+header terlalu tinggi,
     * hanya baris header yang dibekukan (layar digulir sampai header di paling atas).
     */
    private function freeze(int $h): void
    {
        if (! $this->toko) {
            $this->sheet->freezePane('A'.($h + 1));

            return;
        }

        $height = 0.0;
        for ($r = 1; $r <= $h; $r++) {
            $rh = $this->sheet->getRowDimension($r)->getRowHeight();
            $height += $rh > 0 ? $rh : 24;
        }

        if ($height > Theme::TOKO_FREEZE_MAX && method_exists($this->sheet, 'setTopLeftCell')) {
            $this->sheet->setTopLeftCell('A'.$h);
            $this->sheet->freezePane('A'.($h + 1), 'A'.($h + 1));

            return;
        }

        $this->sheet->freezePane('A'.($h + 1));
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
        $l->getFont()->setName(Theme::FONT)->setSize($this->size('info'))->setBold(true)
            ->setColor(new Color('FF'.Theme::MUTED));
        $v = $rt->createTextRun($value);
        $v->getFont()->setName(Theme::FONT)->setSize($this->size('info'))
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
            'size' => $o['size'] ?? $this->baseFontSize(),
            'bold' => $o['bold'] ?? false,
            'italic' => $o['italic'] ?? false,
            'color' => ['rgb' => $o['color'] ?? Theme::TEXT],
        ], fn ($x) => $x !== null);
    }

    private function borders(): array
    {
        return ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $this->plain ? '000000' : Theme::BORDER]]];
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