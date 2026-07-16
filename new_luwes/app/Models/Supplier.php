<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'kode_supplier',
        'alamat',
        'no_telp',
        'deadline_days',
        'deadline_interval_weeks',
        'deadline_reference_date',
    ];

    protected $casts = [
        'deadline_days'              => 'array',
        'deadline_reference_date'    => 'date',
        'deadline_interval_weeks'    => 'integer',
    ];

    public static function generateNextKode(): string
    {
        $last = static::withTrashed()
            ->where('kode_supplier', 'like', 'S%')
            ->orderByRaw('CAST(SUBSTRING(kode_supplier, 2) AS UNSIGNED) DESC')
            ->value('kode_supplier');

        $next = $last ? ((int) substr($last, 1)) + 1 : 1;

        return 'S' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_supplier');
    }

    public function pembelians()
    {
        return $this->hasMany(Pembelian::class);
    }

    /**
     * True if a pembelian already exists within the current ordering interval window.
     * Pass the pre-computed next deadline to avoid a redundant nextDeadlineDate() call.
     */
    public function hasPembelianInCurrentInterval(Carbon $nextDeadline): bool
    {
        $intervalStart = $nextDeadline->copy()->subWeeks($this->deadline_interval_weeks);

        if ($this->relationLoaded('pembelians')) {
            return $this->pembelians->contains(fn ($p) => $p->created_at >= $intervalStart);
        }

        return Pembelian::where('supplier_id', $this->id)
            ->where('created_at', '>=', $intervalStart)
            ->exists();
    }

    /**
     * Returns the next deadline date >= today, or null if no deadline configured.
     *
     * deadline_days: ISO weekday array e.g. [1] = Monday, [1,4] = Mon+Thu (1=Mon,7=Sun)
     * deadline_interval_weeks: 1/2/3
     * deadline_reference_date: anchor week (any past Monday works)
     */
    public function nextDeadlineDate(): ?Carbon
    {
        if (empty($this->deadline_days) || ! $this->deadline_interval_weeks) {
            return null;
        }

        $days     = array_map('intval', $this->deadline_days);
        $interval = (int) $this->deadline_interval_weeks;
        $ref      = Carbon::parse($this->deadline_reference_date ?? $this->created_at)->startOfWeek();
        $today    = Carbon::today();
        $limit    = $today->copy()->addDays($interval * 7 + 14);

        for ($d = $today->copy(); $d->lte($limit); $d->addDay()) {
            if (! in_array($d->dayOfWeekIso, $days)) {
                continue;
            }
            $weeksSinceRef = (int) abs($ref->diffInWeeks($d->copy()->startOfWeek()));
            if ($weeksSinceRef % $interval === 0) {
                return $d->copy();
            }
        }

        return null;
    }

    /** True if next deadline is within 3 days (but not past). */
    public function isDeadlineUrgent(): bool
    {
        $next = $this->nextDeadlineDate();
        if (! $next) {
            return false;
        }
        // nextDeadlineDate() always returns today or later, so daysUntil is always >= 0
        return Carbon::today()->diffInDays($next, false) <= 3;
    }
}
