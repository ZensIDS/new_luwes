<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class OwnerStock extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'owner_id',
        'product_id',
        'stock_id',
        'qty',
        'sku',
        'expired_at',
        'batch_number',
        'hpp',
        'source_type',
        'source_id',
        'created_by',
    ];

    protected $casts = [
        'expired_at' => 'date',
        'hpp' => 'float',
    ];

    public function owner()
    {
        return $this->belongsTo(Outlet::class, 'owner_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getHargaBeliAttribute()
    {
        return $this->attributes['hpp'] ?? $this->attributes['harga_beli'] ?? null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['*'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept(['created_at', 'updated_at'])
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn (string $eventName) => "Data OwnerStock has been {$eventName}")
            ->useLogName('OwnerStock');
    }
}
