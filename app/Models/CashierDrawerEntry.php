<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CashierDrawerEntry extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'cashier_session_id',
        'outlet_id',
        'cashier_id',
        'type',
        'amount',
        'expected_cash',
        'difference',
        'note',
        'recorded_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'expected_cash' => 'float',
        'difference' => 'float',
        'recorded_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(CashierSession::class, 'cashier_session_id');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['cashier_session_id', 'outlet_id', 'cashier_id', 'type', 'amount', 'expected_cash', 'difference', 'note', 'recorded_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Pergerakan cash drawer telah {$eventName}")
            ->useLogName('Cashier');
    }
}
