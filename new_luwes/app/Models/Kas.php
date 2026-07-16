<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kas extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'outlet_id',
        'nominal',
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function penjualan()
    {
        return $this->hasMany(Penjualan::class);
    }
}
