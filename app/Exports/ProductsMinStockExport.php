<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductsMinStockExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private bool $templateOnly = false) {}

    /** Dibaca per potongan (chunk). `id` sebagai pembeda urutan agar paging stabil. */
    public function query()
    {
        $query = Product::query()->select(['id', 'code', 'name', 'min_stock'])->orderBy('code')->orderBy('id');

        return $this->templateOnly ? $query->whereRaw('1 = 0') : $query;
    }

    public function headings(): array
    {
        return ['kode', 'nama', 'min_stock'];
    }

    public function map($row): array
    {
        return [
            $row->code,
            $row->name,
            $row->min_stock,
        ];
    }
}