<?php

namespace App\Exports;

use App\Models\Pembelian;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class PembelianExport implements FromView
{
    protected $startDate;

    protected $endDate;

    protected $hari;

    public function __construct($request)
    {
        $tanggal = isset($request->tanggal) ? explode(' - ', $request->tanggal) : null;
        $this->startDate = $tanggal[0] ?? null;
        $this->endDate = $tanggal[1] ?? null;
        $this->hari = $request->hari;
    }

    public function view(): View
    {
        return view('exports.laporan-pembelian', [
            'pembelians' => Pembelian::when($this->hari, function ($query, $hari) {
                return $query->whereDate('created_at', $hari);
            })->when($this->startDate && $this->endDate, function ($query) {
                return $query->whereBetween('created_at', [$this->startDate, $this->endDate]);
            })->get(),
        ]);
    }
}
