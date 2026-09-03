<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OutletPrice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'outlet_id',
        'product_id',
        'disc_brand_type',
        'disc_brand_value',
        'margin_type',
        'margin_value',
        'disc_toko_type',
        'disc_toko_value',
        'effective_from',
        'effective_until',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'disc_brand_value' => 'float',
        'margin_value' => 'float',
        'disc_toko_value' => 'float',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'is_active' => 'boolean',
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeCurrentlyActive(Builder $query, $at = null): Builder
    {
        $date = ($at ?: now())->toDateString();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $query) use ($date) {
                $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date);
            })
            ->where(function (Builder $query) use ($date) {
                $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date);
            });
    }
}
