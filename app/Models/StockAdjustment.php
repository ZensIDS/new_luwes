<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockAdjustment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'adjustment_date',
        'product_id',
        'stock_id',
        'sku',
        'quantity',
        'system_qty',
        'physical_qty',
        'reason',
        'status',
        'keterangan',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
        'quantity'        => 'float',
        'system_qty'      => 'float',
        'physical_qty'    => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }
}
