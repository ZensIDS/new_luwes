<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashierShift extends Model
{
    protected $fillable = [
        'cashier_session_id',
        'created_by',
        'name',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(CashierSession::class, 'cashier_session_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sales()
    {
        return $this->hasMany(Penjualan::class, 'cashier_shift_id');
    }
}
