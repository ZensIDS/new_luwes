<?php

namespace App\Exports;

use App\Exports\LaporanToko\OutletSellOutBrandHarianSheet;
use App\Exports\LaporanToko\OutletSellOutBrandInvoiceSheet;
use App\Exports\LaporanToko\OutletSellOutBrandProdukSheet;
use App\Services\OutletLaporanService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Laporan Toko: Sell Out Brand.
 * Berapa banyak satu brand (nama depan barang) terjual pada periode: per invoice, per hari, dan per produk.
 * Angka mengikuti aturan Penjualan Outlet: barang yang diretur tidak dihitung, barang pengganti dihitung.
 */
class OutletSellOutBrandExport implements WithMultipleSheets
{
    public function __construct(private readonly Request $request, private readonly string $brand) {}

    public function sheets(): array
    {
        // Data dihitung sekali lalu dipakai ketiga sheet.
        $data = app(OutletLaporanService::class)->sales($this->request, $this->brand);

        return [
            new OutletSellOutBrandInvoiceSheet($this->request, $data, $this->brand),
            new OutletSellOutBrandHarianSheet($this->request, $data, $this->brand),
            new OutletSellOutBrandProdukSheet($this->request, $data, $this->brand),
        ];
    }
}
