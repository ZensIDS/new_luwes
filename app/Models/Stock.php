<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Stock extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'pembelian_id',
        'product_id',
        'sku',
        'subtotal',
        'harga_beli',
        'qty',
        'qty_reserved',
        'qty_available',
        'expired_at',
        'location',
        'batch_number',
        'serial_number', // For individual items like laptops
        'imei', // For phones
        'condition', // new, used, refurbished
        'status',
    ];

    public function scopeAvailable($query)
    {
        return $query->where('qty_available', '>', 0)
            ->where('status', 'available');
    }

    public function scopeReservable($query)
    {
        return $query->where('status', 'available');
    }

    public function reserve($qty)
    {
        if ($this->qty_available < $qty) {
            throw new \Exception('Insufficient available stock');
        }
        $this->qty_reserved += $qty;
        $this->save();
    }

    public function unreserve($qty)
    {
        $this->qty_reserved -= $qty;
        if ($this->qty_reserved < 0) {
            $this->qty_reserved = 0;
        }
        $this->save();
    }

    public function allocate($qty)
    {
        if ($this->qty < $qty) {
            // Mengambil nama produk dari relasi (sesuaikan 'product' dengan nama relasi di model Anda)
            $productName = $this->product ? $this->product->name : 'Produk Tidak Diketahui';

            throw new \Exception("Stok untuk produk [{$productName}] tidak mencukupi. Stok di sistem: {$this->qty}, Kebutuhan transaksi: {$qty}");
        }
        $this->qty -= $qty;
        $this->qty_reserved = max(0, $this->qty_reserved - $qty);
        $this->save();
    }

    public function pembelian()
    {
        return $this->belongsTo(Pembelian::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function ownerStock()
    {
        return $this->hasOne(OwnerStock::class);
    }

    public function adjustment()
    {
        return $this->hasOne(StockAdjustment::class);
    }

    // public function scopeAvailable($query)
    // {
    //     return $query->where('qty', '>', 0);
    // }

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['*'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept(['created_at', 'updated_at'])
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn (string $eventName) => "Data Stock has been {$eventName}")
            ->useLogName('Stock');
    }
}
