<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Perhitungan stok minimum dilakukan sepenuhnya di SQL (realtime, tanpa cache),
 * sehingga PHP tidak perlu memuat semua produk ke memori.
 *
 * Aturan (sama dengan logika lama):
 *  - stok fisik gudang   = SUM(stocks.qty) (stocks soft-deleted diabaikan)
 *  - min efektif         = CEIL(min_stock * (1 + persen_adjustment_aktif / 100))
 *                          (adjustment aktif terbaru: active_from desc, id desc)
 *  - kandidat            = produk dengan min_stock > 0 ATAU punya adjustment aktif
 *  - hampir habis        = stok <= min efektif
 *
 * Dipakai bersama oleh composer layouts.master (lonceng) dan DashboardController.
 */
class LowStockService
{
    private const EFFECTIVE_MIN = 'CEIL(p.min_stock * (1 + p.adjustment_percentage / 100))';

    /**
     * Subquery: produk kandidat + stock_qty + adjustment_percentage (adjustment aktif, 0 bila tidak ada).
     */
    public function baseQuery(?string $date = null): \Illuminate\Database\Eloquent\Builder
    {
        $date = $date ?? now()->toDateString();

        $activeAdjustment = '
            SELECT a.adjustment_percentage
            FROM product_minimum_adjustments a
            WHERE a.product_id = products.id
              AND a.active_from <= ?
              AND (a.active_until IS NULL OR a.active_until >= ?)
            ORDER BY a.active_from DESC, a.id DESC
            LIMIT 1
        ';

        return Product::query()
            ->select('products.id', 'products.code', 'products.name', 'products.min_stock')
            ->selectRaw(
                '(SELECT COALESCE(SUM(s.qty), 0) FROM stocks s WHERE s.product_id = products.id AND s.deleted_at IS NULL) AS stock_qty'
            )
            ->selectRaw("COALESCE(({$activeAdjustment}), 0) AS adjustment_percentage", [$date, $date])
            ->where(function ($query) use ($date) {
                $query->where('products.min_stock', '>', 0)
                    ->orWhereExists(function ($sub) use ($date) {
                        $sub->select(DB::raw(1))
                            ->from('product_minimum_adjustments as pa')
                            ->whereColumn('pa.product_id', 'products.id')
                            ->where('pa.active_from', '<=', $date)
                            ->where(function ($q) use ($date) {
                                $q->whereNull('pa.active_until')->orWhere('pa.active_until', '>=', $date);
                            });
                    });
            });
    }

    /**
     * Produk hampir habis (stok <= min efektif). Kolom: id, code, name, min_stock,
     * stock_qty, adjustment_percentage, effective_min_qty. Belum diurutkan / dibatasi.
     */
    public function lowStockQuery(?string $date = null): Builder
    {
        return DB::query()
            ->fromSub($this->baseQuery($date)->toBase(), 'p')
            ->select('p.*')
            ->selectRaw(self::EFFECTIVE_MIN . ' AS effective_min_qty')
            ->whereRaw('p.stock_qty <= ' . self::EFFECTIVE_MIN);
    }

    /**
     * Untuk lonceng notifikasi (layouts.master).
     *
     * @return array{count:int, products:Collection}
     */
    public function summary(int $limit = 20): array
    {
        $count = $this->lowStockQuery()->count();

        $products = $count === 0
            ? collect()
            : $this->lowStockQuery()
                ->orderBy('p.name')
                ->limit($limit)
                ->get()
                ->map(function ($row) {
                    $row->stock_qty = (int) $row->stock_qty;
                    $row->effective_min_qty = (int) $row->effective_min_qty;

                    return $row;
                });

        return ['count' => $count, 'products' => $products];
    }

    /**
     * Untuk widget "Produk di Bawah Min Stok" di dashboard.
     * Hanya produk dengan min_stock > 0, diurutkan defisit terbesar (lalu nama).
     * Field: id, code, name, min_stock, current_stock, effective_min, adjustment_percentage, deficit.
     */
    public function dashboardList(): Collection
    {
        return $this->lowStockQuery()
            ->where('p.min_stock', '>', 0)
            ->selectRaw('GREATEST(0, ' . self::EFFECTIVE_MIN . ' - p.stock_qty) AS deficit')
            ->orderByDesc('deficit')
            ->orderBy('p.name')
            ->get()
            ->map(function ($row) {
                $row->current_stock = (int) $row->stock_qty;
                $row->effective_min = (int) $row->effective_min_qty;
                $row->adjustment_percentage = (float) $row->adjustment_percentage;
                $row->deficit = (int) $row->deficit;

                return $row;
            });
    }
}