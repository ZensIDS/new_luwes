<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutletPurchaseItem extends Model
{
    protected $fillable = [
        'outlet_purchase_id',
        'product_id',
        'owner_stock_id',
        'qty',
        'harga_beli',
        'subtotal',
        'batch_number',
        'expired_at',
    ];

    protected $casts = [
        'harga_beli' => 'float',
        'subtotal' => 'float',
        'expired_at' => 'date',
    ];

    public function purchase()
    {
        return $this->belongsTo(OutletPurchase::class, 'outlet_purchase_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function ownerStock()
    {
        return $this->belongsTo(OwnerStock::class);
    }
}
