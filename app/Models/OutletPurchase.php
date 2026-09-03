<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OutletPurchase extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'outlet_id',
        'supplier_id',
        'created_by',
        'purchase_date',
        'invoice_number',
        'subtotal',
        'paid_amount',
        'payment_method',
        'status',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'subtotal' => 'float',
        'paid_amount' => 'float',
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(OutletPurchaseItem::class);
    }
}
