<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Kresek yang dibeli pada satu penjualan (snapshot nama + harga, tanpa stok). */
class PenjualanKresek extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'penjualan_id',
        'kresek_id',
        'name',
        'price',
        'qty',
        'subtotal',
    ];

    protected $casts = [
        'price' => 'float',
        'qty' => 'integer',
        'subtotal' => 'float',
    ];

    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class);
    }
}