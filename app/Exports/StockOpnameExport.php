<?php

namespace App\Exports;

use App\Exports\LaporanGudang\Konversi;
use App\Exports\LaporanGudang\Status;
use App\Exports\LaporanToko\BaseLaporanTokoExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use App\Models\StockAdjustment;
use App\Support\ReportQuery;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * B12: Laporan Stok Opname & Adjustment.
 *
 *  - Memakai fondasi baru (kop, info, header, filter, freeze, cetak); tidak ada lagi posisi tetap B16.
 *  - Satu baris per penyesuaian dengan kolom Tanggal, jadi laporan bisa berupa rentang tanggal.
 *  - "No Dokumen" buatan dan "Nama" kosong dihapus (tidak ada nomor dokumen nyata di data).
 *  - Selisih = rumus (Stok Fisik - Stok Sistem); plus hijau, minus merah.
 *  - Periode tanpa data menghasilkan sheet kosong dengan pesan (D3), tanpa fallback 500 data terbaru.
 *  - Filter Lokasi (produk) tampil di blok info.
 */
class StockOpnameExport extends BaseLaporanTokoExport
{
    private const COL_SISTEM = 7;
    private const COL_FISIK = 8;
    private const COL_SELISIH = 9;
    private const COL_ADJUST = 10;

    public function __construct(private readonly Request $request) {}

    protected function makeMeta(): ReportMeta
    {
        return ReportMeta::fromRequest($this->request, 'LAPORAN STOK OPNAME & ADJUSTMENT', 'Stok-Opname', ['lokasi']);
    }

    protected function sheetName(): string
    {
        return 'Stok Opname';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        $w->columns([
            ['h' => 'Tanggal', 'fmt' => Theme::FMT_DATE, 'align' => 'center'],
            ['h' => 'Kode Barang', 'text' => true],
            ['h' => 'Nama Barang', 'max' => 45],
            ['h' => 'Batch', 'text' => true],
            ['h' => 'Expired', 'fmt' => Theme::FMT_DATE, 'align' => 'center'],
            ['h' => 'Satuan', 'align' => 'center'],
            ['h' => 'Stok Sistem', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Stok Fisik', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Selisih', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Qty Adjust', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Konversi Stok Sistem'],
            ['h' => 'Lokasi'],
            ['h' => 'Alasan', 'max' => 30, 'wrap' => true],
            ['h' => 'Status', 'align' => 'center'],
            ['h' => 'Keterangan', 'max' => 50, 'wrap' => true],
        ])->kop()->info()->header();

        // Urut abjad berdasarkan nama barang; penyesuaian barang yang sama diurutkan menurut tanggal.
        // Produk yang sudah dihapus / tanpa nama diletakkan paling bawah.
        $query = StockAdjustment::with(['product', 'stock'])
            ->leftJoin('products', 'products.id', '=', 'stock_adjustments.product_id')
            ->select('stock_adjustments.*')
            ->tap(fn ($q) => ReportQuery::betweenDates(
                $q,
                'stock_adjustments.adjustment_date',
                $meta->start->toDateString(),
                $meta->end->toDateString()
            ))
            ->orderByRaw('products.name IS NULL')
            ->orderBy('products.name')
            ->orderBy('stock_adjustments.adjustment_date')
            ->orderBy('stock_adjustments.id');

        if ($this->request->filled('lokasi')) {
            $lokasi = (string) $this->request->input('lokasi');
            $query->whereHas('product', fn ($q) => $q->where('lokasi', $lokasi));
        }

        foreach ($query->lazy(2000) as $a) {
            $sistem = (float) ($a->system_qty ?? 0);
            $fisik = (float) ($a->physical_qty ?? 0);
            $selisih = $fisik - $sistem;
            $adjust = (float) ($a->quantity ?? 0);

            // Rumus Selisih ditulis di baris yang sedang dibuat.
            $selisihFormula = SheetWriter::f($w->ref(self::COL_FISIK).'-'.$w->ref(self::COL_SISTEM));

            $tone = match (true) {
                $selisih > 0 => ['fill' => Theme::OK_BG, 'font' => Theme::OK_FG, 'bold' => true],
                $selisih < 0 => ['fill' => Theme::DANGER_BG, 'font' => Theme::DANGER_FG, 'bold' => true],
                default => null,
            };
            $adjustTone = match (true) {
                $adjust > 0 => ['font' => Theme::OK_FG, 'bold' => true],
                $adjust < 0 => ['font' => Theme::DANGER_FG, 'bold' => true],
                default => null,
            };

            $cells = array_filter([
                self::COL_SELISIH => $tone,
                self::COL_ADJUST => $adjustTone,
                14 => Status::cell($a->status ?? 'Selesai'),
            ]);

            $w->row([
                $a->adjustment_date,
                $a->product?->code ?? '-',
                $a->product?->name ?? '-',
                $a->sku,
                $a->stock?->expired_at,
                $a->product?->satuan ?? 'PCS',
                $sistem,
                $fisik,
                $selisihFormula,
                $adjust,
                Konversi::of($a->product, $sistem),
                $a->product?->lokasi,
                $a->reason,
                $a->status ?? 'Selesai',
                $a->keterangan,
            ], $cells ? ['cells' => $cells] : []);
        }

        $first = $w->firstDataRow();
        $last = $w->currentRow() - 1;

        if ($first !== null && $last >= $first) {
            $range = $w->col(self::COL_ADJUST).$first.':'.$w->col(self::COL_ADJUST).$last;

            // Ringkasan dengan rumus. SUMIF mengikuti seluruh data (tidak berubah saat difilter).
            $w->totalRow('Total Penyesuaian Masuk', [
                self::COL_ADJUST => SheetWriter::f('SUMIF('.$range.',">0")'),
            ], ['grand' => true, 'label_col' => 3]);
            $w->totalRow('Total Penyesuaian Keluar', [
                self::COL_ADJUST => SheetWriter::f('-SUMIF('.$range.',"<0")'),
            ], ['label_col' => 3]);
            $w->totalRow('Selisih Bersih (Masuk - Keluar)', [
                self::COL_ADJUST => SheetWriter::f($w->col(self::COL_ADJUST).($last + 1).'-'.$w->col(self::COL_ADJUST).($last + 2)),
            ], ['label_col' => 3]);
        }
    }
}