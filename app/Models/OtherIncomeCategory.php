<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class OtherIncomeCategory extends Model
{
    use LogsActivity;
    use SoftDeletes;

    public const LISTING_SLUG = 'listing-product';
    public const SEWA_SLUG = 'sewa';

    protected $fillable = [
        'name',
        'slug',
        'is_system',
        'nominal',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'nominal' => 'float',
    ];

    public function incomes()
    {
        return $this->hasMany(OtherIncome::class);
    }

    /** Kategori buatan user (boleh diubah/dihapus). */
    public function scopeManual(Builder $query): Builder
    {
        return $query->where('is_system', false);
    }

    /** Kategori sistem (terkunci). */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    /** Kategori sistem "Listing Product". Null bila belum ada (migrasi belum dijalankan). */
    public static function listing(): ?self
    {
        return static::query()
            ->system()
            ->where('slug', self::LISTING_SLUG)
            ->first();
    }

    /** Kategori Sewa: memunculkan isian periode, supplier, dan produk di form. */
    public function isSewa(): bool
    {
        return ! $this->is_system && $this->slug === self::SEWA_SLUG;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'is_system', 'nominal'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Kategori Pendapatan Lain telah {$eventName}")
            ->useLogName('OtherIncome');
    }
}