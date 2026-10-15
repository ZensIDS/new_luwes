<?php

namespace App\Services;

use App\Models\OutletPurchase;
use App\Models\Product;
use App\Models\ProductMinimumAdjustment;
use App\Models\RefundPenjualan;
use App\Support\ReportQuery;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Perhitungan min stok otomatis 3 bulan (sumber tunggal untuk laporan Minimal Stock dan PO gudang).
 *
 * Aturan (dipindahkan apa adanya dari OutletLaporanService::minimumStock()):
 *  - Jendela  = 3 bulan penuh SEBELUM bulan berjalan (bulan ke-4 memakai bulan 1-3, bulan ke-5 memakai 2-4, ...).
 *  - Penjualan = qty item penjualan (status paid / null) di jendela, dikurangi retur per item,
 *                ditambah qty replacement di jendela.
 *  - Frekuensi = jumlah PO outlet (OutletPurchase berstatus pending/received, DRAFT tidak dihitung) di jendela / 3,
 *                dibulatkan ke atas, dibatasi 1..4.
 *  - Faktor    = 1 => 2,5 | 2 => 1,875 | 3 => 1,25 | 4 => 0,625.
 *  - Eligible  = penjualan pertama ada di bulan awal jendela atau lebih lama.
 *  - Min stok  = eligible ? CEIL(rata-rata terjual per bulan x faktor) : products.min_stock (default manual).
 *
 * Untuk gudang (PO): min dasar produk = JUMLAH min tiap outlet; produk eligible bila penjualan pertamanya di
 * outlet mana pun sudah memenuhi syarat. Adjustment aktif (product_minimum_adjustments) menambah min dasar:
 *   min_final = CEIL(min_dasar x (1 + persen / 100)).
 *
 * Semua pengambilan data memakai agregasi SQL (bukan memuat seluruh riwayat penjualan ke memori).
 */
class MinimumStockCalculator
{
    public const MONTHS = 3;

    private const FACTORS = [1 => 2.5, 2 => 1.875, 3 => 1.25, 4 => 0.625];

    /** @return array{0: Carbon, 1: Carbon} [awal jendela, akhir jendela] */
    public function window(?Carbon $asOf = null): array
    {
        $asOf = ($asOf ? $asOf->copy() : now())->endOfDay();
        $end = $asOf->copy()->startOfMonth()->subSecond();
        $start = $end->copy()->startOfMonth()->subMonths(self::MONTHS - 1);

        return [$start, $end];
    }

    /**
     * Rumus per outlet x produk.
     *
     * @return array{average_sold: float, po_per_month: float, frequency: int, factor: float, min_stock: int}
     */
    public function rule(float $soldQty, int $poCount, bool $eligible, int $manualMin): array
    {
        $averageSold = $soldQty / self::MONTHS;
        $poPerMonth = $poCount / self::MONTHS;
        $frequency = max(1, min(4, (int) ceil($poPerMonth)));
        $factor = self::FACTORS[$frequency];

        return [
            'average_sold' => $averageSold,
            'po_per_month' => $poPerMonth,
            'frequency' => $frequency,
            'factor' => $factor,
            'min_stock' => $eligible ? (int) ceil($averageSold * $factor) : $manualMin,
        ];
    }

    /** Eligible bila bulan penjualan pertama <= bulan awal jendela. */
    public function isEligible($firstSale, Carbon $windowStart): bool
    {
        return $firstSale
            && Carbon::parse($firstSale)->startOfMonth()->lte($windowStart->copy()->startOfMonth());
    }

    /**
     * Qty terjual bersih di jendela dan penjualan pertama, per "outlet-produk".
     *
     * @param  array<int>|null  $productIds  null = semua produk
     * @return array{sales: Collection, first_sales: Collection}
     *         sales: key "outlet_id-product_id" => qty; first_sales: key => Carbon
     */
    public function salesAggregates(Carbon $windowStart, Carbon $windowEnd, ?int $outletId = null, ?array $productIds = null): array
    {
        if ($productIds !== null && $productIds === []) {
            return ['sales' => collect(), 'first_sales' => collect()];
        }

        // 1) Penjualan pertama (seluruh riwayat sampai akhir jendela), 1 query agregat.
        $firstSales = collect();
        $this->soldItemsQuery($outletId, $productIds)
            ->where('pj.created_at', '<=', $windowEnd)
            ->groupBy('pj.outlet_id', 'pi.product_id')
            ->selectRaw('pj.outlet_id, pi.product_id, MIN(pj.created_at) AS first_sale')
            ->get()
            ->each(function ($row) use ($firstSales) {
                $firstSales[$row->outlet_id.'-'.$row->product_id] = Carbon::parse($row->first_sale);
            });

        // 2) Qty terjual di jendela (belum dikurangi retur), 1 query agregat.
        $sales = collect();
        $this->soldItemsQuery($outletId, $productIds)
            ->where('pj.created_at', '>=', $windowStart)
            ->where('pj.created_at', '<=', $windowEnd)
            ->groupBy('pj.outlet_id', 'pi.product_id')
            ->selectRaw('pj.outlet_id, pi.product_id, SUM(pi.qty) AS qty')
            ->get()
            ->each(function ($row) use ($sales) {
                $sales[$row->outlet_id.'-'.$row->product_id] = (float) $row->qty;
            });

        // 3) Kurangi retur per item: aturan lama max(0, qty - retur) == qty - min(qty, retur).
        //    Hanya item yang pernah diretur yang diambil (jumlahnya jauh lebih kecil dari seluruh item).
        $returned = $this->returnedQuantities($windowEnd, $outletId, $productIds);
        foreach ($returned->keys()->chunk(1000) as $chunk) {
            $this->soldItemsQuery($outletId, $productIds)
                ->whereIn('pi.id', $chunk->all())
                ->where('pj.created_at', '>=', $windowStart)
                ->where('pj.created_at', '<=', $windowEnd)
                ->get(['pi.id', 'pi.qty', 'pj.outlet_id', 'pi.product_id'])
                ->each(function ($row) use ($sales, $returned) {
                    $key = $row->outlet_id.'-'.$row->product_id;
                    $sales[$key] = ($sales[$key] ?? 0) - min((float) $row->qty, (float) ($returned[$row->id] ?? 0));
                });
        }

        // 4) Tambah qty replacement di jendela.
        DB::table('refund_penjualan_items as ri')
            ->join('refund_penjualans as rp', 'rp.id', '=', 'ri.refund_penjualan_id')
            ->whereNull('rp.deleted_at')
            ->where('ri.type', 'replacement')
            ->whereBetween('rp.created_at', [$windowStart, $windowEnd])
            ->when($outletId, fn ($query) => $query->where('rp.outlet_id', $outletId))
            ->when($productIds !== null, fn ($query) => $query->whereIn('ri.product_id', $productIds))
            ->groupBy('rp.outlet_id', 'ri.product_id')
            ->selectRaw('rp.outlet_id, ri.product_id, SUM(COALESCE(ri.qty, 0)) AS qty')
            ->get()
            ->each(function ($row) use ($sales) {
                $key = $row->outlet_id.'-'.$row->product_id;
                $sales[$key] = ($sales[$key] ?? 0) + (float) $row->qty;
            });

        return ['sales' => $sales, 'first_sales' => $firstSales];
    }

    /** Jumlah PO outlet (OutletPurchase pending/received, tanpa draft) di jendela: outlet_id => total. */
    public function poCounts(Carbon $windowStart, Carbon $windowEnd, ?int $outletId = null): Collection
    {
        return OutletPurchase::query()
            ->whereIn('status', [OutletPurchase::STATUS_PENDING, OutletPurchase::STATUS_RECEIVED])
            ->select('outlet_id', DB::raw('COUNT(*) as total'))
            ->tap(fn ($q) => ReportQuery::betweenDates($q, 'purchase_date', $windowStart->toDateString(), $windowEnd->toDateString()))
            ->when($outletId, fn ($query) => $query->where('outlet_id', $outletId))
            ->groupBy('outlet_id')
            ->pluck('total', 'outlet_id');
    }

    /**
     * Min stok untuk GUDANG per produk (dipakai PO / Cek Barang).
     *
     * @param  array<int>|null  $productIds  null = semua produk
     * @return array<int, array{
     *     source: string, min_dasar: int, min_final: int, adjustment_pct: float,
     *     average_sold: float, manual_min: int, first_sale: ?Carbon
     * }>  key = product_id; source: "auto" (3 bulan) | "manual" (belum 3 bulan)
     */
    public function forWarehouse(?array $productIds = null, ?Carbon $asOf = null): array
    {
        $asOf = $asOf ? $asOf->copy() : now();
        [$windowStart, $windowEnd] = $this->window($asOf);

        $manual = Product::query()
            ->when($productIds !== null, fn ($query) => $query->whereIn('id', $productIds))
            ->pluck('min_stock', 'id');

        $aggregate = $this->salesAggregates($windowStart, $windowEnd, null, $productIds);
        $poCounts = $this->poCounts($windowStart, $windowEnd);

        // Jumlahkan min tiap outlet per produk (rumus outlet sama dengan laporan lama).
        $autoMin = [];
        $sold = [];
        foreach ($aggregate['sales'] as $key => $qty) {
            [$outlet, $product] = array_map('intval', explode('-', $key));
            $rule = $this->rule(max(0.0, (float) $qty), (int) ($poCounts[$outlet] ?? 0), true, 0);
            $autoMin[$product] = ($autoMin[$product] ?? 0) + $rule['min_stock'];
            $sold[$product] = ($sold[$product] ?? 0) + max(0.0, (float) $qty);
        }

        // Produk eligible bila penjualan pertamanya di outlet mana pun sudah cukup lama.
        // $firstSaleByProduct = penjualan pertama paling awal di semua outlet (info laporan).
        $eligible = [];
        $firstSaleByProduct = [];
        foreach ($aggregate['first_sales'] as $key => $firstSale) {
            $product = (int) explode('-', $key)[1];
            if (! isset($eligible[$product]) && $this->isEligible($firstSale, $windowStart)) {
                $eligible[$product] = true;
            }
            if (! isset($firstSaleByProduct[$product]) || $firstSale->lt($firstSaleByProduct[$product])) {
                $firstSaleByProduct[$product] = $firstSale;
            }
        }

        $adjustments = ProductMinimumAdjustment::query()
            ->activeOn($asOf->toDateString())
            ->when($productIds !== null, fn ($query) => $query->whereIn('product_id', $productIds))
            ->orderByDesc('active_from')
            ->orderByDesc('id')
            ->get(['product_id', 'adjustment_percentage'])
            ->unique('product_id')
            ->pluck('adjustment_percentage', 'product_id');

        $result = [];
        foreach ($manual as $productId => $manualMin) {
            $manualMin = (int) ($manualMin ?? 0);
            $isAuto = isset($eligible[$productId]);
            $base = $isAuto ? (int) ($autoMin[$productId] ?? 0) : $manualMin;
            $pct = (float) ($adjustments[$productId] ?? 0);

            $result[(int) $productId] = [
                'source' => $isAuto ? 'auto' : 'manual',
                'min_dasar' => $base,
                'min_final' => $pct > 0 ? (int) ceil($base * (1 + $pct / 100)) : $base,
                'adjustment_pct' => $pct,
                'average_sold' => (float) (($sold[$productId] ?? 0) / self::MONTHS),
                'manual_min' => $manualMin,
                'first_sale' => $firstSaleByProduct[$productId] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Min stok untuk SATU OUTLET per produk (dipakai Cek Barang Belanja Langsung / POUT toko).
     *
     * Memakai ulang rule(), salesAggregates() dan poCounts() dengan filter outlet, jadi rumus gudang tidak berubah:
     *  - Eligible per outlet x produk: penjualan pertama DI OUTLET ITU ada di bulan awal jendela atau lebih lama.
     *  - Eligible  : min_dasar = CEIL(rata-rata terjual/bulan x faktor), faktor dari jumlah POUT outlet itu
     *                (pending + received, tanpa draft) di jendela.
     *  - Tidak eligible: min 0, source "insufficient" (badge "data belum cukup"). products.min_stock TIDAK dipakai.
     *  - Adjustment aktif per produk (sama dengan gudang): min_final = CEIL(min_dasar x (1 + persen / 100)).
     *
     * @param  array<int>  $productIds
     * @return array<int, array{
     *     source: string, min_dasar: int, min_final: int, adjustment_pct: float, average_sold: float,
     *     po_per_month: float, frequency: int, factor: float, first_sale: ?Carbon
     * }>  key = product_id; source: "auto" (3 bulan) | "insufficient" (data belum cukup)
     */
    public function forOutlet(int $outletId, array $productIds, ?Carbon $asOf = null): array
    {
        $productIds = array_values(array_unique(array_map('intval', array_filter($productIds))));
        if ($productIds === []) {
            return [];
        }

        $asOf = $asOf ? $asOf->copy() : now();
        [$windowStart, $windowEnd] = $this->window($asOf);

        $aggregate = $this->salesAggregates($windowStart, $windowEnd, $outletId, $productIds);
        $poCount = (int) ($this->poCounts($windowStart, $windowEnd, $outletId)[$outletId] ?? 0);

        $adjustments = ProductMinimumAdjustment::query()
            ->activeOn($asOf->toDateString())
            ->whereIn('product_id', $productIds)
            ->orderByDesc('active_from')
            ->orderByDesc('id')
            ->get(['product_id', 'adjustment_percentage'])
            ->unique('product_id')
            ->pluck('adjustment_percentage', 'product_id');

        $result = [];
        foreach ($productIds as $productId) {
            $key = $outletId.'-'.$productId;
            $firstSale = $aggregate['first_sales'][$key] ?? null;
            $eligible = $this->isEligible($firstSale, $windowStart);
            $sold = max(0.0, (float) ($aggregate['sales'][$key] ?? 0));

            $rule = $this->rule($sold, $poCount, $eligible, 0);
            $base = $eligible ? (int) $rule['min_stock'] : 0;
            $pct = (float) ($adjustments[$productId] ?? 0);

            $result[$productId] = [
                'source' => $eligible ? 'auto' : 'insufficient',
                'min_dasar' => $base,
                'min_final' => $pct > 0 ? (int) ceil($base * (1 + $pct / 100)) : $base,
                'adjustment_pct' => $pct,
                'average_sold' => (float) $rule['average_sold'],
                'po_per_month' => (float) $rule['po_per_month'],
                'frequency' => (int) $rule['frequency'],
                'factor' => (float) $rule['factor'],
                'first_sale' => $firstSale,
            ];
        }

        return $result;
    }

    /**
     * Retur per item penjualan: penjualan_item_id => qty diretur (sampai $end).
     * Retur tanpa penjualan_item_id dialokasikan ke invoice terbaru yang memenuhi syarat di outlet yang sama.
     * (Dipindahkan dari OutletLaporanService; $productIds opsional untuk membatasi ke produk tertentu.)
     */
    public function returnedQuantities(Carbon $end, ?int $outletId, ?array $productIds = null): Collection
    {
        $refunds = RefundPenjualan::query()
            ->where('created_at', '<=', $end)
            ->when($outletId, fn ($query) => $query->where('outlet_id', $outletId))
            ->when($productIds !== null, fn ($query) => $query->whereHas('items', function ($items) use ($productIds) {
                $items->where('type', 'return')->whereIn('product_id', $productIds);
            }))
            ->with(['items' => function ($items) use ($productIds) {
                $items->where('type', 'return');
                if ($productIds !== null) {
                    $items->whereIn('product_id', $productIds);
                }
            }])
            ->orderBy('created_at')->orderBy('id')->get();
        $items = $refunds->flatMap(fn ($refund) => $refund->items->where('type', 'return'));
        $allocated = $items->filter(fn ($item) => $item->penjualan_item_id)
            ->groupBy('penjualan_item_id')
            ->map(fn ($group) => (float) $group->sum('qty'));
        $unlinked = $items->reject(fn ($item) => $item->penjualan_item_id);
        if ($unlinked->isEmpty()) {
            return $allocated;
        }

        $candidates = \App\Models\PenjualanItem::query()
            ->whereIn('product_id', $unlinked->pluck('product_id')->unique())
            ->whereHas('penjualan', function ($query) use ($end, $outletId) {
                $query->where('created_at', '<=', $end)
                    ->where(fn ($q) => $q->where('status', 'paid')->orWhereNull('status'))
                    ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));
            })
            ->with('penjualan')
            ->get()
            ->sort(fn ($a, $b) => $b->penjualan->created_at <=> $a->penjualan->created_at ?: $b->id <=> $a->id)
            ->groupBy(fn ($item) => $item->penjualan->outlet_id.'-'.$item->product_id);

        foreach ($refunds as $refund) {
            foreach ($refund->items->where('type', 'return')->reject(fn ($item) => $item->penjualan_item_id) as $item) {
                $remaining = (float) $item->qty;
                foreach ($candidates->get($refund->outlet_id.'-'.$item->product_id, collect()) as $candidate) {
                    if ($remaining <= 0) {
                        break;
                    }
                    if ($candidate->penjualan->created_at->gt($refund->created_at)
                        || ($refund->penjualan_id && (int) $candidate->penjualan_id !== (int) $refund->penjualan_id)) {
                        continue;
                    }
                    $available = max(0, (float) $candidate->qty - (float) ($allocated[$candidate->id] ?? 0));
                    $take = min($remaining, $available);
                    $allocated[$candidate->id] = (float) ($allocated[$candidate->id] ?? 0) + $take;
                    $remaining -= $take;
                }
            }
        }

        return $allocated;
    }

    /** Dasar query: item penjualan valid (item & penjualan tidak terhapus, status paid/null, outlet terisi). */
    private function soldItemsQuery(?int $outletId, ?array $productIds)
    {
        return DB::table('penjualan_items as pi')
            ->join('penjualans as pj', 'pj.id', '=', 'pi.penjualan_id')
            ->whereNull('pi.deleted_at')
            ->whereNull('pj.deleted_at')
            ->whereNotNull('pj.outlet_id')
            ->where(function ($query) {
                $query->where('pj.status', 'paid')->orWhereNull('pj.status');
            })
            ->when($outletId, fn ($query) => $query->where('pj.outlet_id', $outletId))
            ->when($productIds !== null, fn ($query) => $query->whereIn('pi.product_id', $productIds));
    }
}