<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Produk di bawah min stok (lonceng notifikasi dan widget dashboard).
 *
 * Sumber min stok = MinimumStockCalculator::forWarehouse() (sama dengan PO dan laporan Minimal Stock):
 *   min_final = hasil 3 bulan (atau products.min_stock bila belum 3 bulan) + adjustment aktif.
 *
 * Aturan:
 *  - stok fisik gudang = SUM(stocks.qty) (stocks soft-deleted diabaikan), selalu realtime (1 query agregat)
 *  - kandidat          = produk dengan min_final > 0
 *  - hampir habis      = stok < min_final (K5)
 *
 * Perhitungan min final cukup berat (agregasi riwayat penjualan) sedangkan lonceng dimuat di setiap halaman,
 * maka PETA MIN STOK saja yang di-cache pendek (CACHE_TTL detik). Stok tetap dihitung realtime.
 * Cache dibuang saat adjustment disimpan (forgetCache()).
 */
class LowStockService
{
    private const CACHE_TTL = 600;

    /** Hapus cache peta min stok (dipanggil setelah adjustment diubah). */
    public static function forgetCache(): void
    {
        Cache::forget(self::cacheKey(now()->toDateString()));
    }

    private static function cacheKey(string $date): string
    {
        return 'low_stock:min_map:'.$date;
    }

    /**
     * Peta min stok gudang untuk produk dengan min_final > 0.
     *
     * @return array<int, array{min_final: int, min_dasar: int, source: string, adjustment_pct: float, manual_min: int}>
     */
    public function minimums(): array
    {
        $today = now()->toDateString();

        return Cache::remember(self::cacheKey($today), self::CACHE_TTL, function () {
            $map = [];
            foreach (app(MinimumStockCalculator::class)->forWarehouse() as $productId => $min) {
                if ($min['min_final'] > 0) {
                    $map[$productId] = [
                        'min_final' => $min['min_final'],
                        'min_dasar' => $min['min_dasar'],
                        'source' => $min['source'],
                        'adjustment_pct' => $min['adjustment_pct'],
                        'manual_min' => $min['manual_min'],
                    ];
                }
            }

            return $map;
        });
    }

    /**
     * Produk hampir habis: product_id => [stock, min_final, ...]. Stok realtime, 1 query agregat.
     *
     * @return array<int, array<string, mixed>>
     */
    private function lowRows(): array
    {
        $minimums = $this->minimums();
        if ($minimums === []) {
            return [];
        }

        $stocks = DB::table('stocks')
            ->whereNull('deleted_at')
            ->selectRaw('product_id, SUM(qty) AS qty')
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        $low = [];
        foreach ($minimums as $productId => $min) {
            $stock = (int) ($stocks[$productId] ?? 0);
            if ($stock < $min['min_final']) {
                $low[$productId] = $min + ['stock' => $stock];
            }
        }

        return $low;
    }

    /**
     * Untuk lonceng notifikasi (layouts.master).
     *
     * @return array{count:int, products:Collection}
     */
    public function summary(int $limit = 20): array
    {
        $low = $this->lowRows();
        $count = count($low);

        $products = $count === 0
            ? collect()
            : Product::query()
                ->select('id', 'code', 'name', 'min_stock')
                ->whereIn('id', array_keys($low))
                ->orderBy('name')
                ->limit($limit)
                ->get()
                ->each(function ($product) use ($low) {
                    $product->stock_qty = $low[$product->id]['stock'];
                    $product->effective_min_qty = $low[$product->id]['min_final'];
                    $product->min_source = $low[$product->id]['source'];
                });

        return ['count' => $count, 'products' => $products];
    }

    /**
     * Untuk widget "Produk di Bawah Min Stok" di dashboard, diurutkan defisit terbesar (lalu nama).
     * Field: id, code, name, min_stock, current_stock, effective_min, adjustment_percentage, deficit, min_source.
     */
    public function dashboardList(): Collection
    {
        $low = $this->lowRows();
        if ($low === []) {
            return collect();
        }

        return Product::query()
            ->select('id', 'code', 'name', 'min_stock')
            ->whereIn('id', array_keys($low))
            ->get()
            ->map(function ($product) use ($low) {
                $row = $low[$product->id];
                $product->current_stock = (int) $row['stock'];
                $product->effective_min = (int) $row['min_final'];
                $product->adjustment_percentage = (float) $row['adjustment_pct'];
                $product->min_source = $row['source'];
                $product->deficit = max(0, (int) $row['min_final'] - (int) $row['stock']);

                return $product;
            })
            ->sort(fn ($a, $b) => [$b->deficit, mb_strtolower($a->name)] <=> [$a->deficit, mb_strtolower($b->name)])
            ->values();
    }
}