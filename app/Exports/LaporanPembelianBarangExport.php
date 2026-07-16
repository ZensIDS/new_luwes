<?php

namespace App\Exports;

use App\Models\Pembelian;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class LaporanPembelianBarangExport implements FromCollection, WithHeadings, WithTitle
{
    use Exportable;
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function title(): string
    {
        return 'Laporan Pembelian Barang';
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal',
            'Kode PO',
            'Supplier',
            'Kode Barang',
            'Nama Barang',
            'Qty',
            'Satuan',
            'Harga Satuan',
            'Total Harga',
            'Status',
            'Keterangan'
        ];
    }

    public function collection()
    {
        $mulai   = $this->request->input('tanggal_mulai');
        $selesai = $this->request->input('tanggal_selesai');

        $pembelians = Pembelian::with(['supplier', 'pembelianProducts.product'])
            ->when($mulai, fn ($q) => $q->whereDate('created_at', '>=', $mulai))
            ->when($selesai, fn ($q) => $q->whereDate('created_at', '<=', $selesai))
            ->orderBy('created_at')
            ->get();

        $rows = collect();
        $no = 1;

        foreach ($pembelians as $p) {
            foreach ($p->pembelianProducts as $pp) {
                $k = $pp->product?->konversiDisplay($pp->qty ?? 0);
                $rows->push([
                    $no++,
                    Carbon::parse($p->created_at)->format('d M Y'),
                    $p->code,
                    $p->supplier?->name ?? '-',
                    $pp->product?->code ?? '-',
                    $pp->product?->name ?? '-',
                    ($pp->qty ?? 0).($k && $k !== '-' ? " ({$k})" : ''),
                    $pp->product?->satuan ?? 'PCS',
                    $pp->harga_beli,
                    $pp->subtotal,
                    ucfirst($p->status ?? '-'),
                    '',
                ]);
            }
        }

        return $rows;
    }
}
