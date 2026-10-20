<?php

namespace App\Console\Commands;

use App\Services\DbSync\DbSyncStatus;
use Illuminate\Console\Command;

class DbSyncStatusCommand extends Command
{
    protected $signature = 'dbsync:status';

    protected $description = 'Cek koneksi ke database hosting dan status sinkron terakhir';

    public function handle(DbSyncStatus $status): int
    {
        $s = $status->snapshot();

        $this->line('Node            : '.($s['node'] === 'local' ? 'Server lokal (offline)' : 'Hosting'));
        $this->line('Kesehatan       : '.strtoupper($s['health']).' — '.$s['message']);

        if ($s['server']) {
            $this->line("DB hosting      : {$s['server']['database']} @ {$s['server']['host']}");
        }

        if ($s['connection']) {
            $this->line('Koneksi         : '.($s['connection']['ok']
                ? "OK ({$s['connection']['latency_ms']} ms, {$s['connection']['version']})"
                : 'GAGAL — '.$s['connection']['error']));
        }

        if ($s['last_run']) {
            $run = $s['last_run'];
            $this->line("Sinkron terakhir: {$run['status']}, {$run['started_human']}, {$run['rows_changed']} baris, {$run['conflict_count']} konflik, {$run['error_count']} error");
            if ($run['error']) {
                $this->warn('  '.$run['error']);
            }
            foreach ($run['errors'] as $error) {
                $this->warn('  '.$error);
            }
        } else {
            $this->line('Sinkron terakhir: belum pernah');
        }

        return $s['health'] === 'error' ? self::FAILURE : self::SUCCESS;
    }
}
