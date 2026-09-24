<?php

namespace App\Exports;

use App\Services\OutletLaporanService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromView;

class OutletRafaksiExport implements FromView
{
    public function __construct(private readonly Request $request) {}

    public function view(): View
    {
        return view('exports.outlet.rafaksi', app(OutletLaporanService::class)->rafaksi($this->request));
    }
}
