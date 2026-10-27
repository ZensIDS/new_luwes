<?php

namespace App\Exports;

use App\Exports\LaporanToko\BaseLaporanTokoExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use App\Models\Outlet;
use App\Models\OutletPrice;
use App\Services\PriceFolderIndex;
use App\Support\OutletAccess;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Laporan Toko: daftar barang satu outlet (hanya Barcode dan Nama Barang), laporan posisi per waktu cetak.
 *
 * Barang = produk yang punya aturan harga (Master Harga Jual) di outlet terpilih.
 * Cakupan: semua barang, atau satu folder (kata pertama nama produk, sama dengan folder di Master Harga Jual).
 * Parameter request: outlet_id (wajib), folder (opsional; "#" = folder "#/Lainnya").
 */
class OutletProductListExport extends BaseLaporanTokoExport
{
    private ?int $outletId = null;
    private ?string $folder = null;
    private bool $resolved = false;

    public function __construct(private readonly Request $request) {}

    /** Outlet wajib dipilih; staff-outlet/kasir otomatis terkunci ke outlet sendiri (aturan OutletAccess). */
    private function resolve(): void
    {
        if ($this->resolved) {
            return;
        }

        $this->outletId = OutletAccess::id($this->request, true);

        $folder = trim((string) $this->request->input('folder', ''));
        $this->folder = $folder === '' ? null : PriceFolderIndex::normalize($folder);

        $this->resolved = true;
    }

    private function folderLabel(): ?string
    {
        if ($this->folder === null) {
            return null;
        }

        return $this->folder === PriceFolderIndex::OTHER ? PriceFolderIndex::OTHER_LABEL : $this->folder;
    }

    /** Query dasar: id outlet + (opsional) folder, urut A-Z nama produk (sama dengan Master Harga Jual). */
    private function query()
    {
        $this->resolve();

        return OutletPrice::query()
            ->where('outlet_prices.outlet_id', $this->outletId)
            ->leftJoin('products', 'products.id', '=', 'outlet_prices.product_id')
            ->when($this->folder !== null, fn ($query) => app(PriceFolderIndex::class)->constrain($query, $this->folder))
            ->orderBy('products.name')
            ->orderBy('outlet_prices.id');
    }

    protected function makeMeta(): ReportMeta
    {
        $this->resolve();

        $outletName = Outlet::whereKey($this->outletId)->value('name') ?? '-';
        $folderLabel = $this->folderLabel();
        $total = (clone $this->query())->reorder()->count('outlet_prices.id');

        $base = ReportMeta::fromRequest($this->request, 'DAFTAR BARANG OUTLET', 'Daftar-Barang-Toko', [], now());

        return new ReportMeta(
            $base->title,
            'Daftar Barang Toko '.$outletName.($folderLabel === null ? '' : ' Folder '.$folderLabel),
            $base->start,
            $base->end,
            [
                'Outlet' => $outletName,
                'Cakupan' => $folderLabel === null ? 'Semua barang' : 'Folder '.$folderLabel,
                'Total Barang' => number_format($total, 0, ',', '.'),
            ],
            $base->settings,
            $base->printedBy,
            $base->printedAt,
            $base->snapshot,
        );
    }

    protected function sheetName(): string
    {
        return 'Daftar Barang';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $w->columns([
            ['h' => 'Barcode', 'text' => true, 'align' => 'left', 'min' => 16],
            ['h' => 'Nama Barang', 'max' => 60],
        ])->kop()->info()->header();

        $rows = $this->query()
            ->select('products.code as barcode', 'products.name as product_name')
            ->toBase()
            ->lazy(1000);

        $count = 0;
        foreach ($rows as $row) {
            $w->row([
                $row->barcode ?: null,
                $row->product_name ?: null,
            ]);
            $count++;
        }

        // Tanpa data: tidak ada baris total supaya pesan "Tidak ada data" tampil.
        if ($count > 0) {
            $w->totalRow('TOTAL BARANG', [2 => 'count'], [
                'grand' => true,
                'fmt' => [2 => '#,##0" barang"'],
            ]);
        }
    }
}