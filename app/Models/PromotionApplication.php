<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionApplication extends Model
{
    protected $fillable = [
        'penjualan_id',
        'promotion_id',
        'type',
        'name',
        'code',
        'basis_amount',
        'amount',
        'quantity',
        'details',
    ];

    protected $casts = [
        'basis_amount' => 'float',
        'amount' => 'float',
        'quantity' => 'float',
        'details' => 'array',
    ];

    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class);
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }
}
