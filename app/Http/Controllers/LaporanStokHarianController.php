<?php

namespace App\Http\Controllers;

use App\Exports\StokTokoHarianExport;
use App\Services\PriceFolderIndex;
use App\Support\OutletAccess;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Laporan Toko: Stok Toko Harian (Excel).
 * Route: GET /laporan/outlet/stok-harian?tanggal=Y-m-d&outlet_id=&brand=
 * Saran brand: GET /laporan/outlet/stok-harian/brands?q=&outlet_id=
 */
class LaporanStokHarianController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'outlet_id' => ['nullable', 'integer'],
            'brand' => ['nullable', 'string', 'max:100'],
        ]);

        $export = new StokTokoHarianExport($request);

        return Excel::download($export, $export->fileName());
    }

    /**
     * Saran brand saat mengetik: kata pertama nama barang yang diawali ketikan dan
     * yang ada di folder Master Harga Jual outlet yang boleh diakses (maks 15, urut abjad).
     */
    public function brands(Request $request)
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:50'],
            'outlet_id' => ['nullable', 'integer'],
        ]);

        $q = PriceFolderIndex::normalize((string) $request->input('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $outletId = OutletAccess::id($request, false);
        $outletIds = $outletId ? [$outletId] : OutletAccess::outlets()->pluck('id')->all();
        // Sumber sama dengan folder Master Harga Jual (PriceFolderIndex, sudah di-cache per outlet).
        $index = app(PriceFolderIndex::class);

        $brands = collect($outletIds)
            ->flatMap(fn ($id) => $index->folders((int) $id))
            ->pluck('key')
            ->filter(fn ($key) => $key !== PriceFolderIndex::OTHER && str_starts_with((string) $key, $q))
            ->unique()
            ->sort()
            ->take(15)
            ->values();

        return response()->json($brands);
    }
}