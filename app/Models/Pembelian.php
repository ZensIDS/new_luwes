<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pembelian extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'code_gr',
        'outlet_id',
        'supplier_id',
        'kas_id',
        'total',
        'is_published',
        'owner_approval_status',
        'owner_approved_by',
        'owner_approved_at',
        'owner_approval_note',
        'receipt_date',
        'receipt_pic',
        'receipt_status',
        'receipt_photo',
    ];

    protected $casts = [
        'receipt_date' => 'datetime',
        'owner_approved_at' => 'datetime',
    ];

    public const OWNER_APPROVAL_STATUSES = [
        'pending' => 'Menunggu ACC Owner',
        'approved' => 'Disetujui Owner',
        'rejected' => 'Ditolak Owner',
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function kas()
    {
        return $this->belongsTo(Kas::class);
    }

    // Market stocks (after published)
    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    // Warehouse stocks (before published)
    public function stockPembelians()
    {
        return $this->hasMany(StockPembelian::class);
    }

    public function pembelianProducts()
    {
        return $this->hasMany(PembelianProduct::class);
    }

    public function pembelianTransaction()
    {
        return $this->hasOne(PembelianTransaction::class);
    }

    public function ownerApprovedBy()
    {
        return $this->belongsTo(User::class, 'owner_approved_by');
    }

    public function getOwnerApprovalLabelAttribute(): string
    {
        return self::OWNER_APPROVAL_STATUSES[$this->owner_approval_status] ?? ucfirst((string) $this->owner_approval_status);
    }

    public function isOwnerApproved(): bool
    {
        return $this->owner_approval_status === 'approved';
    }

    public function canBeEditedBy(?User $user): bool
    {
        if (! $user || $this->is_published) {
            return false;
        }

        if (in_array($user->role, ['superadmin', 'owner'], true)) {
            return true;
        }

        if ($user->role === 'admin-gudang') {
            return $this->isOwnerApproved();
        }

        return false;
    }
}
