<?php

namespace App\Exports;

use App\Models\Pengeluaran;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class PengeluaranExport implements FromView
{
    public function view(): View
    {
        $pengeluarans = Pengeluaran::with('category')
            ->orderBy('tanggal')
            ->orderBy('category_id')
            ->get();

        return view('exports.laporan-pengeluaran', [
            'pengeluarans' => $pengeluarans,
        ]);
    }
}
