<?php

namespace App\Services;

use App\Models\OtherIncome;
use App\Models\OtherIncomeCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pencatatan Other Income otomatis.
 *
 * Saat ini hanya satu jenis: Listing Product (produk berstatus Listing).
 * Aturan "kapan boleh memicu" (create / update pertama) ada di controller (tahap B);
 * service ini hanya mencatat dengan aman.
 */
class OtherIncomeService
{
    public const NOMINAL_BELUM_DIATUR = 'Nominal Listing belum diatur. Atur dulu di Other Income > Kategori.';

    /**
     * Catat Listing income untuk satu produk.
     *
     * - Tanpa outlet (umum). Nominal & nama produk disalin saat ini, jadi mengubah nominal
     *   di kemudian hari tidak mengubah income lama.
     * - Nominal belum diatur -> ValidationException pada field `status_produk` (K10). Karena dipanggil
     *   di dalam transaksi pembuat produk, produk ikut batal tersimpan.
     * - Income untuk produk ini sudah ada -> dianggap sudah tercatat, mengembalikan baris lama (bukan error).
     *
     * Panggil di dalam DB::transaction milik pemanggil agar gagal catat = produk batal dibuat.
     */
    public function recordListing(Product $product, ?User $user = null): OtherIncome
    {
        return DB::transaction(function () use ($product, $user) {
            $category = OtherIncomeCategory::query()
                ->system()
                ->where('slug', OtherIncomeCategory::LISTING_SLUG)
                ->lockForUpdate()
                ->first();

            if (! $category) {
                throw ValidationException::withMessages([
                    'status_produk' => 'Kategori Listing Product belum tersedia. Jalankan migrasi Other Income terlebih dahulu.',
                ]);
            }

            // Sudah pernah tercatat untuk produk ini (termasuk yang ter-soft-delete): jangan dobel.
            $existing = $this->findListing($product, $category);
            if ($existing) {
                return $existing;
            }

            $nominal = (float) $category->nominal;
            if ($nominal < 1) {
                throw ValidationException::withMessages([
                    'status_produk' => self::NOMINAL_BELUM_DIATUR,
                ]);
            }

            try {
                // Transaksi bersarang (savepoint) supaya bentrok unique tidak merusak transaksi pemanggil.
                return DB::transaction(fn () => OtherIncome::create([
                    'outlet_id' => null,
                    'other_income_category_id' => $category->id,
                    'tanggal' => now()->toDateString(),
                    'jumlah' => $nominal,
                    'keterangan' => 'Listing produk: ' . $product->name,
                    'source_type' => $product->getMorphClass(),
                    'source_id' => $product->getKey(),
                    'product_name_snapshot' => $product->name,
                    'created_by' => $user?->getKey() ?? auth()->id(),
                ]));
            } catch (QueryException $e) {
                // Lapis terakhir: unique (source_type, source_id, kategori) menolak income kembar
                // dari dua request bersamaan. Anggap sudah tercatat.
                if ($this->isDuplicateKey($e) && ($existing = $this->findListing($product, $category))) {
                    return $existing;
                }

                throw $e;
            }
        });
    }

    /** Apakah produk ini sudah punya Listing income (termasuk yang ter-soft-delete). */
    public function hasListing(Product $product): bool
    {
        $category = OtherIncomeCategory::listing();

        return $category !== null && $this->findListing($product, $category) !== null;
    }

    /** Nominal Listing saat ini; null bila belum diatur. */
    public function listingNominal(): ?float
    {
        $nominal = OtherIncomeCategory::listing()?->nominal;

        return ($nominal !== null && $nominal >= 1) ? (float) $nominal : null;
    }

    private function findListing(Product $product, OtherIncomeCategory $category): ?OtherIncome
    {
        return OtherIncome::withTrashed()
            ->where('source_type', $product->getMorphClass())
            ->where('source_id', $product->getKey())
            ->where('other_income_category_id', $category->id)
            ->first();
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === '23000'
            && in_array((int) ($e->errorInfo[1] ?? 0), [1062, 19], true); // 1062 = MySQL, 19 = SQLite
    }
}