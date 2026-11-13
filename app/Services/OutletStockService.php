<?php

namespace App\Services;

use App\Models\OwnerStock;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Support\HargaBeliGuard;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OutletStockService
{
    /** Set Stock ulang dalam rentang ini = menambah stok, bukan menimpa. */
    public const SET_STOCK_ADD_WINDOW_HOURS = 72;

    public function receive(
        int $outletId,
        int $productId,
        int $qty,
        float $hpp,
        array $attributes,
        string $referenceType,
        int $referenceId,
        ?Authenticatable $user = null,
        ?string $hppSource = null
    ): OwnerStock {
        if ($qty < 1) {
            throw new RuntimeException('Qty penerimaan outlet harus lebih besar dari nol.');
        }

        return DB::transaction(function () use ($outletId, $productId, $qty, $hpp, $attributes, $referenceType, $referenceId, $user, $hppSource) {
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

            // keep_batch_hpp (Delivery Order, K2-a): batch yang sudah berisi stok mempertahankan HPP-nya,
            // tidak ditimpa dan tidak memicu "HPP berbeda". Batch baru / kosong memakai $hpp dari pemanggil.
            if ($ownerStock && ! empty($attributes['keep_batch_hpp']) && (int) $ownerStock->qty > 0) {
                $hpp = (float) $ownerStock->hpp;
            }

            if (! $ownerStock) {
                $ownerStock = $this->writeHpp($hppSource, fn () => OwnerStock::create([
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
                ]));
            }

            if ((int) $ownerStock->qty > 0 && (float) $ownerStock->hpp !== $hpp) {
                throw new RuntimeException("Batch {$batchNumber} sudah memiliki HPP berbeda.");
            }

            $ownerStock->increment('qty', $qty);
            $this->writeHpp($hppSource, fn () => $ownerStock->update([
                'stock_id' => $attributes['stock_id'] ?? $ownerStock->stock_id,
                'sku' => $attributes['sku'] ?? $ownerStock->sku,
                'expired_at' => $attributes['expired_at'] ?? $ownerStock->expired_at,
                'batch_number' => $batchNumber ?: $ownerStock->batch_number,
                'hpp' => $hpp,
                'source_type' => $attributes['source_type'] ?? $referenceType,
                'source_id' => $attributes['source_id'] ?? $referenceId,
            ]));

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

    /**
     * Jalankan penulisan hpp batch atas nama jalur yang sah (HargaBeliGuard).
     * $source null = pemanggil belum menandai jalurnya: tetap dicatat/ditolak oleh guard.
     *
     * @template T
     * @param  Closure():T  $callback
     * @return T
     */
    private function writeHpp(?string $source, Closure $callback)
    {
        return $source === null ? $callback() : HargaBeliGuard::allow($source, $callback);
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
                    // HPP batch MINUS turunan dari HPP terbaru, bukan input harga baru (HargaBeliGuard).
                    $row = HargaBeliGuard::allow('turunan', fn () => OwnerStock::create([
                        'owner_id' => $outletId,
                        'product_id' => $productId,
                        'qty' => 0,
                        'batch_number' => OwnerStock::DEFICIT_BATCH,
                        'hpp' => $hpp,
                        'created_by' => $user?->getAuthIdentifier(),
                    ]));
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
                HargaBeliGuard::allow('turunan', fn () => $row->update(['hpp' => $hpp]));
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

    /**
     * Set Stock: qty yang diinput MENIMPA stok produk di outlet (K5), KECUALI bila produk yang sama sudah
     * di-Set Stock < SET_STOCK_ADD_WINDOW_HOURS (72) jam lalu: maka qty DITAMBAH ke stok saat ini.
     *
     * - Belum ada batch sama sekali: dibuat batch baru (nomor batch otomatis) dengan HPP yang diberikan.
     * - Sudah ada batch: seluruh qty ditaruh di batch yang jadi "HPP terbaru" (sama dengan LatestHpp),
     *   batch lain milik produk itu di outlet yang sama dijadikan 0. HPP batch lama tidak diubah.
     * - Stok MINUS (penjualan yang terlanjur terjadi saat stok sistem kurang) TIDAK dihapus: qty Set Stock dipakai
     *   menutup minus itu lebih dulu. Contoh: stok -2, Set Stock 10 -> stok akhir 8 (bukan 10). Bila qty lebih kecil
     *   dari minusnya, minus yang tersisa tetap tercatat.
     * Setiap perubahan dicatat sebagai StockAdjustment + StockMovement. Dipanggil di dalam
     * transaksi pemanggil, jadi bisa digabung menjadi satu transaksi besar (semua atau tidak sama sekali).
     *
     * @return array{before:int,after:int,mode:string,settled:int}|null null bila qty sudah sama (tidak ada perubahan)
     */
    public function setInitialStock(
        int $outletId,
        int $productId,
        int $targetQty,
        float $hpp,
        int $draftId,
        ?Authenticatable $user = null
    ): ?array {
        if ($targetQty < 0) {
            throw new RuntimeException('Qty Set Stock tidak boleh negatif.');
        }

        return DB::transaction(function () use ($outletId, $productId, $targetQty, $hpp, $draftId, $user) {
            // Set Stock ulang < 72 jam untuk produk yang sama = TAMBAH (barang masuk bertahap di lapangan),
            // bukan menimpa. Di luar 72 jam tetap menimpa (koreksi stok seperti biasa).
            $additive = $this->hasRecentSetStock($outletId, $productId, $draftId);
            $inputQty = $targetQty;

            $batches = OwnerStock::where('owner_id', $outletId)
                ->where('product_id', $productId)
                ->notDeficit()
                ->lockForUpdate()
                ->get();
            $before = (int) $batches->sum('qty');

            if ($additive) {
                $targetQty = $before + $inputQty;
            }

            // Batch MINUS (penjualan melebihi stok sistem) dikunci di sini; qty Set Stock akan menutupnya
            // lebih dulu (lihat $settle di bawah), bukan menghapusnya.
            $deficit = OwnerStock::deficit()
                ->where('owner_id', $outletId)
                ->where('product_id', $productId)
                ->where('qty', '<', 0)
                ->lockForUpdate()
                ->first();

            if ($batches->isEmpty()) {
                // Tidak ada barang yang masuk: stok minus (bila ada) dibiarkan apa adanya.
                if ($targetQty < 1) {
                    return null;
                }

                $batchNumber = $this->generateSetStockBatchNumber($outletId, $productId);
                // HPP batch Set Stock turunan dari HPP terbaru, bukan input harga baru (HargaBeliGuard).
                $primary = HargaBeliGuard::allow('turunan', fn () => OwnerStock::create([
                    'owner_id' => $outletId,
                    'product_id' => $productId,
                    'qty' => 0,
                    'sku' => $batchNumber,
                    'batch_number' => $batchNumber,
                    'hpp' => $hpp,
                    'source_type' => 'set_stock',
                    'source_id' => $draftId,
                    'created_by' => $user?->getAuthIdentifier(),
                ]));
                $batches = collect([$primary]);
            } else {
                $primary = $this->pickPrimaryBatch($batches);
            }

            $date = today()->toDateString();

            // Penjualan yang sudah terjadi (stok minus) ditutup lebih dulu oleh qty Set Stock,
            // sisanya menjadi stok. Contoh: minus 2 + Set Stock 10 = stok 8.
            $settle = $deficit ? (int) min($targetQty, abs((int) $deficit->qty)) : 0;
            $finalQty = $targetQty - $settle;

            $notes = 'Set Stock toko (draft #' . $draftId . ')'
                . ($additive ? ' - ditambahkan +' . $inputQty . ' (set ulang < ' . self::SET_STOCK_ADD_WINDOW_HOURS . ' jam)' : '')
                . ($settle > 0 ? ' - ' . $settle . ' dipakai menutup stok minus' : '');
            $changed = false;

            foreach ($batches as $batch) {
                $target = $batch->is($primary) ? $finalQty : 0;
                if ($this->adjust($batch, (float) $target, $date, 'Set Stock', $user, $notes)) {
                    $changed = true;
                }
            }

            if ($settle >= 1) {
                $this->adjust(
                    $deficit,
                    (float) $deficit->qty + $settle,
                    $date,
                    'Set Stock',
                    $user,
                    'Set Stock toko (draft #' . $draftId . ') - stok minus ditutup ' . $settle . ' dari Set Stock'
                );
                $changed = true;
            }

            return $changed
                ? ['before' => $before, 'after' => $finalQty, 'mode' => $additive ? 'add' : 'replace', 'settled' => $settle]
                : null;
        });
    }

    /**
     * Apakah produk ini sudah pernah disimpan lewat Set Stock di toko yang sama
     * dalam SET_STOCK_ADD_WINDOW_HOURS jam terakhir (draft lain yang sudah selesai)?
     */
    public function hasRecentSetStock(int $outletId, int $productId, ?int $exceptDraftId = null): bool
    {
        return DB::table('set_stock_draft_items as i')
            ->join('set_stock_drafts as d', 'd.id', '=', 'i.draft_id')
            ->where('d.outlet_id', $outletId)
            ->where('d.status', 'done')
            ->where('i.product_id', $productId)
            ->when($exceptDraftId, fn ($q) => $q->where('d.id', '!=', $exceptDraftId))
            ->where('d.saved_at', '>=', now()->subHours(self::SET_STOCK_ADD_WINDOW_HOURS))
            ->exists();
    }

    /**
     * Versi massal hasRecentSetStock: dari daftar produk, yang mana sudah di-Set Stock dalam
     * SET_STOCK_ADD_WINDOW_HOURS jam terakhir (draft lain yang sudah selesai). Dipakai tampilan keranjang.
     *
     * @param  array<int,int>  $productIds
     * @return array<int,bool> [product_id => true]
     */
    public function recentSetStockProductIds(int $outletId, array $productIds, ?int $exceptDraftId = null): array
    {
        $found = [];
        foreach (array_chunk(array_values(array_unique(array_map('intval', $productIds))), 1000) as $chunk) {
            $ids = DB::table('set_stock_draft_items as i')
                ->join('set_stock_drafts as d', 'd.id', '=', 'i.draft_id')
                ->where('d.outlet_id', $outletId)
                ->where('d.status', 'done')
                ->whereIn('i.product_id', $chunk)
                ->when($exceptDraftId, fn ($q) => $q->where('d.id', '!=', $exceptDraftId))
                ->where('d.saved_at', '>=', now()->subHours(self::SET_STOCK_ADD_WINDOW_HOURS))
                ->distinct()
                ->pluck('i.product_id');
            foreach ($ids as $id) {
                $found[(int) $id] = true;
            }
        }

        return $found;
    }

    /** Batch tujuan Set Stock: batch tersedia terbaru, kalau tidak ada batch terbaru apa pun (sama dengan LatestHpp). */
    protected function pickPrimaryBatch($batches): OwnerStock
    {
        $sorted = $batches->sortByDesc(fn (OwnerStock $batch) => [
            $batch->created_at?->getTimestamp() ?? 0,
            (int) $batch->id,
        ])->values();

        $available = $sorted->first(fn (OwnerStock $batch) => (int) $batch->qty > 0
            && (! $batch->expired_at || $batch->expired_at->gte(today())));

        return $available ?? $sorted->first();
    }

    protected function generateSetStockBatchNumber(int $outletId, int $productId): string
    {
        $tag = trim((string) config('dbsync.site_tag', ''));
        $base = 'SET-' . now()->format('Ymd') . '-' . $productId . ($tag !== '' ? '-' . $tag : '');
        $candidate = $base;
        $suffix = 1;

        // withTrashed(): unique index ikut menghitung baris yang sudah di-soft-delete.
        while (OwnerStock::withTrashed()
            ->where('owner_id', $outletId)
            ->where('product_id', $productId)
            ->where('batch_number', $candidate)
            ->exists()) {
            $suffix++;
            $candidate = $base . '-' . $suffix;
        }

        return $candidate;
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