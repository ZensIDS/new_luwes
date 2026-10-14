<?php

namespace App\Exports;

use App\Exports\LaporanGudang\BarangKeluarRekapSheet;
use App\Exports\LaporanGudang\BarangKeluarSheet;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** B10: Laporan Barang Keluar (sheet detail + rekap per tujuan). */
class LaporanBarangKeluarExport implements WithMultipleSheets
{
    public function __construct(private readonly Request $request) {}

    public function sheets(): array
    {
        return [
            new BarangKeluarSheet($this->request),
            new BarangKeluarRekapSheet($this->request),
        ];
    }
}