<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'type', //nominal, percentage
        'jenis', //satuan, keseluruhan
        'limit', //usage limit
        'value',
        'min_purchase',
        'max_discount_amount',
        'outlet_id',
        'start_at',
        'end_at',
        'desc',
        'product_id',
        'kasir_id',
    ];

    protected $casts = [
        'value' => 'float',
        'min_purchase' => 'float',
        'max_discount_amount' => 'float',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function outlets()
    {
        return $this->belongsToMany(Outlet::class, 'voucher_outlets');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'voucher_products');
    }

    public function kasir()
    {
        return $this->belongsTo(User::class, 'kasir_id'); //user role kasir
    }

    public function redemptions()
    {
        return $this->hasMany(VoucherRedemption::class);
    }

    public function isActive(?\Carbon\Carbon $at = null): bool
    {
        $at ??= now();

        $limitAvailable = $this->limit === null
            || ((int) $this->limit > 0 && $this->redemptions()->count() < (int) $this->limit);

        return (! $this->start_at || $this->start_at <= $at)
            && (! $this->end_at || $this->end_at >= $at)
            && $limitAvailable;
    }

    public function appliesToOutlet(?int $outletId): bool
    {
        if (! $outletId) {
            return true;
        }

        if ($this->outlet_id && (int) $this->outlet_id === $outletId) {
            return true;
        }

        return $this->outlet_id === null
            && (! $this->relationLoaded('outlets') || $this->outlets->isEmpty() || $this->outlets->contains('id', $outletId));
    }

    public function appliesToProduct(?int $productId): bool
    {
        if (! $productId) {
            return true;
        }

        if ($this->product_id && (int) $this->product_id === $productId) {
            return true;
        }

        return $this->product_id === null
            && (! $this->relationLoaded('products') || $this->products->isEmpty() || $this->products->contains('id', $productId));
    }
}
