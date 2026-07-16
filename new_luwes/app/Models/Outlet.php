<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Outlet extends Model
{
    use SoftDeletes;

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
