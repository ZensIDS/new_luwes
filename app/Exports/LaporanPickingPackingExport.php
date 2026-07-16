<?php

namespace App\Exports;

use App\Models\PickingList;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class LaporanPickingPackingExport implements FromCollection, WithHeadings, WithTitle
{
    use Exportable;
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function title(): string
    {
        return 'Laporan Picking & Packing';
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal',
            'Kode Picking',
            'Kode DO',
            'Tujuan',
            'Kode Barang',
            'Nama Barang',
            'Lokasi',
            'Ord',
            'Pick',
            'Pack',
            'Status',
            'Picker',
            'Packer',
            'Keterangan'
        ];
    }

    public function collection()
    {
        $mulai   = $this->request->input('tanggal_mulai');
        $selesai = $this->request->input('tanggal_selesai');

        $pickings = PickingList::with(['requestOrder.owner', 'items.product', 'deliveryOrder'])
            ->when($mulai, fn ($q) => $q->whereDate('created_at', '>=', $mulai))
            ->when($selesai, fn ($q) => $q->whereDate('created_at', '<=', $selesai))
            ->orderBy('created_at')
            ->get();

        $rows = collect();
        $no = 1;

        foreach ($pickings as $pk) {
            foreach ($pk->items as $item) {
                $kOrd  = $item->product?->konversiDisplay($item->qty_to_pick) ?? '-';
                $kPick = $item->product?->konversiDisplay($item->qty_picked) ?? '-';

                $rows->push([
                    $no++,
                    Carbon::parse($pk->created_at)->format('d M Y'),
                    $pk->code,
                    $pk->deliveryOrder?->code ?? '-',
                    $pk->requestOrder?->owner?->name ?? '-',
                    $item->product?->code ?? '-',
                    $item->product?->name ?? '-',
                    $item->location ?? '-',
                    $item->qty_to_pick.($kOrd && $kOrd !== '-' ? " ({$kOrd})" : ''),
                    $item->qty_picked.($kPick && $kPick !== '-' ? " ({$kPick})" : ''),
                    $item->qty_picked.($kPick && $kPick !== '-' ? " ({$kPick})" : ''),
                    ucfirst($pk->status ?? '-'),
                    $pk->picker?->name ?? '-',
                    '-',
                    $pk->notes ?? '',
                ]);
            }
        }

        return $rows;
    }
}
