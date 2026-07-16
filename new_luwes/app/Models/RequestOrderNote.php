<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestOrderNote extends Model
{
    protected $fillable = [
        'request_order_id',
        'kategori',
        'qty',
        'nama_pj',
    ];

    public function requestOrder()
    {
        return $this->belongsTo(RequestOrder::class);
    }
}
