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
        'subtotal',
        'discount_total',
        'voucher_total',
        'grand_total',
        'paid_amount',
        'change_amount',
        'payment_method_id',
        'payment_method_name',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'subtotal' => 'float',
        'discount_total' => 'float',
        'voucher_total' => 'float',
        'grand_total' => 'float',
        'paid_amount' => 'float',
        'change_amount' => 'float',
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

    public function vouchers()
    {
        return $this->belongsToMany(Voucher::class, 'voucher_redemptions')
            ->withPivot('outlet_id', 'cashier_id', 'code', 'type', 'value', 'amount')
            ->withTimestamps();
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function getFinalTotalAttribute()
    {
        return $this->grand_total ?? $this->total - $this->discount;
    }
}
