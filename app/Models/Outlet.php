<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Outlet extends Model
{
    use SoftDeletes;

    /** Cache daftar ringkas outlet (id, name, slug) untuk price checker publik. */
    public const LIST_CACHE_KEY = 'outlets:list-id-name-slug';

    public const LIST_CACHE_TTL = 600;

    protected static function booted(): void
    {
        // Nama/hapus/pulihkan outlet langsung terlihat di price checker.
        $forget = static fn () => Cache::forget(self::LIST_CACHE_KEY);

        static::saved($forget);
        static::deleted($forget);
        static::restored($forget);
    }

    protected $fillable = [
        'logo',
        'name',
        'jenis_outlet',
        'alamat',
        'npwp',
        'slogan',
        'desc',
        'footer',
    ];

    public function penjualan()
    {
        return $this->hasMany(Penjualan::class);
    }

    public function pembelian()
    {
        return $this->hasMany(Pembelian::class);
    }
}