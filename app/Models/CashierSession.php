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

    public function shifts()
    {
        return $this->hasMany(CashierShift::class, 'cashier_session_id')
            ->orderBy('started_at');
    }

    public function activeShifts()
    {
        return $this->hasMany(CashierShift::class, 'cashier_session_id')
            ->whereNull('ended_at')
            ->orderByDesc('started_at');
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
                    ->orWhere('payment_method_name', '')
                    ->orWhere('payment_method_name', 'like', '%tunai%')
                    ->orWhere('payment_method_name', 'like', '%cash%');
            });
    }

    public function drawerEntries()
    {
        return $this->hasMany(CashierDrawerEntry::class);
    }

    /**
     * Metode bayar dianggap tunai bila namanya kosong atau memuat "tunai" / "cash".
     * Aturan yang sama dipakai cashSales() dan retur penjualan.
     */
    public static function isCashPaymentName(?string $name): bool
    {
        $name = trim((string) $name);

        return $name === '' || preg_match('/tunai|cash/i', $name) === 1;
    }

    public static function emptyBreakdown(): array
    {
        return [
            'sales_count' => 0,
            'sales_total' => 0.0,
            'non_cash_total' => 0.0,
            'payments' => [],
            'voucher_count' => 0,
            'voucher_total' => 0.0,
        ];
    }

    /**
     * Rincian penjualan per metode bayar + voucher yang keluar untuk banyak sesi sekaligus
     * (2 query saja, tidak N+1). Hanya penjualan berstatus paid dan belum dihapus.
     *
     * @param  array<int>  $sessionIds
     * @return array<int, array>  dikunci oleh id sesi
     */
    public static function breakdownFor(array $sessionIds): array
    {
        $sessionIds = array_values(array_unique(array_map('intval', $sessionIds)));
        $result = [];
        foreach ($sessionIds as $id) {
            $result[$id] = self::emptyBreakdown();
        }
        if (! $sessionIds) {
            return $result;
        }

        $methodExpr = "COALESCE(NULLIF(TRIM(payment_method_name), ''), 'Tunai')";
        $payments = Penjualan::query()
            ->whereIn('cashier_session_id', $sessionIds)
            ->where('status', 'paid')
            ->selectRaw("cashier_session_id, {$methodExpr} as method, COUNT(*) as trx, SUM(COALESCE(grand_total, total, 0)) as total")
            ->groupByRaw("cashier_session_id, {$methodExpr}")
            ->get();

        foreach ($payments as $row) {
            $id = (int) $row->cashier_session_id;
            $isCash = self::isCashPaymentName($row->method);
            $total = (float) $row->total;

            $result[$id]['payments'][] = [
                'name' => (string) $row->method,
                'is_cash' => $isCash,
                'count' => (int) $row->trx,
                'total' => $total,
            ];
            $result[$id]['sales_count'] += (int) $row->trx;
            $result[$id]['sales_total'] += $total;
            if (! $isCash) {
                $result[$id]['non_cash_total'] += $total;
            }
        }

        // Tunai selalu di atas, sisanya urut nama.
        foreach ($result as $id => $breakdown) {
            usort($result[$id]['payments'], fn ($a, $b) => [! $a['is_cash'], $a['name']] <=> [! $b['is_cash'], $b['name']]);
        }

        $vouchers = DB::table('voucher_redemptions as vr')
            ->join('penjualans as p', 'p.id', '=', 'vr.penjualan_id')
            ->whereIn('p.cashier_session_id', $sessionIds)
            ->whereNull('p.deleted_at')
            ->where('p.status', 'paid')
            ->selectRaw('p.cashier_session_id, COUNT(vr.id) as qty, SUM(vr.amount) as total')
            ->groupBy('p.cashier_session_id')
            ->get();

        foreach ($vouchers as $row) {
            $id = (int) $row->cashier_session_id;
            $result[$id]['voucher_count'] = (int) $row->qty;
            $result[$id]['voucher_total'] = (float) $row->total;
        }

        return $result;
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
        $breakdown = self::breakdownFor([(int) $this->id])[(int) $this->id] ?? self::emptyBreakdown();

        return [
            'breakdown' => $breakdown,
            // Isi laci seharusnya = saldo awal (float) + kas penjualan. Hanya untuk informasi:
            // pencocokan uang fisik saat tutup memakai expected_cash (tanpa saldo awal).
            'drawer_total' => (float) $this->opening_cash + $expectedCash,
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