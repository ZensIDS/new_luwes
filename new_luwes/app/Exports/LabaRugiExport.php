<?php

namespace App\Exports;

use App\Models\Kas;
use App\Models\Pembelian;
use App\Models\Pengeluaran;
use App\Models\Penjualan;
use App\Models\Refund;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class LabaRugiExport implements FromView
{
    public function view(): View
    {
        $total_penjualan = Penjualan::sum('total');
        $total_refund = Refund::sum('total');
        $total_pembelian = Pembelian::sum('total');
        $total_pengeluaran = Pengeluaran::sum('jumlah');
        $laba_rugi = $total_penjualan - ($total_refund + $total_pembelian + $total_pengeluaran);

        return view('exports.laporan-laba-rugi', [
            'kas' => Kas::get(),
            'pengeluaran' => Pengeluaran::groupBy('category_id')->get(),
            'total_pembelian' => $total_pembelian,
            'total_penjualan' => $total_penjualan,
            'laba_rugi' => $laba_rugi,
        ]);
    }
}
