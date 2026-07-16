<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PembelianProduct extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'pembelian_id',
        'product_id',
        'harga_beli',
        'qty',
        'qty_diterima',
        'subtotal',
        'expired_at',
        'serial_numbers' // JSON array for serialized items
    ];

    protected $casts = [
        'expired_at' => 'datetime',
        'serial_numbers' => 'array',
    ];

    public function pembelian()
    {
        return $this->belongsTo(Pembelian::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['*'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept(['created_at', 'updated_at'])
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn (string $eventName) => "Data PembelianProduct has been {$eventName}")
            ->useLogName('PembelianProduct');
    }
}
