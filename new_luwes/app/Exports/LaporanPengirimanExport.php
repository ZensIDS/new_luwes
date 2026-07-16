<?php

namespace App\Exports;

use App\Models\DeliveryOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class LaporanPengirimanExport implements FromCollection, WithHeadings, WithTitle
{
    use Exportable;
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function title(): string
    {
        return 'Laporan Pengiriman Barang';
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal',
            'No Surat Jalan',
            'No Dokumen',
            'Tujuan',
            'Kode Barang',
            'Nama Barang',
            'Batch',
            'Qty',
            'Satuan',
            'Status',
            'Keterangan'
        ];
    }

    public function collection()
    {
        $mulai   = $this->request->input('tanggal_mulai');
        $selesai = $this->request->input('tanggal_selesai');

        $deliveries = DeliveryOrder::with(['owner', 'requestOrder', 'items.product'])
            ->when($mulai, fn ($q) => $q->whereDate('delivery_date', '>=', $mulai))
            ->when($selesai, fn ($q) => $q->whereDate('delivery_date', '<=', $selesai))
            ->orderBy('delivery_date')
            ->get();

        $rows = collect();
        $no = 1;

        foreach ($deliveries as $do) {
            foreach ($do->items as $item) {
                $k = $item->product?->konversiDisplay($item->qty) ?? '-';

                $rows->push([
                    $no++,
                    Carbon::parse($do->delivery_date)->format('d M Y'),
                    $do->code,
                    $do->requestOrder?->code ?? '-',
                    $do->owner?->name ?? '-',
                    $item->product?->code ?? '-',
                    $item->product?->name ?? '-',
                    $item->sku ?? '-',
                    $item->qty.($k && $k !== '-' ? " ({$k})" : ''),
                    $item->product?->satuan ?? 'PCS',
                    ucfirst($do->status ?? '-'),
                    '',
                ]);
            }
        }

        return $rows;
    }
}
