<?php

namespace App\Exports;

use App\Models\RequestOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class LaporanPRExport implements FromCollection, WithHeadings, WithTitle
{
    use Exportable;
    protected $request;
    public function __construct(Request $request)
    {
        $this->request = $request;
    }
    public function title(): string
    {
        return 'Laporan PR';
    }
    public function headings(): array
    {
        return ['No', 'Tanggal PR', 'Kode PR', 'Outlet', 'Kode Barang', 'Nama Barang', 'QTY', 'Satuan', 'Status', 'Kode PO', 'Keterangan'];
    }
    public function collection()
    {
        $mulai = $this->request->input('tanggal_mulai');
        $selesai = $this->request->input('tanggal_selesai');
        $orders = RequestOrder::with(['owner', 'items.product'])
            ->when($mulai, fn ($q) => $q->whereDate('request_date', '>=', $mulai))
            ->when($selesai, fn ($q) => $q->whereDate('request_date', '<=', $selesai))
            ->orderBy('request_date')->get();
        $rows = collect();
        $no = 1;
        foreach ($orders as $ro) {
            foreach ($ro->items as $item) {
                $k = $item->product?->konversiDisplay($item->qty_requested) ?? '-';

                $rows->push([
                    $no++,
                    Carbon::parse($ro->request_date)->format('d M Y'),
                    $ro->code,
                    $ro->owner?->name ?? '-',
                    $item->product?->code ?? '-',
                    $item->product?->name ?? '-',
                    $item->qty_requested.($k && $k !== '-' ? " ({$k})" : ''),
                    $item->product?->satuan ?? 'PCS',
                    ucfirst($ro->status ?? '-'),
                    '-',
                    ''
                ]);
            }
        }

        return $rows;
    }
}
