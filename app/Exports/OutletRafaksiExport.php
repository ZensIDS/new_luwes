<?php

namespace App\Exports;

use App\Exports\LaporanToko\BaseLaporanTokoExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use App\Services\OutletLaporanService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** B2: Rafaksi Outlet, satu tabel per rafaksi (judul nama rafaksi di atas tabel), rekap berumus bila lebih dari satu. */
class OutletRafaksiExport extends BaseLaporanTokoExport
{
    /** Jeda baris kosong antar tabel rafaksi (2-4). */
    private const TABLE_GAP = 3;

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

    /** Pola toko (huruf 18, A4 portrait, wrap, tanpa footer): todolist_font_laporan_toko. */
    protected function tokoLayout(): bool
    {
        return true;
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $data = app(OutletLaporanService::class)->rafaksi($this->request);
        $promotions = $data['promotions'] ?? collect();

        $w->columns([
            ['h' => 'Barcode', 'text' => true],
            ['h' => 'Nama Produk', 'max' => 24, 'wrap' => true],
            ['h' => 'Qty Produk Terjual', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Harga Akhir / Unit', 'fmt' => Theme::FMT_RUPIAH, 'min' => 14],
            ['h' => 'Diskon Rafaksi / Unit', 'fmt' => Theme::FMT_RUPIAH, 'min' => 14],
            ['h' => '% Diskon', 'fmt' => Theme::FMT_PERCENT],
            ['h' => 'Total Diskon', 'fmt' => Theme::FMT_RUPIAH, 'min' => 16],
            ['h' => 'Total Penjualan', 'fmt' => Theme::FMT_RUPIAH, 'min' => 16],
        ])->kop()->info();

        $groups = collect($data['rows'] ?? [])
            ->sortBy([['keterangan', 'asc'], ['product', 'asc']])
            ->groupBy('keterangan');

        if ($groups->isEmpty()) {
            $w->header(); // pesan "Tidak ada data" ditulis oleh finish()

            return;
        }

        $percent = fn (int $r) => SheetWriter::f("IF(H{$r}>0,G{$r}/H{$r},0)");
        $multi = $groups->count() > 1;
        $firstOfAll = null;

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
            $firstOfAll ??= $first;

            $r = $w->currentRow();
            $w->totalRow($multi ? 'Total' : 'TOTAL', [3 => 'sum', 6 => $percent($r), 7 => 'sum', 8 => 'sum'], [
                'from' => $first, 'to' => $r - 1, 'label_col' => 2, 'grand' => ! $multi,
            ]);
        }

        // REKAP semua rafaksi hanya bila ada lebih dari satu tabel.
        if ($multi) {
            for ($i = 0; $i < self::TABLE_GAP; $i++) {
                $w->blank(30);
            }
            $r = $w->currentRow();
            $w->totalRow('REKAP SEMUA RAFAKSI', [3 => 'sum', 6 => $percent($r), 7 => 'sum', 8 => 'sum'], [
                'from' => $firstOfAll, 'to' => $r - 1, 'grand' => true, 'label_col' => 2,
            ]);
        }
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