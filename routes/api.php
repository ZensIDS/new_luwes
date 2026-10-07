<?php

use App\Http\Controllers\Api\CurrentUserController;
use App\Http\Controllers\Api\StockByProductController;
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

Route::middleware('auth:sanctum')->get('/user', CurrentUserController::class);

Route::get('/stocks/by-product/{product}', StockByProductController::class);