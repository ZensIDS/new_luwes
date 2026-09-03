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
        'serial_number',
        'hpp',
        'harga_akhir',
        'disc_brand_type',
        'disc_brand_value',
        'disc_brand_amount',
        'margin_type',
        'margin_value',
        'margin_amount',
        'harga_aktif',
        'disc_toko_type',
        'disc_toko_value',
        'disc_toko_amount',
    ];

    protected $casts = [
        'hpp' => 'float',
        'harga_akhir' => 'float',
        'disc_brand_value' => 'float',
        'disc_brand_amount' => 'float',
        'margin_value' => 'float',
        'margin_amount' => 'float',
        'harga_aktif' => 'float',
        'disc_toko_value' => 'float',
        'disc_toko_amount' => 'float',
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
