<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RefundPembelian extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'tanggal',
        'type',             // gudang_ke_supplier | outlet_ke_gudang
        'status',           // retur | complete
        'kas_id',
        'supplier_id',
        'delivery_order_id', // [NEW] for outlet_ke_gudang, replaces pembelian_id
        'outlet_id',
        'user_id',
        'total',
    ];

    protected $casts = [
        'tanggal' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function deliveryOrder()
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function kas()
    {
        return $this->belongsTo(Kas::class);
    }

    public function refundPembelianItems()
    {
        return $this->hasMany(RefundPembelianItem::class);
    }
}
