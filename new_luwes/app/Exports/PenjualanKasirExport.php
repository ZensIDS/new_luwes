<?php

namespace App\Exports;

use App\Models\Penjualan;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class PenjualanKasirExport implements FromView
{
    protected $startDate;

    protected $endDate;

    protected $hari;

    protected $outlet_id;

    protected $kasir_id;

    public function __construct($request)
    {
        $tanggal = isset($request->tanggal) ? explode(' - ', $request->tanggal) : null;
        $this->startDate = $tanggal[0] ?? null;
        $this->endDate = $tanggal[1] ?? null;
        $this->outlet_id = $request->outlet_id;
        $this->kasir_id = $request->kasir_id;
        $this->hari = $request->hari;
    }

    public function view(): View
    {
        $penjualans = Penjualan::when($this->hari, function ($query, $hari) {
            return $query->whereDate('created_at', $hari);
        })->when($this->kasir_id, function ($query, $kasir_id) {
            return $query->where('kasir_id', $kasir_id);
        })->when($this->outlet_id, function ($query, $outlet_id) {
            return $query->where('outlet_id', $outlet_id);
        })
            ->when($this->startDate && $this->endDate, function ($query) {
                return $query->whereBetween('created_at', [$this->startDate, $this->endDate]);
            })->get();

        return view('exports.laporan-penjualan-kasir', ['penjualans' => $penjualans]);
    }
}
