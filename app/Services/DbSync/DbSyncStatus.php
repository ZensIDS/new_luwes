<?php

namespace App\Services\DbSync;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Status kesehatan koneksi ke database hosting dan sinkron terakhir, untuk kartu di dashboard.
 *
 * health: ok | warning | error | unconfigured
 */
class DbSyncStatus
{
    public function snapshot(bool $checkConnection = true): array
    {
        $configured = DbSyncService::isConfigured();
        $isLocalNode = (bool) config('dbsync.enabled');
        $connection = $configured && $checkConnection ? $this->checkConnection() : null;
        $lastRun = $this->lastRun();
        $activeRun = $this->activeRun();
        $lastSuccess = rescue(fn () => DB::table('db_sync_runs')
            ->whereIn('status', ['success', 'partial'])
            ->max('finished_at'), null, false);

        $staleAfter = max(10, 5 * (int) config('dbsync.interval', 2));
        $stale = ! $lastSuccess || Carbon::parse($lastSuccess)->lt(now()->subMinutes($staleAfter));
        $autoIncrement = $this->autoIncrement();
        $wantOffset = $isLocalNode ? 1 : 2;
        $badAutoIncrement = ($isLocalNode || $lastRun) && $autoIncrement
            && ($autoIncrement['increment'] < 2 || $autoIncrement['offset'] !== $wantOffset);

        [$health, $message] = match (true) {
            $connection && ! $connection['ok'] => ['error', 'Tidak dapat terhubung ke database hosting.'],
            $badAutoIncrement => ['error', sprintf(
                'Set DB_AUTO_INCREMENT_INCREMENT=2 dan DB_AUTO_INCREMENT_OFFSET=%d di .env %s (sekarang %d/%d), '
                .'lalu php artisan config:clear; tanpa itu id baru di lokal & hosting bisa bentrok.',
                $wantOffset, $isLocalNode ? 'server lokal' : 'hosting', $autoIncrement['increment'], $autoIncrement['offset']
            )],
            $activeRun => $activeRun['stalled']
                ? ['warning', 'Sinkron tampak macet: tidak ada kemajuan sejak '.$activeRun['idle_minutes'].' menit lalu. Batalkan lalu ulangi.']
                : ['ok', 'Sinkron sedang berjalan ('.$activeRun['percent'].'%).'],
            ! $isLocalNode && ! $lastRun && ! $configured => ['unconfigured', 'Belum ada server lokal yang tersinkron ke database ini.'],
            $isLocalNode && ! $configured => ['error', 'DB_SERVER_* belum diisi di .env server lokal.'],
            $lastRun && $lastRun['status'] === 'failed' => ['error', 'Sinkron terakhir gagal.'],
            $lastRun && $lastRun['status'] === 'cancelled' => ['warning', 'Sinkron terakhir dibatalkan sebelum selesai. Klik "Ulangi Sinkron" untuk melanjutkan.'],
            $isLocalNode && ! $lastRun => ['warning', 'Belum pernah sinkron. Sinkron pertama berjalan otomatis dalam '
                .config('dbsync.interval', 2).' menit (atau jalankan sekarang: make sync).'],
            $stale => ['warning', $isLocalNode
                ? "Belum ada sinkron berhasil dalam {$staleAfter} menit terakhir. Pastikan cron scheduler berjalan."
                : "Server lokal belum sinkron dalam {$staleAfter} menit terakhir (PC server mati / internet putus?)."],
            $lastRun && $lastRun['conflict_count'] > 0 => ['warning', "Sinkron berjalan, tetapi {$lastRun['conflict_count']} baris KONFLIK dilewati (diubah berbeda di lokal & hosting). Pilih versi yang dipakai pada daftar konflik di bawah."],
            $lastRun && $lastRun['status'] === 'partial' => ['warning', 'Sinkron berjalan, tetapi ada baris yang gagal disinkronkan.'],
            default => ['ok', $isLocalNode ? 'Terhubung ke hosting dan tersinkron.' : 'Server lokal aktif dan tersinkron ke hosting ini.'],
        };

        return [
            'health' => $health,
            'message' => $message,
            'node' => $isLocalNode ? 'local' : 'hosting',
            'configured' => $configured,
            'server' => $configured ? [
                'host' => config('database.connections.'.config('dbsync.server_connection').'.host'),
                'database' => config('database.connections.'.config('dbsync.server_connection').'.database'),
            ] : null,
            'connection' => $connection,
            'interval' => (int) config('dbsync.interval', 2),
            'last_run' => $lastRun,
            'active_run' => $activeRun,
            'last_success_at' => $lastSuccess ? Carbon::parse($lastSuccess)->toIso8601String() : null,
            'last_success_human' => $lastSuccess ? Carbon::parse($lastSuccess)->locale('id')->diffForHumans() : null,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /** @return array{increment:int, offset:int}|null setelan id koneksi aplikasi ini (lihat config/database.php) */
    private function autoIncrement(): ?array
    {
        return rescue(function () {
            $row = DB::selectOne('SELECT @@session.auto_increment_increment AS i, @@session.auto_increment_offset AS o');

            return ['increment' => (int) $row->i, 'offset' => (int) $row->o];
        }, null, false);
    }

    /**
     * Sinkron yang sedang berjalan (jadwal otomatis, tombol dashboard, atau artisan), atau null.
     * Dibaca dari db_sync_runs, jadi terlihat dari container / proses mana pun.
     */
    public function activeRun(): ?array
    {
        $run = rescue(fn () => DB::table('db_sync_runs')
            ->where('status', 'running')
            ->latest('started_at')
            ->latest('id')
            ->first(), null, false);

        if (! $run) {
            return null;
        }

        $startedAt = Carbon::parse($run->started_at);
        $beat = Carbon::parse($run->updated_at ?? $run->started_at);
        $elapsed = max(0, now()->timestamp - $startedAt->timestamp);
        $idle = max(0, now()->timestamp - $beat->timestamp);
        $total = (int) ($run->total_tables ?? 0);
        $done = min((int) ($run->done_tables ?? 0), $total);
        $stallSeconds = max(1, (int) config('dbsync.stall_minutes', 5)) * 60;

        return [
            'id' => (int) $run->id,
            'source' => $run->source ?? 'cli',
            'started_at' => $startedAt->toIso8601String(),
            'elapsed_seconds' => $elapsed,
            'total_tables' => $total,
            'done_tables' => $done,
            'current_table' => $run->current_table ?? null,
            'rows_so_far' => (int) ($run->rows_so_far ?? 0),
            'percent' => $total > 0 ? (int) floor($done / $total * 100) : 0,
            // Perkiraan kasar (ukuran tabel berbeda-beda): rata-rata waktu per tabel yang sudah tuntas.
            'eta_seconds' => $done >= 2 && $total > $done ? (int) round($elapsed / $done * ($total - $done)) : null,
            'cancel_requested' => (bool) ($run->cancel_requested ?? false),
            'idle_seconds' => $idle,
            'idle_minutes' => (int) floor($idle / 60),
            'stalled' => $idle >= $stallSeconds,
            'trackable' => property_exists($run, 'total_tables'),
        ];
    }

    /** Ringkasan sinkron terakhir yang sudah selesai (untuk pesan "selesai" setelah progres hilang). */
    public function lastFinished(): ?array
    {
        $run = $this->latestRun();

        return $run ? [
            'id' => (int) $run->id,
            'status' => $run->status,
            'rows_changed' => (int) $run->rows_changed,
            'error_count' => (int) $run->error_count,
            'conflict_count' => (int) ($run->conflict_count ?? 0),
            'duration_ms' => $run->duration_ms,
            'error' => $run->error,
        ] : null;
    }

    /**
     * Minta sinkron yang sedang berjalan berhenti. Normalnya hanya memasang tanda: proses sinkron
     * berhenti sendiri dengan aman setelah batch yang sedang berjalan selesai. Bila prosesnya
     * sudah mati / macet (atau $force), barisnya langsung ditandai dibatalkan.
     *
     * @return array{ok: bool, message: string, forced?: bool}
     */
    public function requestCancel(bool $force = false): array
    {
        $active = $this->activeRun();

        if (! $active) {
            return ['ok' => false, 'message' => 'Tidak ada sinkron yang sedang berjalan.'];
        }

        if (! $active['trackable']) {
            return ['ok' => false, 'message' => 'Jalankan "php artisan migrate" (di server lokal dan hosting) agar sinkron bisa dibatalkan dari dashboard.'];
        }

        if ($active['stalled'] || $force) {
            // Semua baris "running" yang macet ditutup sekaligus (bisa ada beberapa sisa proses mati yang
            // menumpuk). Paksa = semua baris running, termasuk yang masih terlihat hidup.
            $stallBefore = now()->subMinutes(max(1, (int) config('dbsync.stall_minutes', 5)));
            $rows = DB::table('db_sync_runs')
                ->where('status', 'running')
                ->when(! $force, fn ($q) => $q->where('updated_at', '<', $stallBefore))
                ->get(['id', 'started_at']);

            foreach ($rows as $row) {
                $elapsed = max(0, now()->timestamp - Carbon::parse($row->started_at)->timestamp);
                DB::table('db_sync_runs')->where('id', $row->id)->where('status', 'running')->update([
                    'status' => 'cancelled',
                    'finished_at' => now(),
                    'duration_ms' => (int) min($elapsed * 1000, 4000000000),
                    'error' => $force
                        ? 'Dihentikan paksa dari dashboard.'
                        : 'Proses sinkron tidak merespons; dibatalkan manual dari dashboard.',
                    'updated_at' => now(),
                ]);
            }

            return [
                'ok' => true,
                'forced' => true,
                'message' => $rows->count().' sinkron yang macet ditandai dibatalkan. Anda bisa mengulangi sinkron sekarang.',
            ];
        }

        DB::table('db_sync_runs')->where('id', $active['id'])->update(['cancel_requested' => true]);

        return ['ok' => true, 'message' => 'Permintaan batal dikirim. Sinkron berhenti setelah batch yang sedang berjalan selesai.'];
    }

    public function checkConnection(): array
    {
        $started = microtime(true);

        try {
            $connection = DB::connection(config('dbsync.server_connection'));
            $version = $connection->selectOne('SELECT VERSION() AS v')->v;

            return [
                'ok' => true,
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'version' => $version,
                'error' => null,
            ];
        } catch (Throwable $e) {
            DB::purge(config('dbsync.server_connection'));

            return [
                'ok' => false,
                'latency_ms' => null,
                'version' => null,
                'error' => mb_strimwidth($e->getMessage(), 0, 300, '…'),
            ];
        }
    }

    /**
     * Semua baris konflik dari sinkron terakhir (untuk "selesaikan semua konflik").
     *
     * @return array<int, array{table: string, id: int|string, detail: string}>
     */
    public function conflictRows(): array
    {
        $run = $this->latestRun();

        return $run ? $this->collect($run)[1] : [];
    }

    private function latestRun(): ?object
    {
        return rescue(fn () => DB::table('db_sync_runs')
            ->where('status', '!=', 'running')
            ->latest('started_at')
            ->latest('id')
            ->first(), null, false);
    }

    /** @return array{0: string[], 1: array[]} [error, konflik] dari ringkasan per tabel */
    private function collect(object $run): array
    {
        $errors = [];
        $conflicts = [];
        foreach (json_decode((string) $run->summary, true) ?: [] as $table => $stats) {
            foreach ($stats['errors'] ?? [] as $error) {
                $errors[] = "$table: $error";
            }
            foreach ($stats['conflict_rows'] ?? [] as $row) {
                $conflicts[] = ['table' => $table] + $row;
            }
        }

        return [$errors, $conflicts];
    }

    private function lastRun(): ?array
    {
        $run = $this->latestRun();

        if (! $run) {
            return null;
        }

        [$errors, $conflicts] = $this->collect($run);

        return [
            'id' => (int) $run->id,
            'status' => $run->status,
            'scope' => ($run->scope ?? null) ? json_decode($run->scope, true) : null,
            'started_at' => Carbon::parse($run->started_at)->toIso8601String(),
            'started_human' => Carbon::parse($run->started_at)->locale('id')->diffForHumans(),
            'duration_ms' => $run->duration_ms,
            'rows_changed' => (int) $run->rows_changed,
            'error_count' => (int) $run->error_count,
            'conflict_count' => (int) ($run->conflict_count ?? 0),
            'error' => $run->error,
            'errors' => array_slice($errors, 0, 10),
            'conflicts' => array_slice($conflicts, 0, 50),
        ];
    }
}