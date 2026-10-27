<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SetStockDraftItem extends Model
{
    protected $fillable = [
        'draft_id',
        'product_id',
        'qty',
        'scan_count',
        'version',
        'last_scanned_at',
        'updated_by',
    ];

    protected $casts = [
        'last_scanned_at' => 'datetime',
    ];

    public function draft()
    {
        return $this->belongsTo(SetStockDraft::class, 'draft_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}