<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductMinimumAdjustment extends Model
{
    protected $fillable = [
        'product_id',
        'adjustment_percentage',
        'active_from',
        'active_until',
        'created_by',
    ];

    protected $casts = [
        'active_from'  => 'date',
        'active_until' => 'date',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /** Scope: adjustments active on a given date (defaults to today). */
    public function scopeActiveOn($query, $date = null)
    {
        $date = $date ?? now()->toDateString();

        return $query
            ->where('active_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('active_until')->orWhere('active_until', '>=', $date);
            });
    }
}
