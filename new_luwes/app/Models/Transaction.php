<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'penjualan_id',
        'payment_method',
        'tanggal',
        'status',
        'pic',
    ];

    protected $casts = [
        'tanggal' => 'datetime',
    ];

    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class);
    }

    public function payment()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method');
    }
}
