<?php

namespace App\Exports;

use App\Models\StockMovement;
use App\Support\ReportQuery;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class LaporanBarangKeluarExport implements FromCollection, WithHeadings, WithTitle
{
    use Exportable;
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function title(): string
    {
        return 'Laporan Barang Keluar';
    }

    public function headings(): array
    {
        return ['No', 'Tanggal', 'No Dokumen', 'Tujuan', 'Kode Barang', 'Nama Barang', 'Batch', 'Satuan', 'Qty Keluar', 'Keterangan'];
    }

    public function collection()
    {
        $mulai   = $this->request->input('tanggal_mulai');
        $selesai = $this->request->input('tanggal_selesai');

        $movements = ReportQuery::betweenDates(
            StockMovement::with(['product'])->where('qty_out', '>', 0),
            'created_at', $mulai, $selesai
        )->orderBy('created_at')->get();

        $refs = ReportQuery::resolveReferences($movements, [
            \App\Models\DeliveryOrder::class => ['owner', 'requestOrder.owner'],
        ]);

        $rows = collect();
        $no = 1;

        foreach ($movements as $m) {
            $docCode = '-';
            $tujuan  = '-';

            $ref = ReportQuery::ref($refs, $m);
            $docCode = $ref?->code ?? '-';
            $tujuan = $ref?->owner?->name ?? $ref?->requestOrder?->owner?->name ?? '-';

            preg_match('/SKU:\s*(\S+)/', $m->notes ?? '', $matches);
            $batch = $matches[1] ?? '-';
            $k = $m->product?->konversiDisplay($m->qty_out ?? 0);

            $rows->push([
                $no++,
                $m->created_at->format('d M Y'),
                $docCode,
                $tujuan,
                $m->product?->code ?? '-',
                $m->product?->name ?? '-',
                $batch,
                $m->product?->satuan ?? 'PCS',
                ($m->qty_out ?? 0).($k && $k !== '-' ? " ({$k})" : ''),
                $m->notes ?? '',
            ]);
        }

        return $rows;
    }
}