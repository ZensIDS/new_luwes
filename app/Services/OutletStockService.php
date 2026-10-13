<?php

namespace App\Services;

use App\Models\OwnerStock;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OutletStockService
{
    public function receive(
        int $outletId,
        int $productId,
        int $qty,
        float $hpp,
        array $attributes,
        string $referenceType,
        int $referenceId,
        ?Authenticatable $user = null
    ): OwnerStock {
        if ($qty < 1) {
            throw new RuntimeException('Qty penerimaan outlet harus lebih besar dari nol.');
        }

        return DB::transaction(function () use ($outletId, $productId, $qty, $hpp, $attributes, $referenceType, $referenceId, $user) {
            $batchNumber = $attributes['batch_number'] ?? null;
            // withTrashed(): unique index ikut menghitung baris yang sudah di-soft-delete
            $ownerStock = OwnerStock::withTrashed()
                ->where('owner_id', $outletId)
                ->where('product_id', $productId)
                ->where('batch_number', $batchNumber)
                ->lockForUpdate()
                ->first();

            if ($ownerStock && $ownerStock->trashed()) {
                $ownerStock->restore();
            }

            if (! $ownerStock) {
                $ownerStock = OwnerStock::create([
                    'owner_id' => $outletId,
                    'product_id' => $productId,
                    'stock_id' => $attributes['stock_id'] ?? null,
                    'qty' => 0,
                    'sku' => $attributes['sku'] ?? null,
                    'expired_at' => $attributes['expired_at'] ?? null,
                    'batch_number' => $batchNumber,
                    'hpp' => $hpp,
                    'source_type' => $attributes['source_type'] ?? $referenceType,
                    'source_id' => $attributes['source_id'] ?? $referenceId,
                    'created_by' => $user?->getAuthIdentifier(),
                ]);
            }

            if ((int) $ownerStock->qty > 0 && (float) $ownerStock->hpp !== $hpp) {
                throw new RuntimeException("Batch {$batchNumber} sudah memiliki HPP berbeda.");
            }

            $ownerStock->increment('qty', $qty);
            $ownerStock->update([
                'stock_id' => $attributes['stock_id'] ?? $ownerStock->stock_id,
                'sku' => $attributes['sku'] ?? $ownerStock->sku,
                'expired_at' => $attributes['expired_at'] ?? $ownerStock->expired_at,
                'batch_number' => $batchNumber ?: $ownerStock->batch_number,
                'hpp' => $hpp,
                'source_type' => $attributes['source_type'] ?? $referenceType,
                'source_id' => $attributes['source_id'] ?? $referenceId,
            ]);

            StockMovement::create([
                'product_id' => $productId,
                'owner_id' => $outletId,
                'owner_stock_id' => $ownerStock->id,
                'user_id' => $user?->getAuthIdentifier(),
                'type' => 'in',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'qty_in' => $qty,
                'qty_out' => 0,
                'balance' => $ownerStock->qty,
                'notes' => $attributes['notes'] ?? 'Penerimaan stock toko',
            ]);

            // Barang yang masuk lebih dulu dipakai menutup stok minus (penjualan yang
            // melebihi stok sistem). Retur ke batch MINUS itu sendiri tidak diproses ulang.
            if ($batchNumber !== OwnerStock::DEFICIT_BATCH) {
                $this->settleDeficit($ownerStock, $qty, $referenceType, $referenceId, $user);
            }

            return $ownerStock->fresh();
        });
    }

    public function issue(
        OwnerStock $ownerStock,
        int $qty,
        string $referenceType,
        int $referenceId,
        ?Authenticatable $user = null,
        ?string $notes = null,
        bool $allowNegative = false
    ): OwnerStock {
        if ($qty < 1) {
            throw new RuntimeException('Qty pengeluaran outlet harus lebih besar dari nol.');
        }

        return DB::transaction(function () use ($ownerStock, $qty, $referenceType, $referenceId, $user, $notes, $allowNegative) {
            $locked = OwnerStock::whereKey($ownerStock->id)->lockForUpdate()->firstOrFail();
            // allowNegative hanya dipakai untuk batch MINUS (penjualan melebihi stok sistem).
            if (! ($allowNegative && $locked->isDeficit()) && (int) $locked->qty < $qty) {
                throw new RuntimeException('Saldo stock outlet tidak mencukupi.');
            }

            $locked->decrement('qty', $qty);
            StockMovement::create([
                'product_id' => $locked->product_id,
                'owner_id' => $locked->owner_id,
                'owner_stock_id' => $locked->id,
                'user_id' => $user?->getAuthIdentifier(),
                'type' => 'out',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'qty_in' => 0,
                'qty_out' => $qty,
                'balance' => $locked->qty,
                'notes' => $notes ?? 'Pengeluaran stock toko',
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Kembalikan qty ke batch yang sama (mis. pembatalan penjualan). Berbeda dengan
     * receive(): tidak memeriksa HPP/batch dan tidak menutup stok minus, karena barang
     * kembali ke batch asalnya. Bila item berasal dari batch MINUS, minusnya berkurang.
     */
    public function restore(
        OwnerStock $ownerStock,
        int $qty,
        string $referenceType,
        int $referenceId,
        ?Authenticatable $user = null,
        ?string $notes = null
    ): OwnerStock {
        if ($qty < 1) {
            throw new RuntimeException('Qty pengembalian stock outlet harus lebih besar dari nol.');
        }

        return DB::transaction(function () use ($ownerStock, $qty, $referenceType, $referenceId, $user, $notes) {
            // withTrashed(): batch bisa saja ter-soft-delete setelah penjualan terjadi.
            $locked = OwnerStock::withTrashed()->whereKey($ownerStock->id)->lockForUpdate()->firstOrFail();
            if ($locked->trashed()) {
                $locked->restore();
            }

            $locked->increment('qty', $qty);
            StockMovement::create([
                'product_id' => $locked->product_id,
                'owner_id' => $locked->owner_id,
                'owner_stock_id' => $locked->id,
                'user_id' => $user?->getAuthIdentifier(),
                'type' => 'in',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'qty_in' => $qty,
                'qty_out' => 0,
                'balance' => $locked->qty,
                'notes' => $notes ?? 'Pengembalian stock toko',
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Ambil (atau buat) batch MINUS untuk outlet + produk, dalam keadaan terkunci.
     * Dipanggil dari checkout saat qty penjualan melebihi stok sistem; pengurangan
     * qty-nya dilakukan oleh issue(..., allowNegative: true).
     */
    public function deficitStock(int $outletId, int $productId, float $hpp, ?Authenticatable $user = null): OwnerStock
    {
        return DB::transaction(function () use ($outletId, $productId, $hpp, $user) {
            $find = fn () => OwnerStock::withTrashed()
                ->where('owner_id', $outletId)
                ->where('product_id', $productId)
                ->where('batch_number', OwnerStock::DEFICIT_BATCH)
                ->lockForUpdate()
                ->first();

            $row = $find();

            if (! $row) {
                try {
                    $row = OwnerStock::create([
                        'owner_id' => $outletId,
                        'product_id' => $productId,
                        'qty' => 0,
                        'batch_number' => OwnerStock::DEFICIT_BATCH,
                        'hpp' => $hpp,
                        'created_by' => $user?->getAuthIdentifier(),
                    ]);
                } catch (QueryException $e) {
                    // Request lain membuat baris yang sama bersamaan: pakai baris itu.
                    $row = $find();
                    if (! $row) {
                        throw $e;
                    }
                }
            }

            if ($row->trashed()) {
                $row->restore();
            }

            // Episode minus baru (saldo sudah nol): perbarui HPP perkiraan ke HPP terbaru.
            if ((int) $row->qty >= 0 && (float) $row->hpp !== $hpp) {
                $row->update(['hpp' => $hpp]);
            }

            return $row;
        });
    }

    /**
     * Tutup stok minus dengan barang yang baru masuk: qty masuk dikurangkan dari batch
     * penerimaan dan ditambahkan ke batch MINUS, masing-masing dengan movement
     * 'adjustment' agar Kartu Stok tetap seimbang dan bisa ditelusuri.
     */
    protected function settleDeficit(
        OwnerStock $received,
        int $incomingQty,
        string $referenceType,
        int $referenceId,
        ?Authenticatable $user = null
    ): void {
        // Barang yang sudah expired tidak bisa dianggap menutup penjualan.
        if ($received->expired_at && $received->expired_at->lt(today())) {
            return;
        }

        $deficit = OwnerStock::deficit()
            ->where('owner_id', $received->owner_id)
            ->where('product_id', $received->product_id)
            ->lockForUpdate()
            ->first();

        if (! $deficit || (int) $deficit->qty >= 0) {
            return;
        }

        $locked = OwnerStock::whereKey($received->id)->lockForUpdate()->firstOrFail();
        $settle = min($incomingQty, abs((int) $deficit->qty), (int) $locked->qty);
        if ($settle < 1) {
            return;
        }

        $locked->decrement('qty', $settle);
        $deficit->increment('qty', $settle);

        $batchLabel = $locked->batch_number ?: ('#'.$locked->id);

        StockMovement::create([
            'product_id' => $locked->product_id,
            'owner_id' => $locked->owner_id,
            'owner_stock_id' => $locked->id,
            'user_id' => $user?->getAuthIdentifier(),
            'type' => 'adjustment',
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'qty_in' => 0,
            'qty_out' => $settle,
            'balance' => $locked->qty,
            'notes' => "Pelunasan stok minus: {$settle} dipakai menutup penjualan melebihi stok",
        ]);

        StockMovement::create([
            'product_id' => $deficit->product_id,
            'owner_id' => $deficit->owner_id,
            'owner_stock_id' => $deficit->id,
            'user_id' => $user?->getAuthIdentifier(),
            'type' => 'adjustment',
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'qty_in' => $settle,
            'qty_out' => 0,
            'balance' => $deficit->qty,
            'notes' => "Pelunasan stok minus dari penerimaan batch {$batchLabel}",
        ]);
    }

    public function adjust(
        OwnerStock $ownerStock,
        float $physicalQty,
        string $adjustmentDate,
        ?string $reason = null,
        ?Authenticatable $user = null,
        ?string $movementNotes = null
    ): ?StockAdjustment {
        return DB::transaction(function () use ($ownerStock, $physicalQty, $adjustmentDate, $reason, $user, $movementNotes) {
            $locked = OwnerStock::whereKey($ownerStock->id)->lockForUpdate()->firstOrFail();
            $systemQty = (float) $locked->qty;
            $difference = $physicalQty - $systemQty;

            if ($difference == 0.0) {
                return null;
            }

            $adjustment = StockAdjustment::create([
                'adjustment_date' => $adjustmentDate,
                'product_id' => $locked->product_id,
                'stock_id' => $locked->stock_id,
                'owner_id' => $locked->owner_id,
                'owner_stock_id' => $locked->id,
                'sku' => $locked->sku,
                'quantity' => $difference,
                'system_qty' => $systemQty,
                'physical_qty' => $physicalQty,
                'reason' => $reason,
                'status' => 'Selesai',
                'keterangan' => $reason,
            ]);

            $locked->update(['qty' => $physicalQty]);
            StockMovement::create([
                'product_id' => $locked->product_id,
                'owner_id' => $locked->owner_id,
                'owner_stock_id' => $locked->id,
                'user_id' => $user?->getAuthIdentifier(),
                'type' => 'adjustment',
                'reference_type' => StockAdjustment::class,
                'reference_id' => $adjustment->id,
                'qty_in' => $difference > 0 ? $difference : 0,
                'qty_out' => $difference < 0 ? abs($difference) : 0,
                'balance' => $physicalQty,
                // movementNotes dipakai alur selain opname (mis. tutup stok minus) agar Kartu Stok jelas.
                'notes' => $movementNotes ?? ('Stock opname toko - ' . ($reason ?: 'Adjustment')),
            ]);

            return $adjustment;
        });
    }
}