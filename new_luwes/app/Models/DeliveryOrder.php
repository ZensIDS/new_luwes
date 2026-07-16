<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'request_order_id',
        'picking_list_id',
        'owner_id',
        'prepared_by',
        'received_by',
        'delivery_date',
        'received_date',
        'status',
        'notes',
        'photo_path',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'received_date' => 'date',
    ];

    public function requestOrder()
    {
        return $this->belongsTo(RequestOrder::class);
    }

    public function pickingList()
    {
        return $this->belongsTo(PickingList::class);
    }

    public function owner()
    {
        return $this->belongsTo(Outlet::class, 'owner_id');
    }

    public function preparedBy()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items()
    {
        return $this->hasMany(DeliveryOrderItem::class);
    }
}
