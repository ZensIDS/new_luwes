<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Penjualan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'customer_id',
        'outlet_id',
        'kasir_id',
        'kas_id',
        'voucher_id',
        'salesman_id',
        'discount',
        'total',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function kasir()
    {
        return $this->belongsTo(User::class, 'kasir_id');
    }

    public function kas()
    {
        return $this->belongsTo(Kas::class);
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function salesman()
    {
        return $this->belongsTo(Salesman::class);
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }

    public function items()
    {
        return $this->hasMany(PenjualanItem::class);
    }

    public function getFinalTotalAttribute()
    {
        return $this->total - $this->discount;
    }
}
