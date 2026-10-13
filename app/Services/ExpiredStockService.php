<?php

namespace App\Services;

use App\Models\OwnerStock;
use App\Models\StockAdjustment;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Semua hal tentang stok expired outlet untuk admin outlet:
 *  - ringkasan notice (sudah expired, akan expired <= N hari, kombinasi expired + minus)
 *  - daftar batch expired / akan expired
 *  - "Musnahkan" (write-off: adjust ke 0 dengan alasan wajib, tercatat di Kartu Stok)
 *
 * Batch expired tidak ikut FIFO kasir (lihat CashierSaleService); batch MINUS tidak punya
 * expired_at dan tidak pernah dianggap expired.
 */
class ExpiredStockService
{
    public const DEFAULT_WARNING_DAYS = 30;

    public function __construct(protected OutletStockService $stockService)
    {
    }

    /** Ambang notice "akan expired" (hari), diatur di halaman Setting. */
    public function warningDays(): int
    {
        $settings = json_decode(Storage::disk('public')->get('settings.json') ?? '{}', true) ?? [];
        $days = (int) ($settings['expiry_warning_days'] ?? self::DEFAULT_WARNING_DAYS);

        return $days >= 1 && $days <= 365 ? $days : self::DEFAULT_WARNING_DAYS;
    }

    /** Batch normal (bukan MINUS) yang masih berstok dan punya tanggal expired. */
    protected function stockedBatches(?int $outletId): Builder
    {
        return DB::table('owner_stocks as os')
            ->whereNull('os.deleted_at')
            ->where('os.qty', '>', 0)
            ->whereNotNull('os.expired_at')
            ->where(function ($query) {
                $query->whereNull('os.batch_number')->orWhere('os.batch_number', '!=', OwnerStock::DEFICIT_BATCH);
            })
            ->when($outletId, fn ($query) => $query->where('os.owner_id', $outletId));
    }

    protected function expired(?int $outletId): Builder
    {
        return $this->stockedBatches($outletId)
            ->where('os.expired_at', '<', today()->toDateString());
    }

    protected function expiringSoon(?int $outletId, int $days): Builder
    {
        return $this->stockedBatches($outletId)
            ->where('os.expired_at', '>=', today()->toDateString())
            ->where('os.expired_at', '<=', today()->addDays($days)->toDateString());
    }

    /** Batch expired berstok pada produk yang batch MINUS-nya masih negatif. */
    protected function expiredWithOpenDeficit(?int $outletId): Builder
    {
        return $this->expired($outletId)->whereExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('owner_stocks as d')
                ->whereColumn('d.owner_id', 'os.owner_id')
                ->whereColumn('d.product_id', 'os.product_id')
                ->where('d.batch_number', OwnerStock::DEFICIT_BATCH)
                ->where('d.qty', '<', 0)
                ->whereNull('d.deleted_at');
        });
    }

    /**
     * Ringkasan untuk banner / sidebar / dashboard. Di-cache 60 detik; kunci memuat ambang hari
     * sehingga perubahan Setting langsung berlaku.
     *
     * @return array{days:int, expired:int, soon:int, combo:int}
     */
    public function summary(?int $outletId = null): array
    {
        $days = $this->warningDays();

        return Cache::remember($this->cacheKey($outletId, $days), 60, function () use ($outletId, $days) {
            $combo = DB::query()
                ->fromSub(
                    $this->expiredWithOpenDeficit($outletId)->select('os.owner_id', 'os.product_id')->groupBy('os.owner_id', 'os.product_id'),
                    'combo'
                )
                ->count();

            return [
                'days' => $days,
                'expired' => $this->expired($outletId)->count(),
                'soon' => $this->expiringSoon($outletId, $days)->count(),
                'combo' => $combo,
            ];
        });
    }

    public function forgetCache(?int $outletId = null): void
    {
        $days = $this->warningDays();
        Cache::forget($this->cacheKey(null, $days));
        if ($outletId) {
            Cache::forget($this->cacheKey($outletId, $days));
        }
    }

    protected function cacheKey(?int $outletId, int $days): string
    {
        return 'expiry-summary:'.($outletId ?: 'all').':'.$days;
    }

    /**
     * Daftar batch untuk satu tab ('expired' atau 'soon'), batch terlama / terdekat dulu.
     * Tiap baris diperkaya: days_left (negatif = sudah lewat) dan deficit_qty
     * (saldo minus produk itu bila masih negatif, untuk notice kombinasi).
     */
    public function paginate(string $tab, ?int $outletId, string $search, int $perPage = 25): LengthAwarePaginator
    {
        $base = $tab === 'soon'
            ? $this->expiringSoon($outletId, $this->warningDays())
            : $this->expired($outletId);

        $page = $base
            ->join('products as p', function ($join) {
                $join->on('p.id', '=', 'os.product_id')->whereNull('p.deleted_at');
            })
            ->join('outlets as o', 'o.id', '=', 'os.owner_id')
            ->when($search !== '', function ($query) use ($search) {
                $like = addcslashes($search, '\\%_');
                $query->where(function ($where) use ($like) {
                    $where->where('p.name', 'like', "{$like}%")
                        ->orWhere('p.code', 'like', "%{$like}%")
                        ->orWhere('os.batch_number', 'like', "{$like}%");
                });
            })
            ->orderBy('os.expired_at')
            ->orderBy('p.name')
            ->select([
                'os.id', 'os.owner_id', 'os.product_id', 'os.qty', 'os.hpp', 'os.batch_number', 'os.expired_at',
                'p.code as product_code', 'p.name as product_name', 'p.satuan',
                'o.name as outlet_name',
            ])
            ->paginate($perPage)
            ->withQueryString();

        $rows = $page->getCollection();
        $deficits = $rows->isEmpty() ? collect() : DB::table('owner_stocks')
            ->whereNull('deleted_at')
            ->where('batch_number', OwnerStock::DEFICIT_BATCH)
            ->where('qty', '<', 0)
            ->whereIn('product_id', $rows->pluck('product_id')->unique()->all())
            ->whereIn('owner_id', $rows->pluck('owner_id')->unique()->all())
            ->get(['owner_id', 'product_id', 'qty'])
            ->mapWithKeys(fn ($row) => [$row->owner_id.':'.$row->product_id => (int) $row->qty]);

        $page->setCollection($rows->map(function ($row) use ($deficits) {
            $row->qty = (int) $row->qty;
            $row->days_left = (int) today()->diffInDays(Carbon::parse($row->expired_at)->startOfDay(), false);
            $row->deficit_qty = $deficits[$row->owner_id.':'.$row->product_id] ?? null;

            return $row;
        }));

        return $page;
    }

    /**
     * Musnahkan / write-off batch expired: saldo dijadikan 0 lewat adjust()
     * (StockAdjustment + StockMovement), alasan wajib. Tidak menyentuh batch lain
     * (termasuk batch MINUS produk yang sama).
     */
    public function writeOff(OwnerStock $batch, string $reason, ?Authenticatable $user = null): ?StockAdjustment
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Alasan wajib diisi untuk memusnahkan stok expired.');
        }

        $adjustment = DB::transaction(function () use ($batch, $reason, $user) {
            $locked = OwnerStock::whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if ($locked->isDeficit()) {
                throw new RuntimeException('Batch MINUS tidak bisa dimusnahkan. Gunakan halaman Stok Minus.');
            }
            if (! $locked->expired_at || ! $locked->expired_at->lt(today())) {
                throw new RuntimeException('Batch ini belum expired.');
            }

            $qty = (int) $locked->qty;
            if ($qty < 1) {
                throw new RuntimeException('Batch ini sudah tidak memiliki stok.');
            }

            return $this->stockService->adjust(
                $locked,
                0,
                now()->toDateString(),
                "Musnahkan stok expired: {$reason}",
                $user,
                "Musnahkan stok expired ({$qty}) - {$reason}"
            );
        });

        $this->forgetCache((int) $batch->owner_id);

        return $adjustment;
    }
}