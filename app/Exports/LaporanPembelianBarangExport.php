<?php

namespace App\Exports;

use App\Exports\LaporanGudang\PembelianBarangRekapSheet;
use App\Exports\LaporanGudang\PembelianBarangSheet;
use App\Models\Pembelian;
use App\Support\ReportQuery;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** B2: Laporan Pembelian Barang (sheet detail + rekap per supplier). */
class LaporanPembelianBarangExport implements WithMultipleSheets
{
    public function __construct(private readonly Request $request) {}

    public function sheets(): array
    {
        $meta = \App\Exports\LaporanToko\ReportMeta::fromRequest($this->request, '', 'Pembelian-Barang');

        // Data dibaca sekali lalu dipakai kedua sheet.
        $orders = Pembelian::with(['supplier', 'pembelianProducts.product'])
            ->tap(fn ($q) => ReportQuery::betweenDates($q, 'created_at', $meta->start->toDateString(), $meta->end->toDateString()))
            ->orderBy('created_at')->orderBy('id')
            ->get();

        return [
            new PembelianBarangSheet($this->request, $orders),
            new PembelianBarangRekapSheet($this->request, $orders),
        ];
    }
}