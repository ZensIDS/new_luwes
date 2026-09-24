<?php

namespace App\Exports;

use App\Services\OutletLaporanService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromView;

class OutletMinimalStockExport implements FromView
{
    public function __construct(private readonly Request $request) {}

    public function view(): View
    {
        return view('exports.outlet.minimal-stock', app(OutletLaporanService::class)->minimumStock($this->request));
    }
}
