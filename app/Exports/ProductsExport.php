<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private bool $templateOnly = false) {}

    /**
     * Dibaca per potongan (chunk) oleh Laravel Excel, bukan ->get() sekaligus.
     * `suppliers` di-eager-load karena dipakai di map() (sebelumnya 1 query per baris).
     * Mode template: query yang tidak menghasilkan baris, hanya heading.
     */
    public function query()
    {
        $query = Product::query()->with(['category', 'suppliers'])->orderBy('id');

        return $this->templateOnly ? $query->whereRaw('1 = 0') : $query;
    }

    public function headings(): array
    {
        return [
            'kode',
            'nama',
            'kategori',
            'supplier',
            'brand',
            'model',
            'warna',
            'ukuran',
            'satuan',
            'satuan_besar',
            'konversi_qty',
            'konversi',
            'min_stock',
            'lokasi',
            'harga_beli',
            // 'harga_jual',
            // 'diskon',
            // 'berat',
            'deskripsi'
        ];
    }

    public function map($row): array
    {
        return [
            $row->code,
            $row->name,
            $row->category?->name,
            $row->suppliers->pluck('name')->implode(', '),
            $row->brand,
            $row->model,
            $row->warna,
            $row->ukuran,
            $row->satuan,
            $row->satuan_besar,
            $row->konversi_qty,
            $row->konversi_string,
            $row->min_stock,
            $row->lokasi,
            $row->harga_beli,
            // $row->harga_jual,
            // $row->diskon,
            // $row->berat,
            $row->desc,
        ];
    }
}