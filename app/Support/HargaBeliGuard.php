<?php

namespace App\Support;

use App\Exceptions\HargaBeliGuardException;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Pagar terpusat untuk harga beli / HPP.
 *
 * Yang dijaga: products.harga_beli, products.harga_beli_besar, owner_stocks.hpp.
 * Hanya boleh berubah di dalam HargaBeliGuard::allow('<jalur>', fn () => ...).
 *
 *   JALUR RESMI : edit-product, outlet-purchase, master-harga
 *   TURUNAN     : turunan     (nilai diturunkan dari HPP terbaru / batch asal, bukan input baru)
 *   LAINNYA     : maintenance (command perbaikan), seeder (data demo)
 *
 * Mode (config/harga_beli.php): log = hanya catat, enforce = tolak.
 * Penulisan lewat query builder (DB::table, upsert, insert, Model::query()->update)
 * tidak memicu event model; pemanggilnya wajib memanggil assertAllowed() sendiri.
 */
class HargaBeliGuard
{
    public const SOURCES = [
        'edit-product',
        'outlet-purchase',
        'master-harga',
        'turunan',
        'maintenance',
        'seeder',
    ];

    /** Kolom yang dijaga per tabel. */
    public const GUARDED = [
        'products'     => ['harga_beli', 'harga_beli_besar'],
        'owner_stocks' => ['hpp'],
    ];

    /** @var array<int, string> tumpukan jalur yang sedang aktif (mendukung nested). */
    protected static array $stack = [];

    /**
     * Jalankan $callback dengan izin menulis harga beli / HPP atas nama $source.
     *
     * @template T
     * @param  Closure():T  $callback
     * @return T
     */
    public static function allow(string $source, Closure $callback)
    {
        if (! in_array($source, self::SOURCES, true)) {
            throw new InvalidArgumentException("Jalur harga beli tidak dikenal: {$source}");
        }

        self::$stack[] = $source;

        try {
            return $callback();
        } finally {
            array_pop(self::$stack);
        }
    }

    public static function isAllowed(): bool
    {
        return self::$stack !== [];
    }

    /** Jalur terdalam yang sedang aktif (null bila di luar allow()). */
    public static function current(): ?string
    {
        return self::$stack === [] ? null : end(self::$stack);
    }

    public static function mode(): string
    {
        return config('harga_beli.mode', 'log') === 'enforce' ? 'enforce' : 'log';
    }

    /**
     * Dipanggil dari hook `saving` model. Memeriksa kolom terjaga yang berubah.
     * Record baru: hanya dianggap menulis bila nilainya terisi dan bukan 0.
     */
    public static function checkModel(Model $model): void
    {
        if (self::isAllowed()) {
            return;
        }

        $fields = self::GUARDED[$model->getTable()] ?? [];
        $changes = [];

        foreach ($fields as $field) {
            if ($model->exists) {
                if ($model->isDirty($field)) {
                    $changes[$field] = [$model->getOriginal($field), $model->getAttribute($field)];
                }
                continue;
            }

            $value = $model->getAttribute($field);
            if ($value !== null && (float) $value != 0.0) {
                $changes[$field] = [null, $value];
            }
        }

        if ($changes !== []) {
            self::violation(
                $model->getTable() . ($model->exists ? '#' . $model->getKey() : ' (baru)'),
                $changes
            );
        }
    }

    /**
     * Untuk penulisan lewat query builder (tidak memicu event model).
     * Panggil tepat sebelum update/upsert/insert harga beli / HPP.
     */
    public static function assertAllowed(string $context): void
    {
        if (self::isAllowed()) {
            return;
        }

        self::violation($context, []);
    }

    protected static function violation(string $target, array $changes): void
    {
        $payload = [
            'target'  => $target,
            'changes' => $changes,
            'mode'    => self::mode(),
            'caller'  => self::callerFrames(),
            'url'     => app()->runningInConsole() ? 'console' : (request()->method() . ' ' . request()->path()),
            'user_id' => auth()->id(),
        ];

        if (self::mode() === 'enforce') {
            Log::channel(config('harga_beli.log_channel'))->warning('[HargaBeliGuard] DITOLAK', $payload);

            throw new HargaBeliGuardException(
                'Harga beli / HPP hanya boleh diubah lewat Edit Product, Outlet Purchases, atau Master Harga Jual. (' . $target . ')'
            );
        }

        Log::channel(config('harga_beli.log_channel'))->warning('[HargaBeliGuard] di luar jalur resmi (mode log)', $payload);
    }

    /** Beberapa frame kode aplikasi (bukan guard/model) untuk melacak pemanggil. */
    protected static function callerFrames(): array
    {
        $base = base_path() . DIRECTORY_SEPARATOR;
        $frames = [];

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 40) as $frame) {
            $file = $frame['file'] ?? null;
            if (! $file || str_contains($file, 'vendor' . DIRECTORY_SEPARATOR) || $file === __FILE__) {
                continue;
            }
            if (str_contains($file, DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $frames[] = str_replace($base, '', $file) . ':' . ($frame['line'] ?? '?');
            if (count($frames) >= 5) {
                break;
            }
        }

        return $frames;
    }
}