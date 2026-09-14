<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PenjualanItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'penjualan_id',
        'product_id',
        'stock_id',
        'owner_stock_id',
        'qty',
        'price',
        'subtotal',
        'base_price',
        'base_subtotal',
        'promotion_discount',
        'promotion_details',
        'serial_number',
        'hpp',
        'harga_akhir',
        'disc_brand_type',
        'disc_brand_value',
        'disc_brand_amount',
        'disc_tambahan_type',
        'disc_tambahan_value',
        'disc_tambahan_amount',
        'margin_type',
        'margin_value',
        'margin_amount',
        'harga_aktif',
        'disc_toko_type',
        'disc_toko_value',
        'disc_toko_amount',
        'outlet_surcharge',
    ];

    protected $casts = [
        'hpp' => 'float',
        'harga_akhir' => 'float',
        'disc_brand_value' => 'float',
        'disc_brand_amount' => 'float',
        'disc_tambahan_value' => 'float',
        'disc_tambahan_amount' => 'float',
        'margin_value' => 'float',
        'margin_amount' => 'float',
        'harga_aktif' => 'float',
        'disc_toko_value' => 'float',
        'disc_toko_amount' => 'float',
        'outlet_surcharge' => 'float',
        'base_price' => 'float',
        'base_subtotal' => 'float',
        'promotion_discount' => 'float',
        'promotion_details' => 'array',
    ];

    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }

    public function ownerStock()
    {
        return $this->belongsTo(OwnerStock::class);
    }
}
