<?php

namespace App\Services;

use App\Models\OutletPrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Folder di index Master Harga Jual: dikelompokkan per KATA PERTAMA nama produk, per outlet.
 *
 * - Nama yang diawali angka/simbol (atau kosong) digabung ke satu folder "#/Lainnya" (K13).
 * - Hasil hitungan folder disimpan di cache per outlet dan dibersihkan otomatis saat aturan harga
 *   dibuat/dihapus/dipulihkan atau nama produk berubah (lihat hook di model OutletPrice dan Product).
 *   TTL 10 menit menjadi pengaman untuk perubahan yang tidak lewat model (import/query langsung).
 * - Ekspresi kunci folder yang sama dipakai untuk menghitung folder dan untuk menyaring daftar produk
 *   di dalam folder, sehingga jumlah di card selalu sama dengan isi folder.
 */
class PriceFolderIndex
{
    /** Nilai parameter URL untuk folder "#/Lainnya". */
    public const OTHER = '#';

    public const OTHER_LABEL = '#/Lainnya';

    private const TTL_SECONDS = 600;

    /**
     * Ekspresi SQL kunci folder (butuh tabel `products` ter-join). Nama produk sudah disimpan huruf besar
     * dan di-trim oleh model Product; UPPER/TRIM di sini hanya pengaman untuk data lama.
     */
    public static function keySql(): string
    {
        return "CASE WHEN COALESCE(LEFT(TRIM(products.name), 1) REGEXP '^[A-Za-z]', 0) = 1 "
            . "THEN UPPER(SUBSTRING_INDEX(TRIM(products.name), ' ', 1)) ELSE '" . self::OTHER . "' END";
    }

    /** Rapikan nilai folder dari URL supaya cocok dengan kunci folder. */
    public static function normalize(string $folder): string
    {
        return mb_strtoupper(trim($folder), 'UTF-8');
    }

    /**
     * Daftar folder satu outlet: [['key' => 'INDOMIE', 'label' => 'INDOMIE', 'total' => 12], ...],
     * urut abjad, "#/Lainnya" paling akhir.
     *
     * @return array<int,array{key:string,label:string,total:int}>
     */
    public function folders(int $outletId): array
    {
        return Cache::remember($this->cacheKey($outletId), self::TTL_SECONDS, function () use ($outletId) {
            // leftJoin + toBase(): sama dengan query halaman produk (aturan tanpa produk ikut terhitung di "#/Lainnya");
            // soft delete aturan harga tetap diterapkan oleh toBase().
            $rows = OutletPrice::query()
                ->leftJoin('products', 'products.id', '=', 'outlet_prices.product_id')
                ->where('outlet_prices.outlet_id', $outletId)
                ->selectRaw(self::keySql() . ' as folder_key, COUNT(*) as total')
                ->groupBy('folder_key')
                ->toBase()
                ->get();

            $folders = $rows
                ->map(fn ($row) => [
                    'key' => (string) $row->folder_key,
                    'label' => $row->folder_key === self::OTHER ? self::OTHER_LABEL : (string) $row->folder_key,
                    'total' => (int) $row->total,
                ])
                ->sort(function (array $a, array $b) {
                    // "#/Lainnya" selalu terakhir.
                    return [$a['key'] === self::OTHER, $a['key']] <=> [$b['key'] === self::OTHER, $b['key']];
                })
                ->values()
                ->all();

            return $folders;
        });
    }

    /** Jumlah seluruh aturan harga outlet (untuk card "All Product"). */
    public function totalOf(array $folders): int
    {
        return (int) array_sum(array_column($folders, 'total'));
    }

    /** Batasi query (yang sudah ter-join ke `products`) ke satu folder. */
    public function constrain(Builder $query, string $folder): Builder
    {
        return $query->whereRaw('(' . self::keySql() . ') = ?', [$folder]);
    }

    /** Buang cache folder satu outlet (versi baru; cache lama kedaluwarsa sendiri). */
    public function forget(int $outletId): void
    {
        Cache::forever($this->versionKey($outletId), (string) Str::uuid());
    }

    /** Nama produk berubah / dihapus / dipulihkan: bersihkan semua outlet yang punya aturan produk itu. */
    public function forgetProduct(int $productId): void
    {
        OutletPrice::withTrashed()
            ->where('product_id', $productId)
            ->distinct()
            ->pluck('outlet_id')
            ->each(fn ($outletId) => $this->forget((int) $outletId));
    }

    private function cacheKey(int $outletId): string
    {
        return 'outlet_price_folders:' . $outletId . ':' . Cache::get($this->versionKey($outletId), '0');
    }

    private function versionKey(int $outletId): string
    {
        return 'outlet_price_folders_version:' . $outletId;
    }
}