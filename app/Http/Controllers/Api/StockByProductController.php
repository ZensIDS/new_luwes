<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;

class StockByProductController extends Controller
{
    public function __invoke(Product $product)
    {
        $stocks = $product->stocks()
            ->select('id', 'sku', 'qty_available', 'expired_at', 'created_at')
            ->where('qty_available', '>', 0)
            ->where('status', 'available')
            ->orderBy('expired_at', 'asc')
            ->get()
            ->map(function ($stock) {
                return [
                    'id' => $stock->id,
                    'sku' => $stock->sku,
                    'qty_available' => $stock->qty_available,
                    'expired_at' => $stock->expired_at ? $stock->expired_at->format('d/M/Y') : null,
                    'created_at' => $stock->created_at->format('d/M/Y'),
                ];
            });

        return response()->json($stocks);
    }
}