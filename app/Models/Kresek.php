<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kresek extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'price',
        'is_active',
    ];

    protected $casts = [
        'price' => 'float',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}