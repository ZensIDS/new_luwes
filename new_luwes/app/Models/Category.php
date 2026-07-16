<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        // 'type', //unsued, currently just for experiment
        // 'outlet_id', //unsued, currently just for experiment
    ];

    // public function outlet()
    // {
    //     return $this->belongsTo(Outlet::class);
    // }
}
