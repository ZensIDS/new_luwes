<?php

namespace App\Services;

use App\Models\OwnerStock;
use App\Models\Penjualan;
use App\Models\StockAdjustment;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Semua hal tentang stok minus (batch MINUS) untuk admin outlet:
 *  - jumlah produk minus (banner / dashboard)
 *  - daftar produk minus + sejak kapan + penjualan terkait
 *  - "Tutup minus" (adjust ke 0 dengan alasan wajib, tercatat di Kartu Stok)
 *  - laporan penjualan melebihi stok (penjualan_items.is_oversold)
 *
 * Pembuatan batch MINUS & pelunasan otomatis lewat penerimaan ada di OutletStockService.
 */
class DeficitStockService
{
    public function __construct(protected OutletStockService $stockService)
    {
    }

    /** Baris batch MINUS yang saldonya masih negatif (belum ditutup). */
    protected function openDeficits(?int $outletId = null): Builder
    {
        return DB::table('owner_stocks as os')
            ->whereNull('os.deleted_at')
            ->where('os.batch_number', OwnerStock::DEFICIT_BATCH)
            ->where('os.qty', '<', 0)
            ->when($outletId, fn ($query) => $query->where('os.owner_id', $outletId));
    }

    /** Jumlah produk yang sedang minus (satu batch MINUS per outlet + produk). */
    public function countOpen(?int $outletId = null): int
    {
        return $this->openDeficits($outletId)->count();
    }

    /**
     * Daftar produk minus, satu halaman. Tiap baris diperkaya dengan:
     *  - since        : waktu penjualan pertama pada episode minus sekarang
     *  - sales_count  : jumlah penjualan (invoice) pada episode minus sekarang
     */
    public function paginateOpen(?int $outletId, string $search, int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->openDeficits($outletId)
            ->join('products as p', function ($join) {
                $join->on('p.id', '=', 'os.product_id')->whereNull('p.deleted_at');
            })
            ->join('outlets as o', 'o.id', '=', 'os.owner_id')
            ->when($search !== '', function ($query) use ($search) {
                $like = addcslashes($search, '\\%_');
                $query->where(function ($where) use ($like) {
                    $where->where('p.name', 'like', "{$like}%")
                        ->orWhere('p.code', 'like', "%{$like}%");
                });
            })
            ->orderBy('os.qty') // minus terbesar dulu
            ->orderBy('p.name')
            ->select([
                'os.id', 'os.owner_id', 'os.product_id', 'os.qty', 'os.hpp',
                'p.code as product_code', 'p.name as product_name', 'p.satuan',
                'o.name as outlet_name',
            ]);

        $page = $query->paginate($perPage)->withQueryString();
        $episodes = $this->episodes($page->getCollection()->pluck('id')->all());
        $expiredQty = $this->expiredQuantities($page->getCollection());

        $page->setCollection($page->getCollection()->map(function ($row) use ($episodes, $expiredQty) {
            $episode = $episodes[$row->id] ?? null;
            // Batch expired berstok pada produk yang sama: kemungkinan barang expired sudah dibuang
            // atau barang baru belum tercatat (notice kombinasi expired + minus).
            $row->expired_qty = (int) ($expiredQty[$row->owner_id.':'.$row->product_id] ?? 0);
            $row->qty = (int) $row->qty;
            $row->since = $episode['since'] ?? null;
            $row->sales_count = (int) ($episode['sales_count'] ?? 0);

            return $row;
        }));

        return $page;
    }

    /**
     * Total qty batch expired berstok per (outlet:produk) untuk baris-baris yang sedang ditampilkan.
     *
     * @return array<string, int>
     */
    protected function expiredQuantities($rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        return DB::table('owner_stocks')
            ->whereNull('deleted_at')
            ->where('qty', '>', 0)
            ->whereNotNull('expired_at')
            ->where('expired_at', '<', today()->toDateString())
            ->where(function ($query) {
                $query->whereNull('batch_number')->orWhere('batch_number', '!=', OwnerStock::DEFICIT_BATCH);
            })
            ->whereIn('product_id', $rows->pluck('product_id')->unique()->all())
            ->whereIn('owner_id', $rows->pluck('owner_id')->unique()->all())
            ->select('owner_id', 'product_id')
            ->selectRaw('SUM(qty) as qty')
            ->groupBy('owner_id', 'product_id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->owner_id.':'.$row->product_id => (int) $row->qty])
            ->all();
    }

    /**
     * "Episode minus" = rangkaian pergerakan batch MINUS sejak saldo terakhir >= 0.
     * Penjualan terkait = movement keluar bertipe Penjualan pada episode itu.
     *
     * @param  array<int>  $ownerStockIds
     * @return array<int, array{since: ?string, sales_count: int}>
     */
    protected function episodes(array $ownerStockIds): array
    {
        if ($ownerStockIds === []) {
            return [];
        }

        $lastSettled = DB::table('stock_movements')
            ->whereIn('owner_stock_id', $ownerStockIds)
            ->where('balance', '>=', 0)
            ->select('owner_stock_id')
            ->selectRaw('MAX(id) as last_id')
            ->groupBy('owner_stock_id');

        return DB::table('stock_movements as sm')
            ->leftJoinSub($lastSettled, 'ok', 'ok.owner_stock_id', '=', 'sm.owner_stock_id')
            ->whereIn('sm.owner_stock_id', $ownerStockIds)
            ->whereRaw('sm.id > COALESCE(ok.last_id, 0)')
            ->where('sm.type', 'out')
            ->where('sm.reference_type', Penjualan::class)
            ->groupBy('sm.owner_stock_id')
            ->select('sm.owner_stock_id')
            ->selectRaw('MIN(sm.created_at) as since')
            ->selectRaw('COUNT(DISTINCT sm.reference_id) as sales_count')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->owner_stock_id => [
                'since' => $row->since,
                'sales_count' => (int) $row->sales_count,
            ]])
            ->all();
    }

    /**
     * Tutup minus: barang memang ada tapi tidak pernah dicatat, atau selisih diterima.
     * Saldo batch MINUS dijadikan 0 lewat adjust() (StockAdjustment + StockMovement),
     * alasan wajib. Tidak menyentuh stok batch lain.
     */
    public function close(OwnerStock $deficit, string $reason, ?Authenticatable $user = null): StockAdjustment
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Alasan wajib diisi untuk menutup stok minus.');
        }

        return DB::transaction(function () use ($deficit, $reason, $user) {
            $locked = OwnerStock::whereKey($deficit->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isDeficit()) {
                throw new RuntimeException('Hanya batch MINUS yang bisa ditutup lewat halaman ini.');
            }

            if ((int) $locked->qty >= 0) {
                throw new RuntimeException('Stok minus ini sudah tertutup.');
            }

            $minus = abs((int) $locked->qty);

            $adjustment = $this->stockService->adjust(
                $locked,
                0,
                now()->toDateString(),
                "Tutup stok minus: {$reason}",
                $user,
                "Tutup stok minus ({$minus}) - {$reason}"
            );

            return $adjustment;
        });
    }

    /**
     * Laporan penjualan melebihi stok sistem (item is_oversold) pada rentang tanggal.
     *
     * @return array{rows: LengthAwarePaginator, summary: array{invoices:int, items:int, qty:int, still_minus:int}}
     */
    public function oversoldReport(
        ?int $outletId,
        ?string $from,
        ?string $to,
        ?int $productId = null,
        int $perPage = 50
    ): array {
        $base = function () use ($outletId, $from, $to, $productId) {
            $query = DB::table('penjualan_items as pi')
                ->join('penjualans as pj', function ($join) {
                    $join->on('pj.id', '=', 'pi.penjualan_id')->whereNull('pj.deleted_at');
                })
                ->join('owner_stocks as os', 'os.id', '=', 'pi.owner_stock_id')
                ->where('pi.is_oversold', true)
                ->whereNull('pi.deleted_at')
                ->when($outletId, fn ($q) => $q->where('pj.outlet_id', $outletId))
                ->when($productId, fn ($q) => $q->where('pi.product_id', $productId));

            return \App\Support\ReportQuery::betweenDates($query, 'pj.created_at', $from, $to);
        };

        $summaryRow = $base()
            ->selectRaw('COUNT(DISTINCT pi.penjualan_id) as invoices')
            ->selectRaw('COUNT(*) as items')
            ->selectRaw('COALESCE(SUM(pi.qty), 0) as qty')
            ->selectRaw('COUNT(DISTINCT CASE WHEN os.qty < 0 AND os.deleted_at IS NULL THEN os.id END) as still_minus')
            ->first();

        $rows = $base()
            ->join('products as p', 'p.id', '=', 'pi.product_id')
            ->join('outlets as o', 'o.id', '=', 'pj.outlet_id')
            ->leftJoin('users as u', 'u.id', '=', 'pj.kasir_id')
            ->orderByDesc('pj.created_at')
            ->orderByDesc('pi.id')
            ->select([
                'pi.id', 'pi.penjualan_id', 'pi.product_id', 'pi.qty', 'pi.price', 'pi.hpp',
                'pj.code as invoice_code', 'pj.created_at as sold_at',
                'p.code as product_code', 'p.name as product_name', 'p.satuan',
                'o.name as outlet_name', 'u.name as cashier_name',
                'os.qty as deficit_balance',
            ])
            ->paginate($perPage)
            ->withQueryString();

        return [
            'rows' => $rows,
            'summary' => [
                'invoices' => (int) ($summaryRow->invoices ?? 0),
                'items' => (int) ($summaryRow->items ?? 0),
                'qty' => (int) ($summaryRow->qty ?? 0),
                'still_minus' => (int) ($summaryRow->still_minus ?? 0),
            ],
        ];
    }
}