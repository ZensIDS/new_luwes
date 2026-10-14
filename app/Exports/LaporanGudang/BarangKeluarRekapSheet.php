<?php

namespace App\Exports\LaporanGudang;

use App\Exports\LaporanToko\BaseLaporanTokoExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use App\Models\DeliveryOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * B10 sheet 2: rekap Barang Keluar per tujuan (outlet).
 *
 * Dihitung langsung di database (GROUP BY) dari SEMUA data periode, jadi tidak terpengaruh
 * batas baris sheet detail (StockMovementExport::MAX_ROWS) dan tidak memuat baris ke memori.
 * Tujuan = outlet pada Surat Jalan, atau outlet pada PR bila Surat Jalan tidak punya outlet;
 * pergerakan yang bukan pengiriman (retur ke supplier, adjustment, transaksi toko, dst.)
 * masuk ke baris "-" sama seperti kolom Tujuan di sheet detail.
 */
class BarangKeluarRekapSheet extends BaseLaporanTokoExport
{
    public function __construct(private readonly Request $request) {}

    protected function makeMeta(): ReportMeta
    {
        return ReportMeta::fromRequest($this->request, 'REKAP BARANG KELUAR PER TUJUAN', 'Barang-Keluar');
    }

    protected function sheetName(): string
    {
        return 'Rekap Tujuan';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $w->columns([
            ['h' => 'Tujuan', 'min' => 28, 'max' => 45],
            ['h' => 'Jumlah Dokumen', 'fmt' => Theme::FMT_NUMBER, 'min' => 14],
            ['h' => 'Jumlah Baris', 'fmt' => Theme::FMT_NUMBER, 'min' => 12],
            ['h' => 'Total Qty Keluar', 'fmt' => Theme::FMT_NUMBER, 'min' => 16],
            ['h' => '% dari Total', 'fmt' => Theme::FMT_PERCENT, 'min' => 12],
        ])->kop()->info()->header();

        $rows = $this->rekap($meta);
        $grand = (float) $rows->sum('qty');

        foreach ($rows as $r) {
            $w->row([
                (string) $r->tujuan,
                (int) $r->dokumen,
                (int) $r->baris,
                (float) $r->qty,
                $grand > 0 ? (float) $r->qty / $grand : 0.0,
            ]);
        }

        $w->totalRow('TOTAL', [2 => 'sum', 3 => 'sum', 4 => 'sum', 5 => 'sum'], ['grand' => true]);
    }

    private function rekap(ReportMeta $meta)
    {
        $query = DB::table('stock_movements as sm')
            ->leftJoin('delivery_orders as d', function ($j) {
                $j->on('d.id', '=', 'sm.reference_id')
                    ->where('sm.reference_type', '=', DeliveryOrder::class)
                    ->whereNull('d.deleted_at');
            })
            ->leftJoin('request_orders as r', function ($j) {
                $j->on('r.id', '=', 'd.request_order_id')->whereNull('r.deleted_at');
            })
            ->leftJoin('outlets as o', function ($j) {
                $j->on('o.id', '=', DB::raw('COALESCE(d.owner_id, r.owner_id)'))->whereNull('o.deleted_at');
            })
            ->where('sm.qty_out', '>', 0)
            ->where('sm.created_at', '>=', $meta->start->copy()->startOfDay())
            ->where('sm.created_at', '<=', $meta->end->copy()->endOfDay())
            ->groupBy(DB::raw("COALESCE(o.name, '-')"))
            ->selectRaw("COALESCE(o.name, '-') as tujuan")
            ->selectRaw("COUNT(DISTINCT CONCAT(sm.reference_type, '|', sm.reference_id)) as dokumen")
            ->selectRaw('COUNT(*) as baris')
            ->selectRaw('SUM(sm.qty_out) as qty');

        // Tujuan bernama dulu (qty terbesar di atas); baris "-" selalu di akhir.
        return $query
            ->orderByRaw("CASE WHEN COALESCE(o.name, '-') = '-' THEN 1 ELSE 0 END")
            ->orderByDesc('qty')
            ->get();
    }
}