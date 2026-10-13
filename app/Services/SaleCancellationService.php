<?php

namespace App\Services;

use App\Models\OwnerStock;
use App\Models\Penjualan;
use App\Models\VoucherRedemption;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Hapus penjualan kasir = batalkan penjualan: stok kembali ke batch asal (tercatat di
 * Kartu Stok), voucher bisa dipakai lagi, transaksi dan item ikut di-soft-delete.
 *
 * Penjualan marketplace (outlet_id null) tidak memakai owner_stocks, jadi hanya
 * di-soft-delete seperti perilaku lama.
 */
class SaleCancellationService
{
    public function __construct(private readonly OutletStockService $stockService) {}

    /**
     * Alasan penjualan tidak boleh dihapus oleh user ini, atau null bila boleh.
     * Dipakai UI (tombol nonaktif) dan cancel() (guard sesungguhnya).
     */
    public function blockReason(Penjualan $penjualan, Authenticatable $user): ?string
    {
        if ($user->role === 'staff-outlet') {
            return 'Staff outlet tidak dapat menghapus penjualan.';
        }

        if ($penjualan->outlet_id === null) {
            return null; // marketplace: perilaku lama
        }

        $hasRefund = isset($penjualan->refund_penjualans_count)
            ? (int) $penjualan->refund_penjualans_count > 0
            : $penjualan->refundPenjualans()->exists();
        if ($hasRefund) {
            return 'Penjualan ini sudah memiliki retur, tidak bisa dihapus.';
        }

        // Kas sesi yang sudah ditutup sudah direkonsiliasi: hanya owner / superadmin.
        $session = $penjualan->cashierSession;
        if ($session && $session->status === 'closed' && ! in_array($user->role, ['owner', 'superadmin'], true)) {
            return 'Sesi kasir sudah ditutup. Hanya owner / superadmin yang dapat menghapus penjualan ini.';
        }

        return null;
    }

    /**
     * @return array{items:int, qty:int, skipped:int}
     */
    public function cancel(Penjualan $penjualan, Authenticatable $user, ?string $reason): array
    {
        // 3 percobaan: deadlock MySQL diulang otomatis (semua langkah ada di dalam transaksi).
        return DB::transaction(function () use ($penjualan, $user, $reason) {
            // Kunci penjualan: hapus ganda / retur bersamaan (retur juga mengunci baris ini) diserialkan.
            $locked = Penjualan::withTrashed()->whereKey($penjualan->id)->lockForUpdate()->firstOrFail();
            if ($locked->trashed()) {
                throw new RuntimeException('Penjualan ini sudah dihapus sebelumnya.');
            }

            $locked->load('cashierSession');
            if ($block = $this->blockReason($locked, $user)) {
                throw new RuntimeException($block);
            }

            $summary = ['items' => 0, 'qty' => 0, 'skipped' => 0];

            if ($locked->outlet_id !== null) {
                $items = $locked->items()
                    ->with(['product' => fn ($query) => $query->withTrashed()->select('id', 'name')])
                    ->get();

                foreach ($items as $item) {
                    $qty = (int) $item->qty;
                    if ($qty < 1) {
                        continue;
                    }
                    // Data lama tanpa batch tidak bisa dikembalikan otomatis.
                    if (! $item->owner_stock_id) {
                        $summary['skipped']++;

                        continue;
                    }

                    $note = "Pembatalan penjualan {$locked->code} oleh {$user->name} - "
                        . ($item->product?->name ?? 'produk');
                    if (! $this->restoreItem($locked, (int) $item->owner_stock_id, (int) $item->product_id, $qty, $user, $note)) {
                        $summary['skipped']++;

                        continue;
                    }

                    $summary['items']++;
                    $summary['qty'] += $qty;
                }

                // Voucher dipakai lagi: isActive()/pengecekan "sudah digunakan" membaca baris ini.
                VoucherRedemption::where('penjualan_id', $locked->id)->delete();
                // PromotionApplication dibiarkan sebagai jejak; laporan promo memfilter lewat
                // penjualan induk (soft delete), jadi tidak ikut terhitung.
                $locked->transaction?->delete();
            }

            $locked->items()->delete(); // soft delete
            $locked->forceFill([
                'delete_reason' => $reason ? mb_substr(trim($reason), 0, 255) : null,
                'deleted_by' => $user->getAuthIdentifier(),
            ])->save();
            $locked->delete();

            return $summary;
        }, 3);
    }

    private function restoreItem(Penjualan $sale, int $ownerStockId, int $productId, int $qty, Authenticatable $user, string $note): bool
    {
        $stock = OwnerStock::withTrashed()->whereKey($ownerStockId)->lockForUpdate()->first();
        if (! $stock) {
            return false;
        }

        if (! $stock->isDeficit()) {
            $this->stockService->restore($stock, $qty, Penjualan::class, $sale->id, $user, $note);

            return true;
        }

        // Batch MINUS: kembalikan hanya sebesar minus yang masih ada. Bila minus sudah ditutup
        // penerimaan barang, sisanya kembali ke batch normal terbaru (bukan membuat saldo MINUS positif).
        $minus = max(0, -(int) $stock->qty);
        $toDeficit = min($qty, $minus);
        $excess = $qty - $toDeficit;

        if ($toDeficit > 0) {
            $this->stockService->restore($stock, $toDeficit, Penjualan::class, $sale->id, $user, $note);
        }

        if ($excess > 0) {
            $target = OwnerStock::notDeficit()
                ->where('owner_id', $stock->owner_id)
                ->where('product_id', $productId)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $this->stockService->restore(
                $target ?? $stock,
                $excess,
                Penjualan::class,
                $sale->id,
                $user,
                $note . ' (minus sudah tertutup, dikembalikan ke batch terbaru)'
            );
        }

        return true;
    }
}