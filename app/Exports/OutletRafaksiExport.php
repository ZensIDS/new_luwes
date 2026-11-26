<?php

namespace App\Exports;

use App\Exports\LaporanToko\BaseLaporanTokoExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use App\Services\OutletLaporanService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * B2: Rafaksi Outlet, satu tabel per rafaksi (judul nama rafaksi di atas tabel), siap cetak hitam-putih.
 * Layout mengikuti contoh Excel pengguna (Laporan-Rafaksi-Toko CONTOH): huruf 40, zoom 50, A4 portrait,
 * 1 halaman lebar, header kolom ringkas, tanpa baris rekap.
 */
class OutletRafaksiExport extends BaseLaporanTokoExport
{
    /** Jeda baris kosong antar tabel rafaksi (2-4). */
    private const TABLE_GAP = 3;

    /** Ukuran huruf per bagian mengikuti contoh Excel pengguna: semua bagian 40. */
    private const FONT_SIZES = ['kop' => 40, 'info' => 40, 'title' => 40, 'header' => 40, 'body' => 40, 'total' => 40];

    /** Zoom layar sesuai contoh. */
    private const ZOOM = 50;

    /** Lebar kolom tetap sesuai contoh (A..H): Barcode, Nama Produk, QTY, H. Akhir, Disc (Rp), Disc (%), T. Disc, T. Jual. */
    private const COL_WIDTHS = [24.0, 39.6640625, 8.0, 17.75, 18.25, 18.33203125, 17.08203125, 20.25];

    public function __construct(private readonly Request $request) {}

    protected function makeMeta(): ReportMeta
    {
        return ReportMeta::fromRequest(
            $this->request, 'LAPORAN RAFAKSI OUTLET', 'Rafaksi-Toko', ['outlet', 'rafaksi'],
            null, ReportMeta::promotionRange($this->request)
        );
    }

    protected function sheetName(): string
    {
        return 'Rafaksi';
    }

    /** Pola toko (A4 portrait, wrap, tanpa footer): todolist_font_laporan_toko. Ukuran huruf per bagian: FONT_SIZES. */
    protected function tokoLayout(): bool
    {
        return true;
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $data = app(OutletLaporanService::class)->rafaksi($this->request);
        $promotions = $data['promotions'] ?? collect();

        $groups = collect($data['rows'] ?? [])
            ->sortBy([['keterangan', 'asc'], ['product', 'asc']])
            ->groupBy('keterangan');

        $widths = $this->columnWidths($groups);

        $w->fontSizes(self::FONT_SIZES)->plainPrint()->zoom(self::ZOOM)->columns([
            ['h' => 'Barcode', 'text' => true, 'w' => $widths[0], 'exact' => true],
            ['h' => 'Nama Produk', 'wrap' => true, 'w' => $widths[1], 'exact' => true],
            ['h' => 'QTY', 'fmt' => Theme::FMT_NUMBER, 'w' => $widths[2], 'exact' => true],
            ['h' => 'H. Akhir', 'fmt' => Theme::FMT_RUPIAH, 'w' => $widths[3], 'exact' => true],
            ['h' => 'Disc (Rp)', 'fmt' => Theme::FMT_RUPIAH, 'w' => $widths[4], 'exact' => true],
            ['h' => 'Disc (%)', 'fmt' => Theme::FMT_PERCENT, 'w' => $widths[5], 'exact' => true],
            ['h' => 'T. Disc', 'fmt' => Theme::FMT_RUPIAH, 'w' => $widths[6], 'exact' => true],
            ['h' => 'T. Jual', 'fmt' => Theme::FMT_RUPIAH, 'w' => $widths[7], 'exact' => true],
        ])->kop()->info();

        if ($groups->isEmpty()) {
            $w->header(); // pesan "Tidak ada data" ditulis oleh finish()

            return;
        }

        $percent = fn (int $r) => SheetWriter::f("IF(H{$r}>0,G{$r}/H{$r},0)");
        $multi = $groups->count() > 1;

        // Tiap rafaksi = satu tabel sendiri: judul nama rafaksi di atas, kolom langsung berisi barang.
        // Antar tabel diberi jeda self::TABLE_GAP baris kosong.
        foreach ($groups as $name => $items) {
            $w->newTable($this->groupLabel((string) $name, $promotions), self::TABLE_GAP);

            $first = null;
            foreach ($items as $row) {
                $r = $w->currentRow();
                $w->row([
                    $row['barcode'], $row['product'], (float) $row['qty'],
                    (float) $row['unit_price'], (float) $row['discount_per_unit'],
                    $percent($r),
                    (float) $row['discount_total'], (float) $row['subtotal'],
                ]);
                $first ??= $r;
            }

            $r = $w->currentRow();
            $w->totalRow($multi ? 'Total' : 'TOTAL', [3 => 'sum', 6 => $percent($r), 7 => 'sum', 8 => 'sum'], [
                'from' => $first, 'to' => $r - 1, 'label_col' => 2, 'grand' => ! $multi,
            ]);
        }
    }

    /**
     * Lebar kolom: dasar dari contoh (COL_WIDTHS), dilebarkan hanya bila angka/barcode terbesar tidak muat
     * pada huruf 40 (supaya tidak jadi ####). Data seperti contoh tetap memakai lebar contoh persis.
     *
     * @return array<int,float>
     */
    private function columnWidths($groups): array
    {
        $widths = self::COL_WIDTHS;
        $scale = self::FONT_SIZES['body'] / Theme::TOKO_FONT_SIZE;

        // Satuan lebar Excel = lebar angka huruf dasar; angka huruf 40 = $scale satuan, koma ~0,5 angka, "Rp " ~2,6 angka.
        $units = function (float $v, bool $rupiah) use ($scale): float {
            $s = number_format(abs($v));
            $digits = strlen((string) preg_replace('/\D/', '', $s));
            $u = $digits + 0.5 * substr_count($s, ',') + 0.5 + ($rupiah ? 2.6 : 0) + ($v < 0 ? 0.7 : 0);

            return $u * $scale * 1.05;
        };
        $grow = function (int $i, float $need) use (&$widths): void {
            $widths[$i] = max($widths[$i], ceil($need * 4) / 4);
        };

        foreach ($groups as $items) {
            $sum = [2 => 0.0, 6 => 0.0, 7 => 0.0];
            foreach ($items as $row) {
                $grow(0, strlen((string) $row['barcode']) * $scale + 0.5);
                $grow(2, $units((float) $row['qty'], false));
                $grow(3, $units((float) $row['unit_price'], true));
                $grow(4, $units((float) $row['discount_per_unit'], true));
                $grow(6, $units((float) $row['discount_total'], true));
                $grow(7, $units((float) $row['subtotal'], true));
                $sum[2] += (float) $row['qty'];
                $sum[6] += (float) $row['discount_total'];
                $sum[7] += (float) $row['subtotal'];
            }
            $grow(2, $units($sum[2], false) * 1.05); // baris total tebal
            $grow(6, $units($sum[6], true) * 1.05);
            $grow(7, $units($sum[7], true) * 1.05);
        }

        return $widths;
    }

    /** Tambahkan periode bila nama rafaksi cocok tepat dengan satu data rafaksi. */
    private function groupLabel(string $names, $promotions): string
    {
        $parts = array_map('trim', explode(',', $names));
        if (count($parts) !== 1) {
            return $names;
        }

        $matches = collect($promotions)->where('name', $parts[0]);
        if ($matches->count() !== 1) {
            return $names;
        }

        $promo = $matches->first();
        $range = implode(' s/d ', array_filter([
            $promo->start_at?->format('d/m/Y'),
            $promo->end_at?->format('d/m/Y'),
        ]));

        return $range !== '' ? "{$names} ({$range})" : $names;
    }
}