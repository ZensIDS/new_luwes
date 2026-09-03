<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherRedemption extends Model
{
    protected $fillable = [
        'voucher_id',
        'penjualan_id',
        'outlet_id',
        'cashier_id',
        'code',
        'type',
        'value',
        'amount',
    ];

    protected $casts = [
        'value' => 'float',
        'amount' => 'float',
    ];

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }
}
