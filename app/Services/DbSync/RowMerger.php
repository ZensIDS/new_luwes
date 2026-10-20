<?php

namespace App\Services\DbSync;

/**
 * Menggabungkan satu baris yang berubah di server lokal DAN hosting sejak sinkron terakhir.
 *
 * Per kolom (three-way merge terhadap nilai dasar = data saat sinkron terakhir):
 *  - hanya satu sisi yang mengubah        -> ambil nilai sisi itu
 *  - dua sisi mengubah ke nilai yang sama -> ambil nilai itu
 *  - kolom penghitung (additive)          -> lokal + hosting - dasar (stok terjual di dua tempat tetap terhitung)
 *  - kolom max                            -> nilai terbesar
 *  - selain itu                           -> menang baris dengan updated_at terbaru
 */
class RowMerger
{
    /**
     * @param  string[]  $additive
     * @param  string[]  $max
     * @param  string  $prefer  'local' | 'server' bila updated_at sama
     */
    public function __construct(
        private array $additive = [],
        private array $max = [],
        private string $prefer = 'local',
    ) {}

    public function merge(array $local, array $server, ?array $base): array
    {
        if ($base === null) {
            return $this->newerWins($local, $server) === 'local' ? $local : $server;
        }

        $winner = $this->newerWins($local, $server);
        $merged = [];

        foreach (array_keys($local + $server) as $column) {
            $l = $local[$column] ?? null;
            $s = $server[$column] ?? null;
            $b = $base[$column] ?? null;

            $localChanged = ! self::same($l, $b);
            $serverChanged = ! self::same($s, $b);

            if (! $serverChanged || self::same($l, $s)) {
                $merged[$column] = $l;
            } elseif (! $localChanged) {
                $merged[$column] = $s;
            } elseif (in_array($column, $this->additive, true) && self::numeric($l, $s, $b)) {
                $merged[$column] = self::format(
                    (float) $l + (float) $s - (float) $b,
                    [$l, $s, $b]
                );
            } elseif ($column === 'updated_at' || in_array($column, $this->max, true)) {
                $merged[$column] = self::greater($l, $s) ? $l : $s;
            } else {
                $merged[$column] = $winner === 'local' ? $l : $s;
            }
        }

        return $merged;
    }

    /**
     * Kolom yang benar-benar bentrok: diubah di dua sisi ke nilai berbeda dan bukan
     * kolom penghitung / max / updated_at (yang bisa digabung tanpa kehilangan data).
     *
     * @return string[]
     */
    public function conflicts(array $local, array $server, ?array $base): array
    {
        $conflicts = [];

        foreach (array_keys($local + $server) as $column) {
            $l = $local[$column] ?? null;
            $s = $server[$column] ?? null;

            if (self::same($l, $s) || $column === 'updated_at') {
                continue;
            }

            if ($base === null) {
                $conflicts[] = $column;

                continue;
            }

            $b = $base[$column] ?? null;
            $mergeable = in_array($column, $this->max, true)
                || (in_array($column, $this->additive, true) && self::numeric($l, $s, $b));

            if (! self::same($l, $b) && ! self::same($s, $b) && ! $mergeable) {
                $conflicts[] = $column;
            }
        }

        return $conflicts;
    }

    /** 'local' atau 'server': baris dengan updated_at terbaru. */
    public function newerWins(array $local, array $server): string
    {
        $l = (string) ($local['updated_at'] ?? '');
        $s = (string) ($server['updated_at'] ?? '');

        if ($l === $s) {
            return $this->prefer === 'server' ? 'server' : 'local';
        }

        return $l > $s ? 'local' : 'server';
    }

    public static function sameRow(array $a, array $b): bool
    {
        foreach (array_keys($a + $b) as $column) {
            if (! self::same($a[$column] ?? null, $b[$column] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Bandingkan nilai dari dua server yang representasinya bisa sedikit beda
     * (10 vs "10.00", JSON dengan/tanpa spasi).
     */
    public static function same(mixed $a, mixed $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 1e-9 * max(1, abs((float) $a));
        }

        $a = (string) $a;
        $b = (string) $b;

        if ($a === $b) {
            return true;
        }

        if (in_array($a[0] ?? '', ['{', '['], true) && in_array($b[0] ?? '', ['{', '['], true)) {
            $da = json_decode($a, true);
            $db = json_decode($b, true);

            return $da !== null && $da == $db;
        }

        return false;
    }

    private static function numeric(mixed ...$values): bool
    {
        foreach ($values as $v) {
            if ($v !== null && ! is_numeric($v)) {
                return false;
            }
        }

        return true;
    }

    private static function greater(mixed $a, mixed $b): bool
    {
        if ($b === null) {
            return true;
        }
        if ($a === null) {
            return false;
        }

        return is_numeric($a) && is_numeric($b) ? (float) $a >= (float) $b : (string) $a >= (string) $b;
    }

    /** Tulis hasil penjumlahan dengan jumlah desimal yang sama seperti nilai aslinya. */
    private static function format(float $value, array $originals): int|string
    {
        $decimals = 0;
        foreach ($originals as $v) {
            if (is_string($v) && str_contains($v, '.')) {
                $decimals = max($decimals, strlen(substr($v, strpos($v, '.') + 1)));
            } elseif (is_float($v)) {
                $decimals = max($decimals, 6);
            }
        }

        return $decimals === 0 ? (int) round($value) : number_format($value, $decimals, '.', '');
    }
}
