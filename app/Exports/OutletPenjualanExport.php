<?php

namespace App\Exports;

use App\Services\OutletLaporanService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromView;

class OutletPenjualanExport implements FromView
{
    public function __construct(private readonly Request $request) {}

    public function view(): View
    {
        return view('exports.outlet.penjualan', app(OutletLaporanService::class)->sales($this->request));
    }
}
