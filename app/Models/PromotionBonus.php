<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionBonus extends Model
{
    protected $fillable = [
        'promotion_id',
        'name',
        'qty',
    ];

    protected $casts = [
        'qty' => 'integer',
    ];

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }
}
