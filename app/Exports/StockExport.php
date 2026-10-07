<?php

namespace App\Exports;

use App\Models\Stock;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class StockExport implements FromQuery, WithHeadings, WithMapping, WithTitle
{
    use Exportable;

    /** Nomor urut baris; map() dipanggil berurutan per baris oleh Laravel Excel. */
    private int $no = 0;

    /** @var \Illuminate\Support\Collection<int, \App\Models\ProductMinimumAdjustment>|null */
    private $activeAdjs = null;

    /** @var \Illuminate\Support\Collection<int|string, mixed>|null */
    private $productTotals = null;

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

    /**
     * Dibaca per potongan (chunk) oleh Laravel Excel, bukan ->get() seluruh batch stok.
     * `id` sebagai pembeda urutan agar paging stabil (urutan produk tetap sama).
     */
    public function query()
    {
        return Stock::query()
            ->with(['product.category'])
            ->orderBy('product_id')
            ->orderBy('id');
    }

    public function map($s): array
    {
        // Data pendukung dimuat sekali (bukan per baris) saat baris pertama dipetakan.
        if ($this->activeAdjs === null) {
            $this->activeAdjs = \App\Models\ProductMinimumAdjustment::activeOn(now()->toDateString())
                ->orderByDesc('active_from')
                ->orderByDesc('id')
                ->get()
                ->keyBy('product_id');

            // Total stok fisik per produk = SUM(stocks.qty) semua batch (sama dengan menu Stok/Produk).
            // Min Stok, Selisih, dan Status Stok dinilai terhadap TOTAL ini, bukan qty satu batch.
            $this->productTotals = Stock::selectRaw('product_id, SUM(qty) as total_qty')
                ->groupBy('product_id')
                ->pluck('total_qty', 'product_id');
        }

        $baseMin = $s->product?->min_stock ?? 0;
        $adj = $this->activeAdjs->get($s->product_id);
        $minStok = $adj
            ? (int) ceil($baseMin * (1 + $adj->adjustment_percentage / 100))
            : (int) $baseMin;
        $totalQty = (int) ($this->productTotals[$s->product_id] ?? 0);
        $selisih = $totalQty - $minStok;
        $statusStok = $totalQty > $minStok ? 'Aman' : ($totalQty > 0 ? 'Kritis' : 'Habis');
        $statusExp = $s->expired_at && Carbon::parse($s->expired_at)->isPast() ? 'Expired' : 'Belum Expired';

        $qty         = $s->qty ?? 0;
        $konvDisplay = $s->product?->konversiDisplay($qty) ?? '-';
        $konvTotal   = $s->product?->konversiDisplay($totalQty) ?? '-';

        return [
            ++$this->no,
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
        ];
    }
}