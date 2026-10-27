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

    private ?array $syncableTables = null;

    private ?array $foreignParents = null;

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

        // Sinkron sebagian: tabel induk (foreign key) selalu ikut supaya data terkait tidak tertinggal.
        $onlyTables = $onlyTables ? $this->expandTables($onlyTables) : [];

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
        $synced = [];
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
            $synced = array_keys($columns);
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

        // Data perbedaan konflik disimpan terpisah (db_sync_conflicts), bukan di ringkasan riwayat sinkron.
        $snapshots = [];
        foreach ($summary as $table => $stats) {
            $snapshots[$table] = $stats['conflict_snapshots'] ?? [];
            unset($summary[$table]['conflict_snapshots']);
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
            $this->storeConflicts($server, $synced, $summary, $snapshots);
        }

        return $result;
    }

    /** @return string[] tabel yang ikut disinkron (dari database lokal) */
    public function syncableTables(): array
    {
        return $this->syncableTables ??= array_values(array_diff(
            array_keys($this->columns($this->localConnection())),
            config('dbsync.exclude_tables', [])
        ));
    }

    /**
     * Modul sinkron (config dbsync.groups) + modul "lainnya" untuk tabel yang belum masuk modul.
     *
     * @return array<string, array{label: string, tables: string[]}> tabel sudah termasuk induk foreign key
     */
    public function groups(): array
    {
        $syncable = $this->syncableTables();
        $groups = [];
        $grouped = [];

        foreach (config('dbsync.groups', []) as $key => $group) {
            $tables = array_values(array_intersect($group['tables'], $syncable));
            $grouped = array_merge($grouped, $tables);
            $groups[$key] = ['label' => $group['label'], 'tables' => $tables];
        }

        $others = array_values(array_diff($syncable, $grouped));
        if ($others) {
            $groups['lainnya'] = ['label' => 'Lainnya', 'tables' => $others];
        }

        foreach ($groups as $key => $group) {
            $groups[$key]['tables'] = $this->expandTables($group['tables']);
        }

        return $groups;
    }

    /** @param  string[]  $keys  kunci modul dari groups() */
    public function tablesForGroups(array $keys): array
    {
        $groups = $this->groups();
        $unknown = array_diff($keys, array_keys($groups));

        if ($unknown) {
            throw new RuntimeException('Modul tidak dikenal: '.implode(', ', $unknown));
        }

        return array_values(array_unique(array_merge(...array_map(fn ($k) => $groups[$k]['tables'], $keys))));
    }

    /**
     * Tambahkan semua tabel induk (foreign key, berantai) dari tabel yang dipilih.
     * Contoh: penjualan_items -> penjualans, products, owner_stocks -> outlets, users, categories, ...
     *
     * @param  string[]  $tables
     * @return string[]
     */
    public function expandTables(array $tables): array
    {
        $parents = $this->foreignParents();
        $result = [];
        $queue = array_values($tables);

        while ($queue) {
            $table = array_shift($queue);
            if (isset($result[$table])) {
                continue;
            }
            $result[$table] = true;
            array_push($queue, ...($parents[$table] ?? []));
        }

        $tables = array_values(array_intersect(array_keys($result), $this->syncableTables()));
        sort($tables);

        return $tables;
    }

    /** @return array<string, string[]> tabel => tabel induk (foreign key) */
    private function foreignParents(): array
    {
        return $this->foreignParents ??= collect($this->localConnection()->select(
            'SELECT DISTINCT table_name AS t, referenced_table_name AS p
             FROM information_schema.key_column_usage
             WHERE table_schema = DATABASE() AND referenced_table_name IS NOT NULL AND referenced_table_name <> table_name'
        ))->groupBy('t')->map(fn ($rows) => $rows->pluck('p')->all())->all();
    }

    /**
     * Selesaikan konflik satu baris: salin versi yang dipilih ke sisi lain.
     *
     * @param  string  $keep  'local' | 'server' | 'newest'
     */
    public function resolveConflict(string $table, int|string $id, string $keep): array
    {
        $stats = $this->tableSynchronizer($table)->resolveConflict($id, $keep);

        if (! $stats['failed'] && ! $stats['conflicts'] && ! $stats['deferred']) {
            $this->forgetConflicts([[$table, $id]]);
        }

        return $stats;
    }

    /**
     * Selesaikan banyak konflik sekaligus memakai versi yang sama (lokal / hosting / terbaru).
     *
     * @param  array<int, array{table: string, id: int|string}>  $rows
     * @return array{resolved: string[], failed: array<string, string>} kunci "tabel#id"
     */
    public function resolveConflicts(array $rows, string $keep): array
    {
        $resolved = [];
        $failed = [];

        foreach (collect($rows)->groupBy('table') as $table => $tableRows) {
            try {
                $synchronizer = $this->tableSynchronizer($table);
            } catch (Throwable $e) {
                foreach ($tableRows as $row) {
                    $failed[$table.'#'.$row['id']] = $e->getMessage();
                }

                continue;
            }

            foreach ($tableRows->pluck('id')->unique() as $id) {
                $key = $table.'#'.$id;
                $before = $synchronizer->stats();
                $after = $synchronizer->resolveConflict($id, $keep);

                if ($after['failed'] > $before['failed'] || $after['conflicts'] > $before['conflicts'] || $after['deferred'] > $before['deferred']) {
                    $failed[$key] = end($after['errors']) ?: (end($after['warnings']) ?: 'Baris sedang diubah saat ini, coba lagi.');
                } else {
                    $resolved[] = $key;
                }
            }
        }

        $this->forgetConflicts(array_map(fn ($key) => explode('#', $key, 2), $resolved));

        return ['resolved' => $resolved, 'failed' => $failed];
    }

    /**
     * Data perbedaan konflik yang disimpan sinkron terakhir (db_sync_conflicts). Dipakai di hosting,
     * yang tidak bisa membaca database lokal secara langsung.
     */
    public function storedConflict(string $table, int|string $id): ?array
    {
        $row = DB::table('db_sync_conflicts')
            ->where('table_name', $table)
            ->where('row_id', (string) $id)
            ->first();

        $snapshot = $row ? json_decode((string) $row->snapshot, true) : null;

        return $snapshot ? $snapshot + ['detected_at' => $row->detected_at] : null;
    }

    public function tableSynchronizer(string $table): TableSynchronizer
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

        return $this->synchronizer($local, $server, $table, $schema, true, false);
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

            $onlyLocal = $l && $s ? array_diff(array_keys($l), array_keys($s)) : [];
            $onlyServer = $l && $s ? array_diff(array_keys($s), array_keys($l)) : [];

            $problem = match (true) {
                $l === null => 'tabel tidak ada di server lokal (jalankan migrasi di lokal: make migrate)',
                $s === null => 'tabel tidak ada di hosting (jalankan php artisan migrate --force di hosting)',
                $onlyLocal || $onlyServer => 'struktur kolom berbeda'
                    .($onlyLocal ? '; hanya ada di lokal: '.implode(', ', $onlyLocal) : '')
                    .($onlyServer ? '; hanya ada di hosting: '.implode(', ', $onlyServer) : '')
                    .'. Samakan versi aplikasi & migrasi lokal dan hosting.',
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

        // Urutkan di PHP: MySQL 8 dan MariaDB mengurutkan "_" berbeda (collation information_schema),
        // jadi urutan ORDER BY tidak bisa dibandingkan antar server.
        foreach ($tables as &$columns) {
            ksort($columns, SORT_STRING);
        }

        return $tables;
    }

    private function changedRows(array $stats): int
    {
        return array_sum(array_intersect_key($stats, array_flip([
            'pulled', 'pushed', 'merged', 'deleted_local', 'deleted_server',
        ])));
    }

    /**
     * Ganti salinan konflik di hosting untuk tabel yang ikut sinkron kali ini.
     *
     * @param  string[]  $tables
     * @param  array<string, array<string, array|null>>  $snapshots  tabel => id => data inspect
     */
    private function storeConflicts(Connection $server, array $tables, array $summary, array $snapshots): void
    {
        if (! $tables) {
            return;
        }

        $now = now();
        $records = [];
        foreach ($tables as $table) {
            foreach ($summary[$table]['conflict_rows'] ?? [] as $row) {
                $snapshot = $snapshots[$table][(string) $row['id']] ?? null;
                if ($snapshot) {
                    $records[] = [
                        'table_name' => $table,
                        'row_id' => (string) $row['id'],
                        'detail' => $row['detail'],
                        'snapshot' => json_encode($snapshot, JSON_INVALID_UTF8_SUBSTITUTE),
                        'detected_at' => $now,
                    ];
                }
            }
        }

        rescue(function () use ($server, $tables, $records) {
            $server->transaction(function () use ($server, $tables, $records) {
                $server->table('db_sync_conflicts')->whereIn('table_name', $tables)->delete();
                foreach (array_chunk($records, 50) as $chunk) {
                    $server->table('db_sync_conflicts')->insert($chunk);
                }
            });
        }, report: false);
    }

    /** @param  array<int, array{0: string, 1: int|string}>  $rows  [tabel, id] yang konfliknya sudah diselesaikan */
    private function forgetConflicts(array $rows): void
    {
        if (! $rows) {
            return;
        }

        rescue(function () use ($rows) {
            $query = DB::connection(config('dbsync.server_connection'))->table('db_sync_conflicts');
            foreach ($rows as [$table, $id]) {
                $query->orWhere(fn ($q) => $q->where('table_name', $table)->where('row_id', (string) $id));
            }
            $query->delete();
        }, report: false);
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
