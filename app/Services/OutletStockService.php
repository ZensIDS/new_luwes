<?php

namespace App\Services;

use App\Models\OwnerStock;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use Illuminate\Contracts\Auth\Authenticatable;
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
            $ownerStock = OwnerStock::where('owner_id', $outletId)
                ->where('product_id', $productId)
                ->where('batch_number', $batchNumber)
                ->lockForUpdate()
                ->first();

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

            return $ownerStock->fresh();
        });
    }

    public function issue(
        OwnerStock $ownerStock,
        int $qty,
        string $referenceType,
        int $referenceId,
        ?Authenticatable $user = null,
        ?string $notes = null
    ): OwnerStock {
        if ($qty < 1) {
            throw new RuntimeException('Qty pengeluaran outlet harus lebih besar dari nol.');
        }

        return DB::transaction(function () use ($ownerStock, $qty, $referenceType, $referenceId, $user, $notes) {
            $locked = OwnerStock::whereKey($ownerStock->id)->lockForUpdate()->firstOrFail();
            if ((int) $locked->qty < $qty) {
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

    public function adjust(
        OwnerStock $ownerStock,
        float $physicalQty,
        string $adjustmentDate,
        ?string $reason = null,
        ?Authenticatable $user = null
    ): ?StockAdjustment {
        return DB::transaction(function () use ($ownerStock, $physicalQty, $adjustmentDate, $reason, $user) {
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
                'notes' => 'Stock opname toko - ' . ($reason ?: 'Adjustment'),
            ]);

            return $adjustment;
        });
    }
}
