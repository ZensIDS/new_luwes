<?php

namespace App\Exports;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class LaporanBarangMasukExport implements FromCollection, WithHeadings, WithTitle
{
    use Exportable;
    protected $request;
    public function __construct(Request $request)
    {
        $this->request = $request;
    }
    public function title(): string
    {
        return 'Laporan Barang Masuk';
    }
    public function headings(): array
    {
        return ['No', 'Tanggal', 'No Dokumen', 'Supplier', 'Kode Barang', 'Nama Barang', 'Batch', 'Qty Masuk', 'Satuan', 'Keterangan'];
    }
    public function collection()
    {
        $mulai = $this->request->input('tanggal_mulai');
        $selesai = $this->request->input('tanggal_selesai');
        $movements = StockMovement::with(['product'])->where('qty_in', '>', 0)
            ->when($mulai, fn ($q) => $q->whereDate('created_at', '>=', $mulai))
            ->when($selesai, fn ($q) => $q->whereDate('created_at', '<=', $selesai))
            ->orderBy('created_at')->get();
        $rows = collect();
        $no = 1;
        foreach ($movements as $m) {
            $docCode = '-';
            $supplier = '-';
            if ($m->reference_type && $m->reference_id) {
                $ref = $m->reference_type::find($m->reference_id);
                $docCode = $ref?->code ?? '-';
                if ($m->reference_type === 'App\Models\Pembelian') { $supplier = $ref?->supplier?->name ?? '-'; }
            }
            preg_match('/SKU:\s*(\S+)/', $m->notes ?? '', $matches);
            $k = $m->product?->konversiDisplay($m->qty_in ?? 0);
            $rows->push([
                $no++,
                $m->created_at->format('d M Y'),
                $docCode,
                $supplier,
                $m->product?->code ?? '-',
                $m->product?->name ?? '-',
                $matches[1] ?? '-',
                ($m->qty_in ?? 0).($k && $k !== '-' ? " ({$k})" : ''),
                $m->product?->satuan ?? 'PCS',
                $m->notes ?? ''
            ]);
        }

        return $rows;
    }
}
