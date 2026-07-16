<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StockPembelian extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'pembelian_id',
        'product_id',
        'sku',
        'subtotal',
        'harga_beli',
        'qty',
        'expired_at',
        'serial_number', // For individual items like laptops
        'imei', // For phones
        'condition', // new, used, refurbished
        'status', // available, sent_to_outlet, reserved
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function pembelian()
    {
        return $this->belongsTo(Pembelian::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('qty', '>', 0)->where('status', 'available');
    }

    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['*'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept(['created_at', 'updated_at'])
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn (string $eventName) => "Data StockPembelian has been {$eventName}")
            ->useLogName('StockPembelian');
    }
}
