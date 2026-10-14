<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class OtherIncome extends Model
{
    use LogsActivity;
    use SoftDeletes;

    /** Role yang hanya boleh melihat/mengisi data outlet sendiri (K11). */
    public const OUTLET_SCOPED_ROLES = ['staff-outlet', 'kasir'];

    protected $fillable = [
        'outlet_id',
        'other_income_category_id',
        'tanggal',
        'jumlah',
        'keterangan',
        'source_type',
        'source_id',
        'product_name_snapshot',
        'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'jumlah' => 'float',
    ];

    /** Outlet kosong = pemasukan umum. */
    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    /** withTrashed: income lama tetap menampilkan nama kategorinya walau kategori sudah dihapus. */
    public function category()
    {
        return $this->belongsTo(OtherIncomeCategory::class, 'other_income_category_id')->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Sumber income otomatis (produk). Null untuk input manual. */
    public function source()
    {
        return $this->morphTo();
    }

    /** Income hasil input manual. */
    public function scopeManual(Builder $query): Builder
    {
        return $query->whereNull('source_type');
    }

    /** Income otomatis (lahir dari sistem, mis. Listing). */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->whereNotNull('source_type');
    }

    /**
     * Batasi data sesuai hak akses (K11): staff-outlet dan kasir hanya melihat income outlet sendiri;
     * income umum (outlet kosong, termasuk Listing) tidak tampil bagi mereka. Role lain melihat semuanya.
     * Bila role terbatas tapi user belum punya outlet, hasilnya kosong (bukan income umum).
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user && in_array($user->role, self::OUTLET_SCOPED_ROLES, true)) {
            return $user->outlet_id
                ? $query->where('outlet_id', $user->outlet_id)
                : $query->whereRaw('0 = 1');
        }

        return $query;
    }

    /** True bila income lahir otomatis dari sistem (tidak boleh diedit/dihapus tangan). */
    public function getIsAutoAttribute(): bool
    {
        return $this->source_type !== null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'outlet_id',
                'other_income_category_id',
                'tanggal',
                'jumlah',
                'keterangan',
                'source_type',
                'source_id',
                'product_name_snapshot',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Other Income telah {$eventName}")
            ->useLogName('OtherIncome');
    }
}