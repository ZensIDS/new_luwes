<?php

namespace App\Exports\LaporanGudang;

use App\Models\Product;

/** A4: qty tetap angka murni; tulisan konversi (mis. "2 dus 3 pcs") dipisah ke kolom sendiri. */
final class Konversi
{
    public static function of(?Product $product, int|float|null $qty): ?string
    {
        if (! $product || $qty === null) {
            return null;
        }

        $text = $product->konversiDisplay($qty);

        return ($text && $text !== '-') ? $text : null;
    }
}