<?php

namespace App\Console\Commands;

use App\Services\DbSync\DbSyncService;
use Illuminate\Console\Command;

class DbSyncResolve extends Command
{
    protected $signature = 'dbsync:resolve
        {table : Nama tabel, misal products}
        {id : id baris yang konflik}
        {--keep=newest : Versi yang dipakai: local, server, atau newest (updated_at terbaru)}';

    protected $description = 'Selesaikan konflik sinkron satu baris dengan memilih versi lokal atau hosting';

    public function handle(DbSyncService $sync): int
    {
        $keep = $this->option('keep');
        if (! in_array($keep, ['local', 'server', 'newest'], true)) {
            $this->error('--keep harus local, server, atau newest.');

            return self::INVALID;
        }

        $stats = $sync->resolveConflict($this->argument('table'), $this->argument('id'), $keep);

        foreach (array_merge($stats['errors'], $stats['warnings']) as $message) {
            $this->warn($message);
        }

        if ($stats['failed'] || $stats['conflicts'] || $stats['deferred']) {
            $this->error('Konflik belum terselesaikan'.($stats['deferred'] ? ' (baris sedang diubah, coba lagi).' : '.'));

            return self::FAILURE;
        }

        $this->info("Baris {$this->argument('table')} #{$this->argument('id')} disamakan memakai versi: $keep.");

        return self::SUCCESS;
    }
}
