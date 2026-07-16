<?php

namespace App\Exports;

use App\Models\Category;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CategoriesExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private bool $templateOnly = false) {}

    public function collection()
    {
        if ($this->templateOnly) { return collect([]); }
        $query = Category::query();
        // if ($this->type) { $query->where('type', $this->type); }

        return $query->get();
    }

    public function headings(): array
    {
        return ['nama'];
    }

    public function map($row): array
    {
        return [$row->name];
    }
}
