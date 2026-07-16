<?php

namespace App\Imports;

use App\Models\Category;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CategoriesImport implements ToModel, WithHeadingRow, SkipsEmptyRows
{
    public function model(array $row)
    {
        return Category::updateOrCreate(
            ['name' => $row['nama']],
            // ['type' => $row['tipe']]
        );
    }
}
