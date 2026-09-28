<?php

namespace App\Exports;

use App\Models\StockMovement;
use Carbon\Carbon;
use App\Support\ReportQuery;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class LaporanAktifitasExport implements FromCollection, WithHeadings, WithTitle
{
    use Exportable;
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function title(): string
    {
        return 'Laporan Aktivitas Gudang';
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal',
            'Jenis Aktivitas',
            'No Dokumen',
            'Kode Barang',
            'Nama Barang',
            'Qty',
            'Satuan',
            'Lokasi',
            'PIC',
            'Status',
            'Keterangan'
        ];
    }

    public function collection()
    {
        $mulai   = $this->request->input('tanggal_mulai');
        $selesai = $this->request->input('tanggal_selesai');

        // product.suppliers di-eager-load (sebelumnya lazy-load per baris)
        $movements = ReportQuery::betweenDates(
            StockMovement::with(['product.suppliers']),
            'created_at', $mulai, $selesai
        )->orderBy('created_at')->get();

        $refs = ReportQuery::resolveReferences($movements);

        $movements = $movements
            ->map(function ($m) use ($refs) {
                $docCode = ReportQuery::ref($refs, $m)?->code ?? '-';
                $jenis = $m->qty_in > 0 ? 'Penerimaan' : 'Pengiriman';
                $qty   = max($m->qty_in ?? 0, $m->qty_out ?? 0);

                return (object) [
                    'created_at' => $m->created_at,
                    'jenis'      => $jenis,
                    'doc_code'   => $docCode,
                    'product'    => $m->product,
                    'qty'        => $qty,
                    'type'      => $m->type,
                    'notes'      => $m->notes,
                ];
            });

        $rows = collect();
        $no = 1;

        foreach ($movements as $m) {
            $k = $m->product?->konversiDisplay($m->qty ?? 0);
            $rows->push([
                $no++,
                Carbon::parse($m->created_at)->format('d M Y'),
                $m->jenis,
                $m->doc_code,
                $m->product?->code ?? '-',
                $m->product?->name ?? '-',
                ($m->qty ?? 0).($k && $k !== '-' ? " ({$k})" : ''),
                $m->product?->satuan ?? 'PCS',
                $m->product?->lokasi,
                optional($m->product?->suppliers)->pluck('pic_supplier')?->filter()->implode(', '),
                $m->type,
                $m->notes ?? '',
            ]);
        }

        return $rows;
    }
}