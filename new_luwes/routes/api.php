<?php

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/stocks/by-product/{product}', function (Product $product) {
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
});