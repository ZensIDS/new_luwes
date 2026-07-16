<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PembelianTransaction extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'pembelian_id',
        'payment_date',
        'payment_method',
        'payment_reference',
        'payment_history', // for partial
        'status', //'paid', 'unpaid', 'partial'
        'amount',
        'bukti_transfer', //path storage
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'datetime',
        'payment_history' => 'array',
        'amount' => 'float',
    ];

    public function pembelian()
    {
        return $this->belongsTo(Pembelian::class);
    }
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'payment_date',
                'payment_method',
                'payment_reference',
                'payment_history', // for partial
                'status', //'paid', 'unpaid', 'partial'
                'amount',
                'bukti_transfer', //path storage
                'notes',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
