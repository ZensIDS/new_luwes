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
            'Stok Batch',
            'Total Stok Produk',
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

        // Total stok fisik per produk = SUM(stocks.qty) semua batch (sama dengan menu Stok/Produk).
        // Min Stok, Selisih, dan Status Stok dinilai terhadap TOTAL ini, bukan qty satu batch.
        $productTotals = Stock::selectRaw('product_id, SUM(qty) as total_qty')
            ->groupBy('product_id')
            ->pluck('total_qty', 'product_id');

        $rows = collect();
        $no = 1;

        foreach ($stocks as $s) {
            $baseMin = $s->product?->min_stock ?? 0;
            $adj = $activeAdjs->get($s->product_id);
            $minStok = $adj
                ? (int) ceil($baseMin * (1 + $adj->adjustment_percentage / 100))
                : (int) $baseMin;
            $totalQty = (int) ($productTotals[$s->product_id] ?? 0);
            $selisih = $totalQty - $minStok;
            $statusStok = $totalQty > $minStok ? 'Aman' : ($totalQty > 0 ? 'Kritis' : 'Habis');
            $statusExp = $s->expired_at && Carbon::parse($s->expired_at)->isPast() ? 'Expired' : 'Belum Expired';

            $qty         = $s->qty ?? 0;
            $konvDisplay = $s->product?->konversiDisplay($qty) ?? '-';
            $konvTotal   = $s->product?->konversiDisplay($totalQty) ?? '-';

            $rows->push([
                $no++,
                $s->product?->code ?? '-',
                $s->product?->name ?? '-',
                $s->sku ?? '-',
                $s->expired_at ? Carbon::parse($s->expired_at)->format('d/m/Y') : '-',
                $s->product?->category?->name ?? '-',
                $s->product?->satuan ?? 'PCS',
                $qty.($konvDisplay && $konvDisplay !== '-' ? " ({$konvDisplay})" : ''),
                $totalQty.($konvTotal && $konvTotal !== '-' ? " ({$konvTotal})" : ''),
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