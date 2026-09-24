<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'type', //nominal, percentage
        'jenis', //satuan, keseluruhan
        'limit', //usage limit
        'value',
        'min_purchase',
        'start_at',
        'end_at',
        'desc',
        'product_id',
        'kasir_id',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function kasir()
    {
        return $this->belongsTo(User::class, 'kasir_id'); //user role kasir
    }
}
