<?php

namespace App\Services;

use App\Models\OutletPurchase;
use App\Models\OutletPurchaseItem;
use App\Models\OwnerStock;
use Illuminate\Support\Facades\DB;

/**
 * Data pembanding untuk Cek Barang Belanja Langsung (POUT) satu outlet:
 *  - stock    : stok toko = SUM(owner_stocks.qty) per produk di outlet itu (batch MINUS ikut mengurangi).
 *  - on_order : "Dalam Pesanan" = qty POUT LAIN berstatus Menunggu Penerimaan (pending) di outlet yang sama.
 *               Draft (draft ini maupun draft lain) tidak dihitung; POUT yang sedang diedit dikecualikan.
 *  - price    : harga awal baris = HPP batch terbaru di outlet itu (LatestHpp), fallback products.harga_beli.
 *
 * Tiap sumber 1 query agregat untuk semua produk sekaligus (tanpa query per produk).
 * Sengaja TIDAK membaca stok gudang (K5).
 */
class OutletPurchaseStockInfo
{
    public function __construct(private LatestHpp $latestHpp)
    {
    }

    /**
     * @param  array<int>  $productIds
     * @return array<int, array{stock: float, on_order: float, price: float}>  key = product_id
     */
    public function forProducts(int $outletId, array $productIds, ?int $exceptPurchaseId = null): array
    {
        $productIds = array_values(array_unique(array_map('intval', array_filter($productIds))));
        if ($productIds === []) {
            return [];
        }

        $stock = OwnerStock::query()
            ->where('owner_id', $outletId)
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(qty) AS qty')
            ->pluck('qty', 'product_id');

        $onOrder = OutletPurchaseItem::query()
            ->join('outlet_purchases as op', 'op.id', '=', 'outlet_purchase_items.outlet_purchase_id')
            ->whereNull('op.deleted_at')
            ->where('op.outlet_id', $outletId)
            ->where('op.status', OutletPurchase::STATUS_PENDING)
            ->when($exceptPurchaseId, fn ($query) => $query->where('op.id', '!=', $exceptPurchaseId))
            ->whereIn('outlet_purchase_items.product_id', $productIds)
            ->groupBy('outlet_purchase_items.product_id')
            ->select('outlet_purchase_items.product_id', DB::raw('SUM(outlet_purchase_items.qty) AS qty'))
            ->pluck('qty', 'product_id');

        $prices = $this->latestHpp->forProducts($outletId, $productIds);

        $result = [];
        foreach ($productIds as $productId) {
            $result[$productId] = [
                'stock' => (float) ($stock[$productId] ?? 0),
                'on_order' => (float) ($onOrder[$productId] ?? 0),
                'price' => (float) ($prices[$productId] ?? 0),
            ];
        }

        return $result;
    }
}