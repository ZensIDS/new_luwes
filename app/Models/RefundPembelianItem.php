<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RefundPembelianItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'refund_pembelian_id',
        'product_id',
        'stock_pembelian_id',
        'stock_id',
        'sku',
        'qty',
        'harga',
        'alasan',
        'resolution', // barang | uang
    ];

    public function refundPembelian()
    {
        return $this->belongsTo(RefundPembelian::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stockPembelian()
    {
        return $this->belongsTo(StockPembelian::class);
    }

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }
}
