<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pengeluaran extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'tanggal',
        'biaya',
        'desc',
        'kas_id',
        'jumlah',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function kas()
    {
        return $this->belongsTo(Kas::class);
    }

    protected $casts = [
        'tanggal' => 'datetime',
    ];
}
