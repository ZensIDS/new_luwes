<?php

namespace App\Exports;

use App\Models\Pembelian;
use Illuminate\Contracts\View\View;

use Maatwebsite\Excel\Concerns\FromView;

class PembelianSupplierExport implements FromView
{
    protected $startDate;

    protected $endDate;

    protected $hari;

    protected $supplier_id;

    protected $outlet_id;

    public function __construct($request)
    {
        $tanggal = isset($request->tanggal) ? explode(' - ', $request->tanggal) : null;
        $this->startDate = $tanggal[0] ?? null;
        $this->endDate = $tanggal[1] ?? null;
        $this->supplier_id = $request->supplier_id;
        $this->outlet_id = $request->outlet_id;
        $this->hari = $request->hari;
    }

    public function view(): View
    {
        $pembelians = Pembelian::when($this->hari, function ($query, $hari) {
            return $query->whereDate('created_at', $hari);
        })->when($this->supplier_id, function ($query, $supplier_id) {
            return $query->where('supplier_id', $supplier_id);
        })->when($this->outlet_id, function ($query, $outlet_id) {
            return $query->where('outlet_id', $outlet_id);
        })
            ->when($this->startDate && $this->endDate, function ($query) {
                return $query->whereBetween('created_at', [$this->startDate, $this->endDate]);
            })->get();

        return view('exports.laporan-pembelian-supplier', ['pembelians' => $pembelians]);
    }
}
