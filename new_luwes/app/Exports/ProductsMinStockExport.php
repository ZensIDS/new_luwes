<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductsMinStockExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private bool $templateOnly = false) {}

    public function collection()
    {
        return $this->templateOnly ? collect([]) : Product::orderBy('code')->get();
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
