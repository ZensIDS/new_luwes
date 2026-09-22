<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RefundPenjualan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'penjualan_id',
        'outlet_id',
        'user_id',
        'cashier_session_id',
        'returned_total',
        'replacement_total',
        'difference',
        'payment_method_name',
        'payment_reference',
        'notes',
    ];

    protected $casts = [
        'returned_total' => 'float',
        'replacement_total' => 'float',
        'difference' => 'float',
    ];

    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cashierSession()
    {
        return $this->belongsTo(CashierSession::class);
    }

    public function items()
    {
        return $this->hasMany(RefundPenjualanItem::class);
    }

    public function refundPenjualanItems()
    {
        return $this->items();
    }
}
