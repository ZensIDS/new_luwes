<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PickingListItem extends Model
{
    use LogsActivity;

    protected $fillable = [
        'picking_list_id',
        'product_id',
        'stock_id',
        'qty_to_pick',
        'qty_picked',
        'location',
        'sku',
        'is_picked',
    ];

    protected $casts = [
        'is_picked' => 'boolean',
    ];

    public function pickingList()
    {
        return $this->belongsTo(PickingList::class);
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
            ->setDescriptionForEvent(fn (string $eventName) => "Data PickingListItem has been {$eventName}")
            ->useLogName('PickingListItem');
    }
}
