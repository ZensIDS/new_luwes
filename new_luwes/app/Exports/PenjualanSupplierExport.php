<?php

namespace App\Exports;

use App\Models\Penjualan;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class PenjualanSupplierExport implements FromView
{
    protected $startDate;

    protected $endDate;

    protected $hari;

    protected $supplier_id;

    public function __construct($request)
    {
        $tanggal = isset($request->tanggal) ? explode(' - ', $request->tanggal) : null;
        $this->startDate = $tanggal[0] ?? null;
        $this->endDate = $tanggal[1] ?? null;
        $this->supplier_id = $request->supplier_id;
        $this->hari = $request->hari;
    }

    public function view(): View
    {
        $penjualans = Penjualan::when($this->hari, function ($query, $hari) {
            return $query->whereDate('created_at', $hari);
        })
            ->when($this->startDate && $this->endDate, function ($query) {
                return $query->whereBetween('created_at', [$this->startDate, $this->endDate]);
            })
            ->when($this->supplier_id, function ($query) {
                return $query->whereHas('items.product', function ($query) {
                    return $query->where('supplier_id', $this->supplier_id);
                });
            })
            ->get();

        return view('exports.laporan-penjualan-supplier', ['penjualans' => $penjualans]);
    }
}
