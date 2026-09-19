<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CashierSession extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'outlet_id',
        'cashier_id',
        'status',
        'opening_cash',
        'closing_cash',
        'expected_cash',
        'cash_sales',
        'cash_in',
        'cash_out',
        'cash_removed',
        'carry_over_cash',
        'discrepancy',
        'opening_note',
        'closing_note',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'opening_cash' => 'float',
        'closing_cash' => 'float',
        'expected_cash' => 'float',
        'cash_sales' => 'float',
        'cash_in' => 'float',
        'cash_out' => 'float',
        'cash_removed' => 'float',
        'carry_over_cash' => 'float',
        'discrepancy' => 'float',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function sales()
    {
        return $this->hasMany(Penjualan::class, 'cashier_session_id');
    }

    public function cashSales()
    {
        return $this->sales()
            ->where('status', 'paid')
            ->where(function ($query) {
                $query->whereNull('payment_method_name')
                    ->orWhere('payment_method_name', 'like', '%tunai%')
                    ->orWhere('payment_method_name', 'like', '%cash%');
            });
    }

    public function drawerEntries()
    {
        return $this->hasMany(CashierDrawerEntry::class);
    }

    public function summary(): array
    {
        $cashSales = (float) $this->cashSales()
            ->sum(DB::raw('COALESCE(grand_total, total, 0)'));
        $cashIn = (float) $this->drawerEntries()->where('type', 'cash_in')->sum('amount');
        $cashOut = (float) $this->drawerEntries()
            ->whereIn('type', ['bon', 'cash_out'])
            ->sum('amount');
        // opening_cash is the float carried from the previous day. It is kept
        // separately from today's cash movement, matching the outlet workflow:
        // sales - BON = cash available to settle at the end of the day.
        $expectedCash = $cashSales + $cashIn - $cashOut;

        return [
            'opening_cash' => (float) $this->opening_cash,
            'cash_sales' => $cashSales,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'net_cash_income' => $cashSales + $cashIn - $cashOut,
            'cash_settled' => $cashOut + (float) ($this->cash_removed ?? 0),
            'expected_cash' => $expectedCash,
            'closing_cash' => $this->closing_cash,
            'carry_over_cash' => $this->carry_over_cash,
            'cash_removed' => $this->cash_removed,
            'discrepancy' => $this->discrepancy,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'outlet_id', 'cashier_id', 'status', 'opening_cash', 'closing_cash',
                'expected_cash', 'cash_sales', 'cash_in', 'cash_out', 'cash_removed',
                'carry_over_cash', 'discrepancy', 'opening_note', 'closing_note',
                'opened_at', 'closed_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Sesi kasir telah {$eventName}")
            ->useLogName('Cashier');
    }
}
