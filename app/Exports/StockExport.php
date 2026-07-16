<?php

namespace App\Exports;

use App\Models\Stock;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class StockExport implements FromCollection, WithHeadings, WithTitle
{
    use Exportable;

    public function title(): string
    {
        return 'Laporan Stok Barang';
    }

    public function headings(): array
    {
        return [
            'No',
            'Kode Barang',
            'Nama Barang',
            'Batch',
            'Expired Date',
            'Kategori',
            'Satuan',
            'Stok',
            'Min Stok',
            'Selisih',
            'Status Stok',
            'Status Expired',
            'Lokasi',
        ];
    }

    public function collection()
    {
        $stocks = Stock::with(['product.category', 'pembelian'])
            ->orderBy('product_id')
            ->get();

        $today = now()->toDateString();
        $activeAdjs = \App\Models\ProductMinimumAdjustment::activeOn($today)
            ->orderByDesc('active_from')
            ->orderByDesc('id')
            ->get()
            ->keyBy('product_id');

        $rows = collect();
        $no = 1;

        foreach ($stocks as $s) {
            $baseMin = $s->product?->min_stock ?? 0;
            $adj = $activeAdjs->get($s->product_id);
            $minStok = $adj
                ? (int) ceil($baseMin * (1 + $adj->adjustment_percentage / 100))
                : (int) $baseMin;
            $selisih = ($s->qty ?? 0) - $minStok;
            $statusStok = ($s->qty ?? 0) > $minStok ? 'Aman' : (($s->qty ?? 0) > 0 ? 'Kritis' : 'Habis');
            $statusExp = $s->expired_at && Carbon::parse($s->expired_at)->isPast() ? 'Expired' : 'Belum Expired';

            $qty         = $s->qty ?? 0;
            $konvDisplay = $s->product?->konversiDisplay($qty) ?? '-';

            $rows->push([
                $no++,
                $s->product?->code ?? '-',
                $s->product?->name ?? '-',
                $s->sku ?? '-',
                $s->expired_at ? Carbon::parse($s->expired_at)->format('d/m/Y') : '-',
                $s->product?->category?->name ?? '-',
                $s->product?->satuan ?? 'PCS',
                $qty.($konvDisplay && $konvDisplay !== '-' ? " ({$konvDisplay})" : ''),
                $minStok,
                ($selisih >= 0 ? '+' : '').$selisih,
                $statusStok,
                $s->expired_at ? $statusExp : '-',
                $s->location ?? '-',
            ]);
        }

        return $rows;
    }
}
