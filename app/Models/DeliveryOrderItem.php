<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DeliveryOrderItem extends Model
{
    use LogsActivity;

    protected $fillable = [
        'delivery_order_id',
        'product_id',
        'stock_id',
        'qty',
        'qty_sent',
        'sku',
        'expired_at',
        'harga_beli',
    ];

    protected $casts = [
        'expired_at' => 'date',
    ];

    public function deliveryOrder()
    {
        return $this->belongsTo(DeliveryOrder::class);
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
            ->setDescriptionForEvent(fn (string $eventName) => "Data DeliveryOrderItem has been {$eventName}")
            ->useLogName('DeliveryOrderItem');
    }
}
