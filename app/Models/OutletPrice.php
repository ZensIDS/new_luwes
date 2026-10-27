<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\PriceFolderIndex;

class OutletPrice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'outlet_id',
        'product_id',
        'disc_brand_type',
        'disc_brand_value',
        'pajak_type',
        'pajak_value',
        'disc_tambahan_type',
        'disc_tambahan_value',
        'disc_tambahan_2_type',
        'disc_tambahan_2_value',
        'disc_tambahan_3_type',
        'disc_tambahan_3_value',
        'disc_tambahan_4_type',
        'disc_tambahan_4_value',
        'margin_type',
        'margin_value',
        'disc_toko_type',
        'disc_toko_value',
        'outlet_adjustment_type',
        'outlet_adjustment_value',
        'effective_from',
        'effective_until',
        'is_active',
        'note',
        'created_by',
    ];

    protected $casts = [
        'disc_brand_value' => 'float',
        'pajak_value' => 'float',
        'disc_tambahan_value' => 'float',
        'disc_tambahan_2_value' => 'float',
        'disc_tambahan_3_value' => 'float',
        'disc_tambahan_4_value' => 'float',
        'margin_value' => 'float',
        'disc_toko_value' => 'float',
        'outlet_adjustment_value' => 'float',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Cache folder index Master Harga Jual dibersihkan saat aturan dibuat, dihapus, dipulihkan,
     * atau pindah outlet/produk. Simpan biasa (mis. tombol Kembali yang hanya memperbarui updated_at) tidak membersihkan.
     */
    protected static function booted(): void
    {
        static::saved(function (OutletPrice $price) {
            if (! $price->wasRecentlyCreated && ! $price->wasChanged(['outlet_id', 'product_id'])) {
                return;
            }

            $folders = app(PriceFolderIndex::class);
            $folders->forget((int) $price->outlet_id);

            $oldOutletId = (int) $price->getOriginal('outlet_id');
            if ($oldOutletId && $oldOutletId !== (int) $price->outlet_id) {
                $folders->forget($oldOutletId);
            }
        });

        static::deleted(fn (OutletPrice $price) => app(PriceFolderIndex::class)->forget((int) $price->outlet_id));
        static::restored(fn (OutletPrice $price) => app(PriceFolderIndex::class)->forget((int) $price->outlet_id));
    }

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
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', $date);
            })
            ->where(function (Builder $query) use ($date) {
                $query->whereNull('effective_until')->orWhere('effective_until', '>=', $date);
            });
    }
}