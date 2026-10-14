<?php

namespace App\Exports;

use App\Exports\LaporanGudang\Konversi;
use App\Exports\LaporanGudang\StockMovementExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use App\Models\DeliveryOrder;
use App\Models\Pembelian;
use App\Models\RefundPembelian;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Support\ReportQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * B11: Laporan Aktivitas Gudang (semua pergerakan stok, masuk dan keluar).
 *
 *  - Jenis Aktivitas dibaca dari `type` + dokumen referensi + ada/tidaknya `owner_id` (sisi toko),
 *    bukan lagi sekadar "Penerimaan/Pengiriman" berdasarkan qty masuk.
 *  - Arah (Masuk/Keluar) berwarna; Qty Masuk dan Qty Keluar dipisah supaya bisa dijumlah dan di-pivot.
 *  - Lokasi = Gudang, atau nama outlet bila pergerakan terjadi di sisi toko (`owner_id`).
 *  - PIC = user yang mencatat pergerakan (`user_id`), bukan PIC supplier dari master produk.
 *  - Lokasi rak produk dari master produk tidak dipakai lagi karena bukan lokasi aktivitas.
 */
class LaporanAktifitasExport extends StockMovementExport
{
    private const COL_JENIS = 2;
    private const COL_ARAH = 3;

    protected function makeMeta(): ReportMeta
    {
        return ReportMeta::fromRequest($this->request, 'LAPORAN AKTIVITAS GUDANG', 'Aktivitas-Gudang');
    }

    protected function sheetName(): string
    {
        return 'Aktivitas Gudang';
    }

    protected function columnDefs(): array
    {
        return [
            ['h' => 'Tanggal', 'fmt' => Theme::FMT_DATETIME, 'align' => 'center'],
            ['h' => 'Jenis Aktivitas', 'max' => 30],
            ['h' => 'Arah', 'align' => 'center'],
            ['h' => 'No Dokumen'],
            ['h' => 'Kode Barang', 'text' => true],
            ['h' => 'Nama Barang', 'max' => 45],
            ['h' => 'Batch', 'text' => true],
            ['h' => 'Qty Masuk', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Qty Keluar', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Satuan', 'align' => 'center'],
            ['h' => 'Konversi'],
            ['h' => 'Lokasi', 'max' => 28],
            ['h' => 'PIC', 'max' => 24],
            ['h' => 'Keterangan', 'max' => 50, 'wrap' => true],
        ];
    }

    protected function baseQuery(): Builder
    {
        // Relasi tambahan dimuat di sini; StockMovementExport menambahkan 'product' sendiri.
        return StockMovement::query()->with(['user', 'owner']);
    }

    protected function referenceRelations(): array
    {
        return [];
    }

    protected function movementRow(object $m, array $refs): array
    {
        $ref = ReportQuery::ref($refs, $m);
        $in = (float) ($m->qty_in ?? 0);
        $out = (float) ($m->qty_out ?? 0);
        $arah = $in > 0 ? 'Masuk' : ($out > 0 ? 'Keluar' : '-');

        $tone = match ($arah) {
            'Masuk' => ['fill' => Theme::OK_BG, 'font' => Theme::OK_FG, 'bold' => true],
            'Keluar' => ['fill' => Theme::WARN_BG, 'font' => Theme::WARN_FG, 'bold' => true],
            default => null,
        };

        $lokasi = $m->owner_id
            ? ($m->owner?->name ?? 'Toko #'.$m->owner_id)
            : 'Gudang';

        return [[
            $m->created_at,
            $this->jenis($m),
            $arah,
            $ref?->code ?? '-',
            $m->product?->code ?? '-',
            $m->product?->name ?? '-',
            $this->batchFromNotes($m->notes),
            $in > 0 ? $in : null,
            $out > 0 ? $out : null,
            $m->product?->satuan ?? 'PCS',
            Konversi::of($m->product, max($in, $out)),
            $lokasi,
            $m->user?->name ?? '-',
            $m->notes ?: null,
        ], $tone ? ['cells' => [self::COL_JENIS => $tone, self::COL_ARAH => $tone]] : []];
    }

    protected function addTotals(SheetWriter $w): void
    {
        $w->totalRow('TOTAL', [8 => 'sum', 9 => 'sum'], ['grand' => true, 'label_col' => 6]);
    }

    /** Nama aktivitas yang mudah dibaca, dari kombinasi type, dokumen referensi, dan sisi (gudang/toko). */
    private function jenis(object $m): string
    {
        $toko = (bool) $m->owner_id;

        return match ($m->type) {
            'in' => match (true) {
                $toko => 'Penerimaan Toko',
                $m->reference_type === Pembelian::class => 'Penerimaan Barang',
                $m->reference_type === DeliveryOrder::class => 'Pengembalian Sisa Pengiriman',
                default => 'Barang Masuk',
            },
            'out' => match (true) {
                $toko && $m->reference_type === RefundPembelian::class => 'Retur Toko ke Gudang',
                $toko => 'Pengeluaran Toko',
                $m->reference_type === DeliveryOrder::class => 'Pengiriman Barang',
                default => 'Barang Keluar',
            },
            'adjustment' => $m->reference_type === StockAdjustment::class ? 'Stok Opname / Adjustment' : 'Penyesuaian Stok',
            'retur_ke_supplier' => 'Retur ke Supplier',
            'retur_dari_outlet' => 'Retur dari Outlet',
            'penerimaan_retur' => 'Penerimaan Retur',
            default => $m->type ? ucfirst(str_replace('_', ' ', (string) $m->type)) : ($m->qty_in > 0 ? 'Barang Masuk' : 'Barang Keluar'),
        };
    }
}