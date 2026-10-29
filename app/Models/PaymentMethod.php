<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'bank_number',
        'desc',
        'is_fixed_amount',
        'charge_percent',
    ];

    protected $casts = [
        'is_fixed_amount' => 'boolean',
        'charge_percent' => 'float',
    ];
}