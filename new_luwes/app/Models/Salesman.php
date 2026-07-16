<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Salesman extends Model
{
    use SoftDeletes;

    protected $table = 'salesmans';
    protected $fillable = [
        'name',
        'alamat',
        'no_telp',
    ];

    public function penjualan()
    {
        return $this->hasMany(Penjualan::class);
    }
}
