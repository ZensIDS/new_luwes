<?php

namespace App\Exports;

use App\Exports\LaporanGudang\Konversi;
use App\Exports\LaporanGudang\Status;
use App\Exports\LaporanToko\BaseLaporanTokoExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use App\Models\Product;
use App\Models\ProductMinimumAdjustment;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** B8: Laporan Pergerakan & Kebutuhan Stok (laporan posisi per tanggal cetak, satu baris per produk). */
class LaporanPergerakanExport extends BaseLaporanTokoExport
{
    public function __construct(private readonly Request $request) {}

    protected function makeMeta(): ReportMeta
    {
        return ReportMeta::fromRequest($this->request, 'LAPORAN PERGERAKAN & KEBUTUHAN STOK', 'Pergerakan-Stok', [], now());
    }

    protected function sheetName(): string
    {
        return 'Pergerakan Stok';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $w->columns([
            ['h' => 'Kode Barang', 'text' => true],
            ['h' => 'Nama Barang', 'max' => 45],
            ['h' => 'Stok', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Konversi Stok'],
            ['h' => 'Rata-rata Keluar / Bulan', 'fmt' => Theme::FMT_DECIMAL],
            ['h' => 'Hari Tanpa Transaksi', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Kategori Pergerakan', 'align' => 'center', 'min' => 16],
            ['h' => 'Status Stok', 'align' => 'center'],
            ['h' => 'Min Stok', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Saran Reorder', 'align' => 'center'],
            ['h' => 'Qty Reorder', 'fmt' => Theme::FMT_NUMBER],
            ['h' => 'Konversi Reorder'],
        ])->kop()->info()->header();

        $movementStats = StockMovement::selectRaw('product_id, SUM(qty_out) as total_out, MIN(created_at) as first_date, MAX(created_at) as last_date')
            ->groupBy('product_id')
            ->get()->keyBy('product_id');

        $adjustments = ProductMinimumAdjustment::activeOn(now()->toDateString())
            ->orderByDesc('active_from')->orderByDesc('id')
            ->get()->keyBy('product_id');

        // Per PRODUK dengan stok fisik = SUM(stocks.qty) semua batch; dibaca per potongan.
        $products = Product::with('category')
            ->withSum('stocks as stock_qty', 'qty')
            ->whereHas('stocks')
            ->orderBy('code')->orderBy('id')
            ->lazy(1000);

        foreach ($products as $p) {
            $qty = (int) ($p->stock_qty ?? 0);
            $stat = $movementStats[$p->id] ?? null;

            $totalOut = (int) ($stat?->total_out ?? 0);
            $months = max(1, (int) Carbon::parse($stat?->first_date ?? now())->diffInMonths(now()) + 1);
            $avgOut = round($totalOut / $months, 1);
            $idleDays = $stat ? (int) now()->diffInDays(Carbon::parse($stat->last_date)) : 0;

            $baseMin = (float) ($p->min_stock ?? 0);
            $adj = $adjustments->get($p->id);
            $min = $adj ? (int) ceil($baseMin * (1 + $adj->adjustment_percentage / 100)) : (int) $baseMin;

            $movement = $avgOut >= 10 ? 'Fast Moving' : ($avgOut >= 3 ? 'Medium Moving' : 'Slow Moving');
            $status = $qty > $min ? 'Aman' : ($qty > 0 ? 'Kritis' : 'Habis');
            $reorder = $qty <= $min;
            $reorderQty = $reorder ? max(0, $min * 2 - $qty) : 0;

            $w->row([
                $p->code ?? '-',
                $p->name ?? '-',
                (float) $qty,
                Konversi::of($p, $qty),
                $avgOut,
                (float) $idleDays,
                $movement,
                $status,
                (float) $min,
                $reorder ? 'Ya' : 'Tidak',
                (float) $reorderQty,
                $reorderQty > 0 ? Konversi::of($p, $reorderQty) : null,
            ], ['cells' => array_filter([
                7 => Status::cell($movement),
                8 => Status::cell($status),
                10 => $reorder ? ['fill' => Theme::WARN_BG, 'font' => Theme::WARN_FG, 'bold' => true] : null,
            ])]);
        }

        $w->totalRow('TOTAL', [3 => 'sum', 11 => 'sum'], ['grand' => true, 'label_col' => 2]);
    }
}