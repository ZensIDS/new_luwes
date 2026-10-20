<?php

namespace App\Services\DbSync;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Sinkron dua arah seluruh tabel antara database PC server lokal dan database hosting.
 * Dijalankan dari server lokal: php artisan dbsync:run (otomatis via scheduler).
 */
class DbSyncService
{
    public const LOCAL_CONNECTION = 'dbsync_local';

    private const INT_TYPES = ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'];

    public static function isConfigured(): bool
    {
        return filled(config('database.connections.'.config('dbsync.server_connection').'.host'))
            && filled(config('database.connections.'.config('dbsync.server_connection').'.database'));
    }

    /**
     * @param  string[]  $onlyTables
     * @param  callable(string $table, array $stats):void|null  $progress
     */
    public function run(array $onlyTables = [], bool $force = false, bool $dryRun = false, ?callable $progress = null): array
    {
        if (! self::isConfigured()) {
            throw new RuntimeException('DB_SERVER_HOST / DB_SERVER_DATABASE belum diisi di .env.');
        }

        $startedAt = now();
        $started = microtime(true);
        $local = $this->localConnection();
        $server = DB::connection(config('dbsync.server_connection'));
        $runId = $dryRun ? null : $local->table('db_sync_runs')->insertGetId([
            'node' => 'local',
            'status' => 'running',
            'started_at' => $startedAt,
            'created_at' => $startedAt,
            'updated_at' => $startedAt,
        ]);

        $summary = [];
        $fatal = null;
        $lockName = 'dbsync:'.$server->getDatabaseName();
        $locked = false;

        try {
            $locked = (int) $server->selectOne('SELECT GET_LOCK(?, 0) AS l', [$lockName])->l === 1;
            if (! $locked) {
                throw new RuntimeException('Sinkronisasi lain sedang berjalan ke database hosting ini.');
            }

            $this->preflight($local, $server, $dryRun);
            $columns = $this->compareSchema($local, $server, $onlyTables, $summary);
            foreach ($columns as $table => $schema) {
                try {
                    $summary[$table] = $this->synchronizer($local, $server, $table, $schema, $force, $dryRun)->run();
                } catch (Throwable $e) {
                    $summary[$table] = ['failed' => 1, 'errors' => [mb_strimwidth($e->getMessage(), 0, 300, '…')]];
                }

                if ($progress) {
                    $progress($table, $summary[$table]);
                }
            }
        } catch (Throwable $e) {
            $fatal = $e->getMessage();
        } finally {
            if ($locked) {
                rescue(fn () => $server->select('SELECT RELEASE_LOCK(?)', [$lockName]), report: false);
            }
        }

        $summary = array_filter($summary, fn ($s) => $this->changedRows($s) > 0
            || ($s['failed'] ?? 0) > 0 || ($s['conflicts'] ?? 0) > 0 || ! empty($s['errors']));
        $errorCount = array_sum(array_map(fn ($s) => (int) ($s['failed'] ?? 0), $summary));
        $conflictCount = array_sum(array_map(fn ($s) => (int) ($s['conflicts'] ?? 0), $summary));

        $result = [
            'status' => $fatal ? 'failed' : ($errorCount + $conflictCount > 0 ? 'partial' : 'success'),
            'started_at' => $startedAt,
            'finished_at' => now(),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'rows_changed' => array_sum(array_map(fn ($s) => $this->changedRows($s), $summary)),
            'error_count' => $errorCount + ($fatal ? 1 : 0),
            'conflict_count' => $conflictCount,
            'error' => $fatal,
            'tables' => $summary,
        ];

        if (! $dryRun) {
            $this->recordRun($local, $server, $runId, $result);
        }

        return $result;
    }

    /** @return string[] tabel yang ikut disinkron (dari database lokal) */
    public function syncableTables(): array
    {
        return array_values(array_diff(
            array_keys($this->columns($this->localConnection())),
            config('dbsync.exclude_tables', [])
        ));
    }

    /**
     * Selesaikan konflik satu baris: salin versi yang dipilih ke sisi lain.
     *
     * @param  string  $keep  'local' | 'server' | 'newest'
     */
    public function resolveConflict(string $table, int|string $id, string $keep): array
    {
        if (! self::isConfigured()) {
            throw new RuntimeException('DB_SERVER_HOST / DB_SERVER_DATABASE belum diisi di .env.');
        }

        $local = $this->localConnection();
        $server = DB::connection(config('dbsync.server_connection'));
        $summary = [];
        $schema = $this->compareSchema($local, $server, [$table], $summary)[$table] ?? null;

        if (! $schema) {
            throw new RuntimeException("Tabel $table tidak bisa disinkron: ".implode('; ', $summary[$table]['errors'] ?? ['dikecualikan dari sinkron']));
        }

        return $this->synchronizer($local, $server, $table, $schema, true, false)->resolveConflict($id, $keep);
    }

    /** @param  array{0: string[], 1: string[], 2: bool}  $schema */
    private function synchronizer(Connection $local, Connection $server, string $table, array $schema, bool $force, bool $dryRun): TableSynchronizer
    {
        [$names, $generated, $intKey] = $schema;

        return new TableSynchronizer(
            $local,
            $server,
            $table,
            $names,
            $generated,
            $intKey,
            new RowMerger(
                config("dbsync.additive_columns.$table", []),
                config("dbsync.max_columns.$table", []),
                config('dbsync.prefer', 'local'),
            ),
            [
                'chunk_size' => (int) config('dbsync.chunk_size', 1000),
                'batch_size' => (int) config('dbsync.batch_size', 200),
                'delete_guard' => config('dbsync.delete_guard'),
                'conflict' => config('dbsync.conflict', 'skip'),
                'force' => $force,
                'dry_run' => $dryRun,
            ],
        );
    }

    /** Koneksi lokal khusus sinkron: affected rows = baris cocok, tanpa cek foreign key. */
    public function localConnection(): Connection
    {
        $name = self::LOCAL_CONNECTION;

        if (! config("database.connections.$name")) {
            $config = config('database.connections.'.config('dbsync.local_connection'));
            $config['options'] = array_replace($config['options'] ?? [], [
                PDO::MYSQL_ATTR_FOUND_ROWS => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION foreign_key_checks=0',
            ]);
            config(["database.connections.$name" => $config]);
        }

        return DB::connection($name);
    }

    /** @param  bool  $dryRun  dry-run tidak menulis apa pun, jadi cek offset id boleh dilewati */
    private function preflight(Connection $local, Connection $server, bool $dryRun = false): void
    {
        $increment = (int) DB::connection(config('dbsync.local_connection'))
            ->selectOne('SELECT @@session.auto_increment_increment AS i')->i;

        if ($increment < 2 && ! $dryRun) {
            throw new RuntimeException(
                'DB_AUTO_INCREMENT_INCREMENT=2 dan DB_AUTO_INCREMENT_OFFSET belum diset di .env server lokal; '
                .'tanpa itu id baru di lokal & hosting bisa bentrok.'
            );
        }

        $localMigrations = $local->table('migrations')->pluck('migration')->all();
        $serverMigrations = $server->table('migrations')->pluck('migration')->all();
        $missingOnServer = array_diff($localMigrations, $serverMigrations);
        $missingOnLocal = array_diff($serverMigrations, $localMigrations);

        if ($missingOnServer || $missingOnLocal) {
            throw new RuntimeException(sprintf(
                'Struktur database berbeda. Jalankan "php artisan migrate" di %s. Migrasi yang belum ada: %s',
                $missingOnServer ? 'hosting' : 'server lokal',
                implode(', ', array_slice(array_merge($missingOnServer, $missingOnLocal), 0, 5))
            ));
        }
    }

    /**
     * @return array<string, array{0: string[], 1: string[], 2: bool}> tabel => [kolom, kolom generated, id bertipe integer]
     */
    private function compareSchema(Connection $local, Connection $server, array $onlyTables, array &$summary): array
    {
        $localColumns = $this->columns($local);
        $serverColumns = $this->columns($server);
        $excluded = config('dbsync.exclude_tables', []);
        $tables = array_unique(array_merge(array_keys($localColumns), array_keys($serverColumns)));
        sort($tables);

        $result = [];
        foreach ($tables as $table) {
            if (in_array($table, $excluded, true) || ($onlyTables && ! in_array($table, $onlyTables, true))) {
                continue;
            }

            $l = $localColumns[$table] ?? null;
            $s = $serverColumns[$table] ?? null;

            $problem = match (true) {
                $l === null => 'tabel tidak ada di server lokal',
                $s === null => 'tabel tidak ada di hosting',
                array_keys($l) != array_keys($s) => 'kolom berbeda: '.implode(', ', array_merge(
                    array_diff(array_keys($l), array_keys($s)),
                    array_diff(array_keys($s), array_keys($l))
                )),
                ! isset($l['id']) => 'tidak punya kolom id',
                default => null,
            };

            if ($problem) {
                $summary[$table] = ['failed' => 1, 'errors' => [$problem]];

                continue;
            }

            $result[$table] = [
                array_keys($l),
                array_keys(array_filter($l, fn ($c) => $c['generated'])),
                in_array($l['id']['type'], self::INT_TYPES, true),
            ];
        }

        return $result;
    }

    /** @return array<string, array<string, array{type:string, generated:bool}>> tabel => [kolom => info] (kolom terurut) */
    private function columns(Connection $connection): array
    {
        $rows = $connection->select(
            'SELECT c.table_name AS t, c.column_name AS n, c.data_type AS d,
                (c.extra LIKE \'%VIRTUAL%\' OR c.extra LIKE \'%STORED%\' OR c.extra LIKE \'%PERSISTENT%\') AS g
             FROM information_schema.columns c
             JOIN information_schema.tables tb ON tb.table_schema = c.table_schema AND tb.table_name = c.table_name
             WHERE c.table_schema = DATABASE() AND tb.table_type = \'BASE TABLE\'
             ORDER BY c.table_name, c.column_name'
        );

        $tables = [];
        foreach ($rows as $row) {
            $tables[$row->t][$row->n] = ['type' => strtolower($row->d), 'generated' => (bool) $row->g];
        }

        return $tables;
    }

    private function changedRows(array $stats): int
    {
        return array_sum(array_intersect_key($stats, array_flip([
            'pulled', 'pushed', 'merged', 'deleted_local', 'deleted_server',
        ])));
    }

    private function recordRun(Connection $local, Connection $server, ?int $runId, array $result): void
    {
        $record = [
            'node' => 'local',
            'status' => $result['status'],
            'started_at' => $result['started_at'],
            'finished_at' => $result['finished_at'],
            'duration_ms' => $result['duration_ms'],
            'rows_changed' => $result['rows_changed'],
            'error_count' => $result['error_count'],
            'conflict_count' => $result['conflict_count'],
            'summary' => json_encode($result['tables'], JSON_INVALID_UTF8_SUBSTITUTE),
            'error' => $result['error'] ? mb_strimwidth($result['error'], 0, 1000, '…') : null,
            'updated_at' => now(),
        ];
        $cutoff = now()->subDays((int) config('dbsync.keep_runs_days', 14));

        $local->table('db_sync_runs')->where('id', $runId)->update($record);
        $local->table('db_sync_runs')->where('started_at', '<', $cutoff)->delete();

        // Salinan ke hosting supaya dashboard hosting tahu status server lokal.
        rescue(function () use ($server, $record, $cutoff) {
            $server->table('db_sync_runs')->insert($record + ['created_at' => now()]);
            $server->table('db_sync_runs')->where('started_at', '<', $cutoff)->delete();
        }, report: false);
    }
}
