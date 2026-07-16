<?php

namespace App\Exports;

use App\Models\Pembelian;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class LaporanPOExport implements FromCollection, WithHeadings, WithTitle
{
    use Exportable;
    protected $request;
    public function __construct(Request $request)
    {
        $this->request = $request;
    }
    public function title(): string
    {
        return 'Laporan PO';
    }
    public function headings(): array
    {
        return ['No', 'Tanggal PO', 'Kode PO', 'No PR', 'Supplier', 'Kode Barang', 'Nama Barang', 'QTY', 'Satuan', 'Harga Total', 'QTY Diterima', 'Status', 'Keterangan'];
    }
    public function collection()
    {
        $mulai = $this->request->input('tanggal_mulai');
        $selesai = $this->request->input('tanggal_selesai');
        $pembelians = Pembelian::with(['supplier', 'pembelianProducts.product'])
            ->when($mulai, fn ($q) => $q->whereDate('created_at', '>=', $mulai))
            ->when($selesai, fn ($q) => $q->whereDate('created_at', '<=', $selesai))
            ->orderBy('created_at')->get();
        $rows = collect();
        $no = 1;
        foreach ($pembelians as $p) {
            foreach ($p->pembelianProducts as $pp) {
                $kQty = $pp->product?->konversiDisplay($pp->qty) ?? '-';
                $qtyRcv = $pp->qty_received ?? $pp->qty;
                $kRcv = $pp->product?->konversiDisplay($qtyRcv) ?? '-';

                $rows->push([
                    $no++,
                    Carbon::parse($p->created_at)->format('d M Y'),
                    $p->code,
                    $p->requestOrder?->code ?? '-',
                    $p->supplier?->name ?? '-',
                    $pp->product?->code ?? '-',
                    $pp->product?->name ?? '-',
                    $pp->qty.($kQty && $kQty !== '-' ? " ({$kQty})" : ''),
                    $pp->product?->satuan ?? 'PCS',
                    $pp->subtotal,
                    $qtyRcv.($kRcv && $kRcv !== '-' ? " ({$kRcv})" : ''),
                    ucfirst($p->status ?? '-'),
                    ''
                ]);
            }
        }

        return $rows;
    }
}
