<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OutletPurchase extends Model
{
    use SoftDeletes;

    /** Sudah disimpan, barang belum diterima (stok toko belum bertambah, masih bisa diedit). */
    public const STATUS_PENDING = 'pending';

    /** Penerimaan barang selesai (completed): stok toko sudah bertambah, tidak bisa diedit. */
    public const STATUS_RECEIVED = 'received';

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
        'received_by',
        'received_at',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'subtotal' => 'float',
        'paid_amount' => 'float',
        'received_at' => 'datetime',
    ];

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

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

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items()
    {
        return $this->hasMany(OutletPurchaseItem::class);
    }
}