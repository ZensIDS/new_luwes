<?php

namespace App\Services;

use App\Models\OwnerStock;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Satu sumber kebenaran untuk "HPP terbaru" yang dipakai menghitung HARGA JUAL.
 *
 * HPP terbaru = HPP batch stok outlet yang paling baru dibuat (created_at, lalu id)
 * di antara batch yang masih tersedia (qty > 0 dan belum kedaluwarsa). Bila tidak
 * ada batch tersedia, dipakai batch terbaru apa pun; bila outlet belum punya batch
 * sama sekali, dipakai harga_beli produk.
 *
 * Batch MINUS (stok minus) tidak pernah dipakai sebagai sumber HPP.
 *
 * Service ini TIDAK mengubah HPP batch. HPP tiap batch tetap dipakai untuk
 * pembukuan stok (nilai persediaan, kartu stok, biaya pokok penjualan).
 */
class LatestHpp
{
    /** Batch yang menjadi "HPP terbaru" untuk outlet + produk (dipakai juga saat HPP dikoreksi). */
    public function stockFor(int $outletId, int $productId): ?OwnerStock
    {
        $base = fn () => OwnerStock::where('owner_id', $outletId)
            ->where('product_id', $productId)
            ->notDeficit()
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return $base()
            ->where('qty', '>', 0)
            ->where(function ($query) {
                $query->whereNull('expired_at')->orWhere('expired_at', '>=', today()->toDateString());
            })
            ->first()
            ?? $base()->first();
    }

    public function forProduct(int $outletId, int $productId): float
    {
        return $this->forProducts($outletId, [$productId])[$productId] ?? 0.0;
    }

    /**
     * HPP terbaru untuk satu produk TANPA melihat outlet (HPP dianggap sama di semua outlet).
     * Urutan: batch tersedia terbaru di outlet mana pun -> batch terbaru apa pun -> harga_beli produk.
     */
    public function forProductGlobal(int $productId): float
    {
        $base = fn () => OwnerStock::where('product_id', $productId)
            ->notDeficit()
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $stock = $base()
            ->where('qty', '>', 0)
            ->where(function ($query) {
                $query->whereNull('expired_at')->orWhere('expired_at', '>=', today()->toDateString());
            })
            ->first(['id', 'product_id', 'hpp'])
            ?? $base()->first(['id', 'product_id', 'hpp']);

        if ($stock && $stock->hpp !== null) {
            return (float) $stock->hpp;
        }

        return (float) (Product::whereKey($productId)->value('harga_beli') ?? 0);
    }

    /**
     * @param  iterable<int|string|null>  $productIds
     * @return array<int, float> product_id => HPP terbaru
     */
    public function forProducts(int $outletId, iterable $productIds): array
    {
        $ids = collect($productIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $hargaBeli = Product::whereIn('id', $ids->all())->pluck('harga_beli', 'id');
        $result = [];

        if ($outletId > 0) {
            $newestPerProduct = function ($rows) {
                return $rows->groupBy('product_id')->map(fn ($group) => $group->first());
            };

            $available = $newestPerProduct(
                OwnerStock::where('owner_id', $outletId)
                    ->whereIn('product_id', $ids->all())
                    ->notDeficit()
                    ->where('qty', '>', 0)
                    ->where(function ($query) {
                        $query->whereNull('expired_at')->orWhere('expired_at', '>=', today()->toDateString());
                    })
                    ->orderByDesc('created_at')->orderByDesc('id')
                    ->get(['id', 'product_id', 'hpp'])
            );

            $missing = $ids->reject(fn ($id) => $available->has($id))->values();
            $anyBatch = $missing->isEmpty()
                ? collect()
                : $newestPerProduct(
                    OwnerStock::where('owner_id', $outletId)
                        ->whereIn('product_id', $missing->all())
                        ->notDeficit()
                        ->orderByDesc('created_at')->orderByDesc('id')
                        ->get(['id', 'product_id', 'hpp'])
                );

            foreach ($ids as $id) {
                $stock = $available->get($id) ?? $anyBatch->get($id);
                $result[$id] = (float) ($stock?->hpp ?? $hargaBeli[$id] ?? 0);
            }

            return $result;
        }

        foreach ($ids as $id) {
            $result[$id] = (float) ($hargaBeli[$id] ?? 0);
        }

        return $result;
    }

    /**
     * Untuk relasi ownerStocks yang sudah dimuat (batch tersedia milik satu outlet):
     * pilih batch terbaru tanpa query tambahan.
     */
    public function fromLoaded(?Collection $stocks, float|int|string|null $hargaBeli = null): float
    {
        $newest = $stocks?->reduce(function ($carry, $stock) {
            if (! $carry) {
                return $stock;
            }

            $current = [$stock->created_at?->getTimestamp() ?? 0, (int) $stock->id];
            $best = [$carry->created_at?->getTimestamp() ?? 0, (int) $carry->id];

            return $current > $best ? $stock : $carry;
        });

        return (float) ($newest?->hpp ?? $hargaBeli ?? 0);
    }
}