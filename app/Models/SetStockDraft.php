<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SetStockDraft extends Model
{
    protected $fillable = [
        'outlet_id',
        'active_outlet_id',
        'status',
        'save_token',
        'saved_by',
        'saved_at',
    ];

    protected $casts = [
        'saved_at' => 'datetime',
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function items()
    {
        return $this->hasMany(SetStockDraftItem::class, 'draft_id');
    }
}