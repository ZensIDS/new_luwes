<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Helper kecil untuk query laporan agar cepat:
 *  - betweenDates(): filter tanggal tanpa whereDate() supaya index bisa dipakai
 *  - resolveReferences(): ambil dokumen referensi polymorphic secara batch
 *    (1 query per tipe dokumen), menggantikan `$m->reference_type::find()` per baris.
 */
class ReportQuery
{
    /**
     * Batas baris untuk PDF. DomPDF memuat seluruh tabel ke memori sebelum
     * menggambar, jadi ribuan baris bisa menghabiskan RAM / mematikan proses PHP
     * (browser hanya menampilkan ERR_EMPTY_RESPONSE). Sesuaikan angka ini dengan
     * kemampuan server; untuk data besar gunakan Export Excel.
     */
    public const PDF_MAX_ROWS = 1500;

    /** Naikkan batas memori & waktu khusus untuk request pembuatan PDF. */
    public static function preparePdf(): void
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);
    }

    /** Hentikan dengan pesan jelas bila baris terlalu banyak untuk dirender PDF. */
    public static function guardPdfRows(int $count): void
    {
        if ($count > self::PDF_MAX_ROWS) {
            abort(422, "Data terlalu banyak untuk PDF ({$count} baris, maksimal ".self::PDF_MAX_ROWS.'). '
                .'Persempit rentang tanggal atau gunakan Export Excel.');
        }
    }

    /**
     * Pengganti whereDate('col','>=',...)->whereDate('col','<=',...).
     * whereDate() membungkus kolom dengan DATE(), sehingga index tidak terpakai.
     */
    public static function betweenDates($query, string $column, ?string $mulai, ?string $selesai)
    {
        if ($mulai) {
            $query->where($column, '>=', Carbon::parse($mulai)->startOfDay());
        }
        if ($selesai) {
            $query->where($column, '<=', Carbon::parse($selesai)->endOfDay());
        }

        return $query;
    }

    /**
     * Memuat semua dokumen referensi milik kumpulan StockMovement sekaligus.
     *
     * @param  Collection  $movements  koleksi StockMovement
     * @param  array       $with       relasi eager-load per tipe, contoh:
     *                                 [Pembelian::class => ['supplier']]
     *                                 Relasi yang tidak ada di model akan dilewati.
     * @return array<string, \Illuminate\Database\Eloquent\Model>  key: "Tipe|id"
     */
    public static function resolveReferences(Collection $movements, array $with = []): array
    {
        $map = [];

        $movements
            ->filter(fn ($m) => $m->reference_type && $m->reference_id)
            ->groupBy('reference_type')
            ->each(function ($group, $type) use (&$map, $with) {
                if (! class_exists($type)) {
                    return;
                }

                $relations = array_values(array_filter(
                    $with[$type] ?? [],
                    fn ($rel) => method_exists($type, explode('.', $rel)[0])
                ));

                $group->pluck('reference_id')->unique()->values()->chunk(1000)
                    ->each(function ($ids) use ($type, $relations, &$map) {
                        $type::query()
                            ->with($relations)
                            ->whereIn((new $type)->getKeyName(), $ids)
                            ->get()
                            ->each(function ($ref) use ($type, &$map) {
                                $map[$type.'|'.$ref->getKey()] = $ref;
                            });
                    });
            });

        return $map;
    }

    /** Ambil dokumen referensi milik satu movement dari hasil resolveReferences(). */
    public static function ref(array $map, $movement)
    {
        if (! $movement->reference_type || ! $movement->reference_id) {
            return null;
        }

        return $map[$movement->reference_type.'|'.$movement->reference_id] ?? null;
    }
}