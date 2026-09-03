<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'product_id',
        'owner_id',
        'owner_stock_id',
        'user_id',
        'type',
        'reference_type',
        'reference_id',
        'qty_in',
        'qty_out',
        'balance',
        'notes',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function owner()
    {
        return $this->belongsTo(Outlet::class, 'owner_id');
    }

    public function ownerStock()
    {
        return $this->belongsTo(OwnerStock::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
