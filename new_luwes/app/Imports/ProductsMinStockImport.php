<?php

namespace App\Imports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductsMinStockImport implements ToModel, WithHeadingRow, SkipsEmptyRows
{
    public function model(array $row)
    {
        $product = Product::where('code', $row['kode'])->first();

        if ($product) {
            $product->update(['min_stock' => $row['min_stock'] ?? 0]);
        }

        return null;
    }
}
