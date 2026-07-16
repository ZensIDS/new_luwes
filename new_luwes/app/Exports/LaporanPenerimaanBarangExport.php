<?php

namespace App\Exports;

use App\Models\Stock;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class LaporanPenerimaanBarangExport implements FromCollection, WithHeadings, WithTitle
{
    use Exportable;
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function title(): string
    {
        return 'Laporan Penerimaan Barang';
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal',
            'No Dokumen',
            'No PO',
            'Supplier',
            'Kode Barang',
            'Nama Barang',
            'Batch',
            'Expired',
            'Qty',
            'Qty Diterima',
            'Satuan',
            'Kondisi',
            'Keterangan'
        ];
    }

    public function collection()
    {
        $mulai   = $this->request->input('tanggal_mulai');
        $selesai = $this->request->input('tanggal_selesai');

        $stocks = Stock::with(['pembelian.supplier', 'product'])
            ->whereHas('pembelian')
            ->when($mulai, fn ($q) => $q->whereDate('created_at', '>=', $mulai))
            ->when($selesai, fn ($q) => $q->whereDate('created_at', '<=', $selesai))
            ->orderBy('created_at')
            ->get();

        $rows = collect();
        $no = 1;

        foreach ($stocks as $s) {
            $qty        = $s->qty ?? 0;
            $qtyDiterima = $s->qty_diterima ?? 0;
            $kQty      = $s->product?->konversiDisplay($qty);
            $kDiterima  = $s->product?->konversiDisplay($qtyDiterima);
            $rows->push([
                $no++,
                Carbon::parse($s->created_at)->format('d M Y'),
                $s->pembelian?->code_gr ?? ($s->pembelian?->code ?? '-'),
                $s->pembelian?->code ?? '-',
                $s->pembelian?->supplier?->name ?? '-',
                $s->product?->code ?? '-',
                $s->product?->name ?? '-',
                $s->sku ?? '-',
                $s->expired_date ? Carbon::parse($s->expired_date)->format('d/m/Y') : '-',
                $qty.($kQty && $kQty !== '-' ? " ({$kQty})" : ''),
                $qtyDiterima.($kDiterima && $kDiterima !== '-' ? " ({$kDiterima})" : ''),
                $s->product?->satuan ?? 'PCS',
                'Baik',
                $s->pembelian?->notes ?? 'Pembelian',
            ]);
        }

        return $rows;
    }
}
