<?php

namespace App\Exports;

use App\Exports\LaporanToko\OutletPenjualanRekapSheet;
use App\Exports\LaporanToko\OutletPenjualanSheet;
use App\Services\OutletLaporanService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class OutletPenjualanExport implements WithMultipleSheets
{
    public function __construct(private readonly Request $request) {}

    public function sheets(): array
    {
        // Data dihitung sekali lalu dipakai kedua sheet.
        $data = app(OutletLaporanService::class)->sales($this->request);

        return [
            new OutletPenjualanSheet($this->request, $data),
            new OutletPenjualanRekapSheet($this->request, $data),
        ];
    }
}