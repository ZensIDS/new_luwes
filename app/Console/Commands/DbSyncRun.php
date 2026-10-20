<?php

namespace App\Console\Commands;

use App\Services\DbSync\DbSyncService;
use Illuminate\Console\Command;

class DbSyncRun extends Command
{
    protected $signature = 'dbsync:run
        {--table=* : Hanya sinkron tabel tertentu}
        {--force : Izinkan penghapusan massal yang ditahan pengaman}
        {--dry-run : Hanya tampilkan rencana perubahan, tanpa menulis apa pun}';

    protected $description = 'Sinkron dua arah database server lokal (offline) dengan database hosting';

    public function handle(DbSyncService $sync): int
    {
        $verbose = $this->output->isVerbose() || $this->option('dry-run');

        $result = $sync->run(
            $this->option('table'),
            (bool) $this->option('force'),
            (bool) $this->option('dry-run'),
            function (string $table, array $stats) use ($verbose) {
                foreach (array_merge($stats['errors'] ?? [], $stats['warnings'] ?? []) as $error) {
                    $this->warn("[$table] $error");
                }
                if ($verbose) {
                    $this->line(sprintf(
                        '%-32s tarik %d | kirim %d | gabung %d | hapus lokal %d | hapus hosting %d | baseline %d | tunda %d | konflik %d',
                        $table,
                        $stats['pulled'] ?? 0, $stats['pushed'] ?? 0, $stats['merged'] ?? 0,
                        $stats['deleted_local'] ?? 0, $stats['deleted_server'] ?? 0,
                        $stats['baseline'] ?? 0, $stats['deferred'] ?? 0, $stats['conflicts'] ?? 0,
                    ));
                }
            }
        );

        if ($result['error']) {
            $this->error('Sinkron gagal: '.$result['error']);

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%sSinkron %s: %d baris berubah, %d konflik dilewati, %d error, %d ms.',
            $this->option('dry-run') ? '[dry-run] ' : '',
            $result['status'],
            $result['rows_changed'],
            $result['conflict_count'],
            $result['error_count'],
            $result['duration_ms'],
        ));

        return $result['status'] === 'success' ? self::SUCCESS : self::FAILURE;
    }
}
