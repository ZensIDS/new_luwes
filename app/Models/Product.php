<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Product extends Model
{
    use SoftDeletes;
    use LogsActivity;

    public const STATUS_PRODUK = [
        'free_produk' => 'Free produk',
        'tambahan_diskon' => 'Tambahan diskon',
        'free_tester' => 'Free tester',
        'listing' => 'Listing',
        'lunas' => 'Lunas',
        'belum_lunas' => 'Belum lunas',
        'sudah' => 'Sudah',
    ];

    protected $fillable = [
        'pic', //picture
        'code',
        'name',
        'category_id',
        'desc',
        'warna',
        'ukuran',
        // 'outlet_id', //unsued, currently just for experiment
        // 'supplier_id', //unsued, currently just for experiment
        'brand', // Add for Lenovo, Samsung, etc.
        'satuan',
        'min_stock',
        'lokasi',
        'status_produk',
        'status_produk_note',
        'model', // Add for specific model info
        'is_serialized', // Boolean: true for unique items, false for bulk => now always false
        'harga_beli',
        'harga_jual',
        'diskon',
        'berat',
        'satuan_besar',
        'konversi_qty',
    ];

    protected $casts = [
        'is_serialized' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'product_supplier');
    }

    // public function supplier()
    // {
    //     return $this->belongsTo(Supplier::class);
    // }

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

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function wishlist()
    {
        return $this->belongsToMany(User::class, 'user_wishlist')->withPivot('qty', 'name', 'customer_id');
    }

    public function penjualanItems()
    {
        return $this->hasMany(PenjualanItem::class);
    }

    // Get total available stock
    public function getTotalStockAttribute()
    {
        return $this->stocks()->sum('qty');
    }

    public function calculateHPP($newQty, $newPrice)
    {
        $currentQty = $this->total_stock - $newQty; // stock BEFORE this batch
        $currentValue = $currentQty * $this->harga_beli;
        $newValue = $newQty * $newPrice;
        $totalQty = $this->total_stock; // already includes newQty (stock already saved)

        return $totalQty > 0 ? ($currentValue + $newValue) / $totalQty : $newPrice;
    }

    public function updateStockValue()
    {
        $this->stock_value = $this->total_stock * $this->harga_beli;
        $this->save();
    }

    public function getTotalAvailableStockAttribute()
    {
        return $this->stocks()->sum('qty_available');
    }

    public function getTotalReservedStockAttribute()
    {
        return $this->stocks()->sum('qty_reserved');
    }

    public function ownerStocks()
    {
        return $this->hasMany(OwnerStock::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function refundPembelianItems()
    {
        return $this->hasMany(RefundPembelianItem::class);
    }

    public function isLowStock(): bool
    {
        return $this->total_available_stock <= $this->effective_min_stock;
    }

    /**
     * Returns min_stock raised by the active adjustment percentage, if any.
     * Falls back to bare min_stock when no active adjustment exists.
     */
    public function getEffectiveMinStockAttribute(): int
    {
        $adjustment = ProductMinimumAdjustment::where('product_id', $this->id)
            ->activeOn()
            ->orderByDesc('active_from')
            ->orderByDesc('id')
            ->first();

        if (! $adjustment) {
            return (int) $this->min_stock;
        }

        return (int) ceil($this->min_stock * (1 + $adjustment->adjustment_percentage / 100));
    }

    public function getKonversiStringAttribute(): string
    {
        if (! $this->satuan_besar || ! $this->konversi_qty) {
            return '-';
        }

        return "1 {$this->satuan_besar} = {$this->konversi_qty} {$this->satuan}";
    }

    public function getStatusProdukLabelAttribute(): string
    {
        return self::STATUS_PRODUK[$this->status_produk] ?? ucfirst(str_replace('_', ' ', (string) $this->status_produk));
    }

    public function konversiDisplay(int|float $qty): string
    {
        if (! $this->konversi_qty || ! $this->satuan_besar) {
            return '-';
        }
        $qty   = (int) $qty;
        $boxes = (int) floor($qty / $this->konversi_qty);
        $rem   = (int) fmod($qty, $this->konversi_qty);

        if ($rem === 0) {
            return "{$boxes} {$this->satuan_besar}";
        }
        if ($boxes > 0) {
            return "{$boxes} {$this->satuan_besar} {$rem} {$this->satuan}";
        }

        return "{$qty} {$this->satuan}";
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['*'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept(['created_at', 'updated_at'])
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn (string $eventName) => "Data Product has been {$eventName}")
            ->useLogName('Product');
    }
}
