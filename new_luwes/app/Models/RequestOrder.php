<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'owner_id',
        'requested_by',
        'verified_by',
        'request_date',
        'verified_date',
        'status',
        'notes',
        'verification_notes',
    ];

    protected $casts = [
        'request_date' => 'date',
        'verified_date' => 'date',
    ];

    public function owner()
    {
        return $this->belongsTo(Outlet::class, 'owner_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function items()
    {
        return $this->hasMany(RequestOrderItem::class);
    }

    public function pickingList()
    {
        return $this->hasOne(PickingList::class);
    }

    public function deliveryOrder()
    {
        return $this->hasOne(DeliveryOrder::class);
    }

    public function additionalNotes()
    {
        return $this->hasMany(RequestOrderNote::class);
    }
}
