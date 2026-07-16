<?php

namespace App\Exports;

use App\Models\Stock;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class LaporanPergerakanExport implements FromCollection, WithHeadings, WithTitle
{
    use Exportable;
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function title(): string
    {
        return 'Laporan Pergerakan dan Kebutuhan Stok';
    }

    public function headings(): array
    {
        return [
            'No',
            'Kode Barang',
            'Nama Barang',
            'Stok',
            'Rata-rata Keluar/Bln',
            'Hari Tanpa Trx',
            'Kategori',
            'Status Stok',
            'Min Stok',
            'Saran Reorder',
            'Qty Reorder',
            'Keterangan'
        ];
    }

    public function collection()
    {
        $movementStats = StockMovement::selectRaw('product_id, SUM(qty_out) as total_out, MIN(created_at) as first_date, MAX(created_at) as last_date')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $stocks = Stock::with(['product.category'])->get();

        $rows = collect();
        $no = 1;

        foreach ($stocks as $s) {
            $stat = $movementStats[$s->product_id] ?? null;
            $totalOut = (int) ($stat?->total_out ?? 0);
            $months = max(1, (int) Carbon::parse($stat?->first_date ?? now())->diffInMonths(now()) + 1);
            $avgKeluar = round($totalOut / $months, 1);
            $hariTanpa = $stat ? now()->diffInDays(Carbon::parse($stat->last_date)) : 0;
            $minStok = $s->product?->min_stock ?? 0;

            $kategori = $avgKeluar >= 10 ? 'Fast Moving' : ($avgKeluar >= 3 ? 'Medium Moving' : 'Slow Moving');
            $statusStok = ($s->qty ?? 0) > $minStok ? 'Aman' : (($s->qty ?? 0) > 0 ? 'Kritis' : 'Habis');
            $saranReorder = ($s->qty ?? 0) <= $minStok ? 'Ya' : 'Tidak';
            $qtyReorder = ($s->qty ?? 0) <= $minStok ? max(0, $minStok * 2 - ($s->qty ?? 0)) : 0;

            $qty      = $s->qty ?? 0;
            $kStok    = $s->product?->konversiDisplay($qty);
            $kReorder = $qtyReorder > 0 ? $s->product?->konversiDisplay($qtyReorder) : null;
            $rows->push([
                $no++,
                $s->product?->code ?? '-',
                $s->product?->name ?? '-',
                $qty.($kStok && $kStok !== '-' ? " ({$kStok})" : ''),
                $avgKeluar,
                $hariTanpa,
                $kategori,
                $statusStok,
                $minStok,
                $saranReorder,
                $qtyReorder > 0 ? ($qtyReorder.($kReorder && $kReorder !== '-' ? " ({$kReorder})" : '')) : 0,
                '',
            ]);
        }

        return $rows;
    }
}
