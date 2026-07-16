<?php

namespace App\Exports;

use App\Models\Penjualan;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class PenjualanExport implements FromView
{
    protected $startDate;

    protected $endDate;

    protected $hari;

    protected $outlet_id;

    public function __construct($request)
    {
        $tanggal = isset($request->tanggal) ? explode(' - ', $request->tanggal) : null;
        $this->startDate = $tanggal[0] ?? null;
        $this->endDate = $tanggal[1] ?? null;
        $this->outlet_id = $request->outlet_id;
        $this->hari = $request->hari;
    }

    public function view(): View
    {
        return view('exports.laporan-penjualan', [
            'penjualans' => Penjualan::when($this->hari, function ($query, $hari) {
                return $query->whereDate('created_at', $hari);
            })->when($this->outlet_id, function ($query, $outlet_id) {
                return $query->where('outlet_id', $outlet_id);
            })->when($this->startDate && $this->endDate, function ($query) {
                return $query->whereBetween('created_at', [$this->startDate, $this->endDate]);
            })->get(),
        ]);
    }
}
