<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundPenjualanItem extends Model
{
    protected $fillable = [
        'refund_penjualan_id',
        'type',
        'product_id',
        'penjualan_item_id',
        'owner_stock_id',
        'qty',
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'unit_price' => 'float',
        'subtotal' => 'float',
    ];

    public function refundPenjualan()
    {
        return $this->belongsTo(RefundPenjualan::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function penjualanItem()
    {
        return $this->belongsTo(PenjualanItem::class);
    }

    public function ownerStock()
    {
        return $this->belongsTo(OwnerStock::class);
    }
}
