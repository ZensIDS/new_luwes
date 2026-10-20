<?php

namespace App\Support;

/**
 * Helper pencarian "luas" (LIKE %kata%) yang dipakai bersama oleh menu
 * Stock, Pembelian, Penerimaan, Request Order, dan Delivery Order.
 *
 * Aturan:
 *  - Pencarian dicocokkan di MANA SAJA pada teks ("kacang hijau" menemukan "ABC KACANG HIJAU",
 *    "4567" menemukan barcode "12345678").
 *  - Input dipecah per kata (dipisah spasi). Setiap kata harus ketemu (AND), tetapi boleh ketemu di
 *    kolom yang berbeda (nama / barcode / kode PO / dll). Jadi "hijau kacang" tetap menemukan
 *    "ABC KACANG HIJAU".
 *  - Karakter spesial LIKE (% _ \) dianggap teks biasa.
 *  - Jumlah kata dan panjang input dibatasi supaya query tidak membengkak.
 *
 * Contoh pemakaian:
 *
 *   SearchHelper::apply($query, $searchValue, function ($q, $like) {
 *       $q->where('products.name', 'like', $like)
 *         ->orWhere('products.code', 'like', $like);
 *   });
 *
 *   // atau kalau hanya kolom biasa:
 *   SearchHelper::applyColumns($query, $searchValue, ['products.name', 'products.code']);
 */
class SearchHelper
{
    public const MAX_TOKENS = 5;
    public const MAX_LENGTH = 100;

    /**
     * Rapikan input: trim, rapatkan spasi ganda (termasuk tab/newline), batasi panjang.
     */
    public static function normalize(?string $value): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return mb_substr($value, 0, self::MAX_LENGTH);
    }

    /**
     * Escape karakter spesial LIKE supaya % _ \ dianggap teks biasa.
     */
    public static function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    /**
     * Pola LIKE "di mana saja": %kata%
     */
    public static function contains(string $value): string
    {
        return '%' . self::escapeLike($value) . '%';
    }

    /**
     * Pecah input menjadi daftar pola LIKE (%kata%), unik, maksimal MAX_TOKENS kata.
     *
     * @return string[]
     */
    public static function tokens(?string $value): array
    {
        $value = self::normalize($value);

        if ($value === '') {
            return [];
        }

        $words = array_values(array_unique(explode(' ', $value)));
        $words = array_slice($words, 0, self::MAX_TOKENS);

        return array_map(fn (string $w) => self::contains($w), $words);
    }

    /**
     * Pasang filter pencarian ke query.
     *
     * Untuk tiap kata dibuat satu grup WHERE ( ... ) yang diisi oleh $match,
     * antar kata digabung AND. $match menerima ($q, $like) dan bebas memakai
     * where/orWhere/orWhereIn di dalamnya.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     * @param  callable(\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder, string): mixed  $match
     */
    public static function apply($query, ?string $search, callable $match)
    {
        foreach (self::tokens($search) as $like) {
            $query->where(function ($q) use ($match, $like) {
                $match($q, $like);
            });
        }

        return $query;
    }

    /**
     * Versi singkat: cocokkan tiap kata ke salah satu dari $columns (OR antar kolom).
     *
     * @param  string[]  $columns
     */
    public static function applyColumns($query, ?string $search, array $columns)
    {
        return self::apply($query, $search, function ($q, $like) use ($columns) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'like', $like);
            }
        });
    }
}