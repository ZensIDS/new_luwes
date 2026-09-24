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
        'harga_beli',
    ];

    protected $casts = [
        'expired_at' => 'date',
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
