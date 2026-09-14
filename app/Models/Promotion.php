<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promotion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'type',
        'discount_type',
        'discount_value',
        'bundle_price',
        'max_qty',
        'quota_qty',
        'used_qty',
        'min_purchase',
        'outlet_id',
        'start_at',
        'end_at',
        'is_active',
        'stackable',
        'priority',
        'desc',
        'created_by',
    ];

    protected $casts = [
        'discount_value' => 'float',
        'bundle_price' => 'float',
        'min_purchase' => 'float',
        'max_qty' => 'integer',
        'quota_qty' => 'integer',
        'used_qty' => 'integer',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'stackable' => 'boolean',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'promotion_products')
            ->withPivot('required_qty')
            ->withTimestamps();
    }

    public function promotionProducts()
    {
        return $this->hasMany(PromotionProduct::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function outlets()
    {
        return $this->belongsToMany(Outlet::class, 'promotion_outlets');
    }

    public function bonuses()
    {
        return $this->hasMany(PromotionBonus::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function applications()
    {
        return $this->hasMany(PromotionApplication::class);
    }

    public function isActive(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->is_active
            && (! $this->start_at || $this->start_at <= $at)
            && (! $this->end_at || $this->end_at >= $at)
            && (! $this->quota_qty || $this->used_qty < $this->quota_qty);
    }

    public function scopeActiveFor(Builder $query, int $outletId, ?Carbon $at = null): Builder
    {
        $at ??= now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $scope) use ($outletId) {
                $scope->where(function (Builder $legacy) use ($outletId) {
                    $legacy->whereNull('outlet_id')->orWhere('outlet_id', $outletId);
                })->whereDoesntHave('outlets')
                    ->orWhereHas('outlets', fn (Builder $outlets) => $outlets->whereKey($outletId));
            })
            ->where(function (Builder $scope) use ($at) {
                $scope->whereNull('start_at')->orWhere('start_at', '<=', $at);
            })
            ->where(function (Builder $scope) use ($at) {
                $scope->whereNull('end_at')->orWhere('end_at', '>=', $at);
            })
            ->where(function (Builder $scope) {
                $scope->whereNull('quota_qty')->orWhereColumn('used_qty', '<', 'quota_qty');
            })
            ->orderBy('id');
    }
}
