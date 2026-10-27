<?php

namespace App\Services\DbSync;

use Illuminate\Database\Connection;
use Throwable;

/**
 * Sinkron dua arah satu tabel antara database lokal dan hosting.
 *
 * Cara kerja:
 *  1. Baris dikelompokkan per blok id (chunk_size). Untuk tiap blok dihitung checksum
 *     di lokal, di hosting, dan di snapshot sinkron terakhir (db_sync_rows).
 *     Blok yang checksum lokal & hostingnya sama dengan snapshot dilewati.
 *  2. Blok yang berubah dibandingkan per baris (hash) terhadap snapshot untuk tahu
 *     sisi mana yang menambah, mengubah, atau menghapus baris.
 *  3. Perubahan diterapkan dengan update bersyarat (WHERE hash = hash saat dibaca),
 *     jadi baris yang sedang diubah kasir saat sinkron tidak tertimpa; baris itu
 *     diproses lagi pada sinkron berikutnya.
 *
 * Hash dihitung oleh masing-masing server dan disimpan per sisi, sehingga perbedaan
 * representasi MySQL/MariaDB (float, JSON) tidak dianggap sebagai perubahan.
 */
class TableSynchronizer
{
    private string $hashExpr;

    private string $wrappedTable;

    private array $stats = [
        'pulled' => 0,
        'pushed' => 0,
        'merged' => 0,
        'deleted_local' => 0,
        'deleted_server' => 0,
        'baseline' => 0,
        'deferred' => 0,
        'failed' => 0,
        'conflicts' => 0,
        'errors' => [],
        'warnings' => [],
        'conflict_rows' => [],
        // id => data inspect() saat konflik terdeteksi (disimpan ke hosting, lihat DbSyncService::storeConflicts).
        'conflict_snapshots' => [],
    ];

    /**
     * @param  string[]  $columns
     * @param  string[]  $generated  kolom generated (dihitung MySQL, tidak boleh ditulis)
     * @param  array{chunk_size:int,batch_size:int,delete_guard:array,force:bool,dry_run:bool,conflict:string}  $options
     */
    public function __construct(
        private Connection $local,
        private Connection $server,
        private string $table,
        array $columns,
        private array $generated,
        private bool $intKey,
        private RowMerger $merger,
        private array $options,
    ) {
        sort($columns);
        $parts = array_map(
            fn ($c) => 'IFNULL(CAST('.self::wrap($c).' AS CHAR), \'<~null~>\')',
            $columns
        );
        $this->hashExpr = 'MD5(CONCAT_WS(\'|\', '.implode(', ', $parts).'))';
        $this->wrappedTable = self::wrap($table);
    }

    public function run(): array
    {
        $plan = $this->plan();

        if ($this->options['dry_run']) {
            return $this->planSummary($plan);
        }

        if ($plan['forget']) {
            $this->baseQuery()->whereIn('row_id', array_map('strval', $plan['forget']))->delete();
        }

        foreach (array_chunk($plan['pull_new'], $this->options['batch_size'], true) as $batch) {
            $this->pullNew($batch);
        }

        foreach (array_chunk($plan['push_new'], $this->options['batch_size'], true) as $batch) {
            $this->pushNew($batch);
        }

        foreach (array_chunk($plan['compare'], $this->options['batch_size'], true) as $batch) {
            $this->compare($batch);
        }

        foreach ($plan['rows'] as $id => [$action, $localHash, $serverHash]) {
            $this->syncRow($id, $action, $localHash, $serverHash);
        }

        return $this->stats;
    }

    /* ------------------------------------------------------------------ plan */

    private function plan(): array
    {
        $plan = ['pull_new' => [], 'push_new' => [], 'compare' => [], 'rows' => [], 'forget' => []];

        $localSums = $this->bucketSums($this->local);
        $serverSums = $this->bucketSums($this->server);
        $baseSums = $this->baseSums();

        $buckets = array_unique(array_merge(array_keys($localSums), array_keys($serverSums), array_keys($baseSums)));

        foreach ($buckets as $bucket) {
            $unchanged = ($localSums[$bucket] ?? '0:0') === ($baseSums[$bucket]['l'] ?? '0:0')
                && ($serverSums[$bucket] ?? '0:0') === ($baseSums[$bucket]['s'] ?? '0:0');

            if ($unchanged) {
                continue;
            }

            $local = $this->bucketHashes($this->local, $bucket);
            $server = $this->bucketHashes($this->server, $bucket);
            $base = $this->baseHashes($bucket);

            foreach (array_keys($local + $server + $base) as $id) {
                $l = $local[$id] ?? null;
                $s = $server[$id] ?? null;
                $b = $base[$id] ?? null;

                if ($b === null) {
                    match (true) {
                        $l !== null && $s !== null => $plan['compare'][$id] = [$l, $s],
                        $l !== null => $plan['push_new'][$id] = $l,
                        default => $plan['pull_new'][$id] = $s,
                    };

                    continue;
                }

                $localState = $l === null ? 'deleted' : ($l === $b['l'] ? 'same' : 'changed');
                $serverState = $s === null ? 'deleted' : ($s === $b['s'] ? 'same' : 'changed');

                $action = match ("$localState/$serverState") {
                    'same/same' => null,
                    'deleted/deleted' => 'forget',
                    'changed/same', 'changed/deleted' => 'push',
                    'deleted/same' => 'push_delete',
                    'same/changed', 'deleted/changed' => 'pull',
                    'same/deleted' => 'pull_delete',
                    'changed/changed' => 'merge',
                };

                if ($action === 'forget') {
                    $plan['forget'][] = $id;
                } elseif ($action !== null) {
                    $plan['rows'][$id] = [$action, $l, $s];
                }
            }
        }

        $this->guardMassDelete($plan, 'push_delete', array_sum(array_map('intval', $serverSums)));
        $this->guardMassDelete($plan, 'pull_delete', array_sum(array_map('intval', $localSums)));

        return $plan;
    }

    /** Tahan penghapusan massal (misal salah satu database terhapus/kosong) kecuali --force. */
    private function guardMassDelete(array &$plan, string $action, int $targetRows): void
    {
        $ids = array_keys(array_filter($plan['rows'], fn ($row) => $row[0] === $action));
        $limit = max($this->options['delete_guard']['min_rows'], $this->options['delete_guard']['ratio'] * $targetRows);

        if (count($ids) <= $limit || $this->options['force']) {
            return;
        }

        foreach ($ids as $id) {
            unset($plan['rows'][$id]);
        }

        $where = $action === 'push_delete' ? 'hosting' : 'lokal';
        $this->stats['failed']++;
        $this->stats['errors'][] = sprintf(
            'Penghapusan %d baris di %s ditahan (melebihi batas aman). Periksa datanya, lalu jalankan "php artisan dbsync:run --table=%s --force" bila memang disengaja.',
            count($ids), $where, $this->table
        );
    }

    private function planSummary(array $plan): array
    {
        $count = fn ($action) => count(array_filter($plan['rows'], fn ($row) => $row[0] === $action));

        $this->stats['pulled'] = count($plan['pull_new']) + $count('pull');
        $this->stats['pushed'] = count($plan['push_new']) + $count('push');
        $this->stats['merged'] = $count('merge');
        $this->stats['deleted_local'] = $count('pull_delete');
        $this->stats['deleted_server'] = $count('push_delete');
        $this->stats['baseline'] = count($plan['compare']);

        return $this->stats;
    }

    private function bucketExpr(): string
    {
        return $this->intKey
            ? 'FLOOR(`id` / '.(int) $this->options['chunk_size'].')'
            : '0';
    }

    /** @return array<int, string> bucket => "jumlah:xor-hash" */
    private function bucketSums(Connection $connection): array
    {
        $rows = $connection->select(
            "SELECT {$this->bucketExpr()} AS b, COUNT(*) AS c,
                CAST(BIT_XOR(CAST(CONV(SUBSTRING({$this->hashExpr}, 1, 16), 16, 10) AS UNSIGNED)) AS CHAR) AS x
             FROM {$this->wrappedTable} GROUP BY b"
        );

        $sums = [];
        foreach ($rows as $row) {
            $sums[(int) $row->b] = $row->c.':'.$row->x;
        }

        return $sums;
    }

    /** @return array<int, array{l:string, s:string}> */
    private function baseSums(): array
    {
        $rows = $this->local->select(
            'SELECT bucket AS b, COUNT(*) AS c,
                CAST(BIT_XOR(CAST(CONV(SUBSTRING(local_hash, 1, 16), 16, 10) AS UNSIGNED)) AS CHAR) AS lx,
                CAST(BIT_XOR(CAST(CONV(SUBSTRING(server_hash, 1, 16), 16, 10) AS UNSIGNED)) AS CHAR) AS sx
             FROM db_sync_rows WHERE table_name = ? GROUP BY bucket',
            [$this->table]
        );

        $sums = [];
        foreach ($rows as $row) {
            $sums[(int) $row->b] = ['l' => $row->c.':'.$row->lx, 's' => $row->c.':'.$row->sx];
        }

        return $sums;
    }

    /** @return array<int|string, string> id => hash */
    private function bucketHashes(Connection $connection, int $bucket): array
    {
        $query = $connection->table($this->table)->selectRaw("`id` AS k, {$this->hashExpr} AS h");

        if ($this->intKey) {
            $size = (int) $this->options['chunk_size'];
            $query->where('id', '>=', $bucket * $size)->where('id', '<', ($bucket + 1) * $size);
        }

        return $query->pluck('h', 'k')->all();
    }

    /** @return array<int|string, array{l:string, s:string}> */
    private function baseHashes(int $bucket): array
    {
        return $this->baseQuery()
            ->where('bucket', $bucket)
            ->get(['row_id', 'local_hash', 'server_hash'])
            ->mapWithKeys(fn ($row) => [$row->row_id => ['l' => $row->local_hash, 's' => $row->server_hash]])
            ->all();
    }

    /* --------------------------------------------------------------- execute */

    /** Baris baru di hosting -> tambahkan ke lokal. */
    private function pullNew(array $batch): void
    {
        $rows = $this->fetchRows($this->server, array_keys($batch));
        if (! $rows) {
            return;
        }

        try {
            $this->local->transaction(function () use ($rows) {
                $this->local->table($this->table)->insert(array_values(array_map(fn ($row) => $this->strip($row), $rows)));
                $this->saveBase($this->fetchRows($this->local, array_keys($rows)), array_column($rows, '__h', 'id'));
            });
            $this->stats['pulled'] += count($rows);
        } catch (Throwable $e) {
            $this->retryOneByOne($batch, $e, fn ($single) => $this->pullNew($single));
        }
    }

    /** Baris baru di lokal -> tambahkan ke hosting. */
    private function pushNew(array $batch): void
    {
        $rows = $this->fetchRows($this->local, array_keys($batch));
        if (! $rows) {
            return;
        }

        try {
            $serverHashes = $this->server->transaction(function () use ($rows) {
                $this->server->table($this->table)->insert(array_values(array_map(fn ($row) => $this->strip($row), $rows)));

                return array_column($this->fetchRows($this->server, array_keys($rows)), '__h', 'id');
            });
            $this->saveBase($rows, $serverHashes);
            $this->stats['pushed'] += count($rows);
        } catch (Throwable $e) {
            $this->retryOneByOne($batch, $e, fn ($single) => $this->pushNew($single));
        }
    }

    /** Baris ada di dua sisi tanpa snapshot (sinkron pertama): samakan atau selesaikan konflik. */
    private function compare(array $batch): void
    {
        $local = $this->fetchRows($this->local, array_keys($batch));
        $server = $this->fetchRows($this->server, array_keys($batch));

        foreach ($batch as $id => [$localHash, $serverHash]) {
            $l = $local[$id] ?? null;
            $s = $server[$id] ?? null;

            if ($l && $s && RowMerger::sameRow($this->strip($l), $this->strip($s))) {
                $this->saveBase([$id => $l], [$id => $s['__h']]);
                $this->stats['baseline']++;
            } else {
                $this->syncRow($id, 'resolve', $l['__h'] ?? null, $s['__h'] ?? null);
            }
        }
    }

    private function retryOneByOne(array $batch, Throwable $e, callable $retry): void
    {
        if (count($batch) === 1) {
            $this->error(array_key_first($batch), $e);

            return;
        }

        foreach ($batch as $id => $hash) {
            $retry([$id => $hash]);
        }
    }

    /**
     * Terapkan satu aksi pada satu baris. Baris lokal dikunci selama hosting ditulis,
     * dan hosting ditulis dengan syarat hash-nya belum berubah sejak direncanakan.
     */
    private function syncRow(int|string $id, string $action, ?string $localHash, ?string $serverHash): void
    {
        try {
            $this->local->transaction(function () use ($id, $action, $localHash, $serverHash) {
                $l = $this->fetchRows($this->local, [$id], lock: true)[$id] ?? null;
                $s = $this->fetchRows($this->server, [$id])[$id] ?? null;

                if (($l['__h'] ?? null) !== $localHash || ($s['__h'] ?? null) !== $serverHash) {
                    throw new SyncDeferred;
                }

                [$toLocal, $toServer] = $this->resolve($id, $action, $l, $s);

                $newServerHash = $toServer === false
                    ? $serverHash
                    : $this->server->transaction(fn () => $this->write($this->server, $id, $s, $toServer));

                if ($toLocal !== false) {
                    $this->write($this->local, $id, $l, $toLocal);
                }

                $after = $this->fetchRows($this->local, [$id])[$id] ?? null;

                if ($after !== null && $newServerHash !== null) {
                    $this->saveBase([$id => $after], [$id => $newServerHash]);
                } else {
                    $this->baseQuery()->where('row_id', (string) $id)->delete();
                }
            });

            $this->stats[match ($action) {
                'push' => 'pushed',
                'pull' => 'pulled',
                'push_delete' => 'deleted_server',
                'pull_delete' => 'deleted_local',
                default => 'merged',
            }]++;
        } catch (SyncDeferred) {
            $this->stats['deferred']++;
        } catch (SyncConflict $e) {
            $this->stats['conflicts']++;
            if (count($this->stats['conflict_rows']) < 50) {
                $this->stats['conflict_rows'][] = ['id' => $id, 'detail' => $e->getMessage()];
                $this->stats['conflict_snapshots'][(string) $id] = rescue(fn () => $this->snapshot($id), null, false);
            }
            if (count($this->stats['warnings']) < 20) {
                $this->stats['warnings'][] = sprintf(
                    'KONFLIK id %s dilewati (%s). Selesaikan: php artisan dbsync:resolve %s %s --keep=local|server|newest',
                    $id, $e->getMessage(), $this->table, $id
                );
            }
        } catch (Throwable $e) {
            $this->error($id, $e);
        }
    }

    /**
     * @return array{0: array|null|false, 1: array|null|false} [target lokal, target hosting]
     *                                                         false = tidak diubah, null = hapus
     */
    private function resolve(int|string $id, string $action, ?array $l, ?array $s): array
    {
        $local = $l ? $this->strip($l) : null;
        $server = $s ? $this->strip($s) : null;

        switch ($action) {
            case 'push':
                return [false, $local];
            case 'push_delete':
                return [false, null];
            case 'pull':
                return [$server, false];
            case 'pull_delete':
                return [null, false];
            case 'merge':
                $base = json_decode((string) $this->baseQuery()->where('row_id', (string) $id)->value('data'), true) ?: null;
                $this->guardConflict($local, $server, $base);
                $merged = $this->merger->merge($local, $server, $base);

                return [
                    RowMerger::sameRow($merged, $local) ? false : $merged,
                    RowMerger::sameRow($merged, $server) ? false : $merged,
                ];
        }

        // 'resolve': id sama di dua sisi tanpa riwayat sinkron.
        if ($local === null || $server === null) {
            throw new SyncDeferred;
        }

        // created_at beda DAN ada kolom data lain yang beda -> kemungkinan dua baris berbeda memakai id sama.
        // Bila yang beda hanya created_at/updated_at, itu baris yang sama: menang yang terbaru.
        $dataDiffers = ! RowMerger::sameRow(
            array_diff_key($local, array_flip(RowMerger::TIMESTAMPS)),
            array_diff_key($server, array_flip(RowMerger::TIMESTAMPS))
        );

        if ($dataDiffers && array_key_exists('created_at', $local) && ! RowMerger::same($local['created_at'], $server['created_at'] ?? null)) {
            throw new SyncConflict(
                'data berbeda di lokal & hosting (created_at berbeda) — kemungkinan id bentrok. '
                .'Pastikan DB_AUTO_INCREMENT_OFFSET lokal & hosting berbeda.'
            );
        }

        $this->guardConflict($local, $server, null);

        return $this->merger->newerWins($local, $server) === 'local'
            ? [false, $local]
            : [$server, false];
    }

    /** Mode 'skip' (default): kolom yang bentrok membuat baris dilewati dan dilaporkan sebagai konflik. */
    private function guardConflict(array $local, array $server, ?array $base): void
    {
        if ($this->options['conflict'] !== 'skip') {
            return;
        }

        $columns = $this->merger->conflicts($local, $server, $base);
        if (! $columns) {
            return;
        }

        $show = fn ($v) => $v === null ? 'NULL' : '"'.mb_strimwidth((string) $v, 0, 40, '…').'"';
        throw new SyncConflict(implode('; ', array_map(
            fn ($c) => "$c: lokal {$show($local[$c] ?? null)} vs hosting {$show($server[$c] ?? null)}",
            array_slice($columns, 0, 4)
        )));
    }

    /**
     * Data satu baris di lokal, hosting, dan saat sinkron terakhir, beserta status tiap kolom
     * (untuk menampilkan perbedaan konflik di dashboard).
     */
    public function inspect(int|string $id): array
    {
        $l = $this->fetchRows($this->local, [$id])[$id] ?? null;
        $s = $this->fetchRows($this->server, [$id])[$id] ?? null;
        $local = $l ? $this->strip($l) : null;
        $server = $s ? $this->strip($s) : null;
        $base = json_decode((string) $this->baseQuery()->where('row_id', (string) $id)->value('data'), true) ?: null;

        $states = $local && $server
            ? $this->merger->columnStates($local, $server, $base)
            : array_fill_keys(array_keys($local ?? $server ?? []), $local ? 'local' : 'server');

        return [
            'table' => $this->table,
            'id' => $id,
            'local' => $local,
            'server' => $server,
            'base' => $base,
            'states' => $states,
            'newest' => $local && $server ? $this->merger->newerWins($local, $server) : ($local ? 'local' : 'server'),
        ];
    }

    /** inspect() dengan nilai panjang (mis. JSON activity_log) dipotong, untuk disimpan / ditampilkan di modal. */
    public function snapshot(int|string $id): array
    {
        $row = $this->inspect($id);
        foreach (['local', 'server', 'base'] as $side) {
            if ($row[$side] !== null) {
                $row[$side] = array_map(fn ($v) => is_string($v) ? mb_strimwidth($v, 0, 2000, '…') : $v, $row[$side]);
            }
        }

        return $row;
    }

    /**
     * Selesaikan konflik satu baris secara manual: salin versi yang dipilih ke sisi lain.
     *
     * @param  string  $keep  'local' | 'server' | 'newest'
     */
    public function resolveConflict(int|string $id, string $keep): array
    {
        $l = $this->fetchRows($this->local, [$id])[$id] ?? null;
        $s = $this->fetchRows($this->server, [$id])[$id] ?? null;

        if ($keep === 'newest') {
            $keep = $l && $s ? $this->merger->newerWins($this->strip($l), $this->strip($s)) : ($l ? 'local' : 'server');
        }

        $action = $keep === 'local'
            ? ($l ? 'push' : 'push_delete')
            : ($s ? 'pull' : 'pull_delete');

        $this->syncRow($id, $action, $l['__h'] ?? null, $s['__h'] ?? null);

        return $this->stats;
    }

    /** Statistik kumulatif instance ini (untuk menyelesaikan banyak konflik berurutan). */
    public function stats(): array
    {
        return $this->stats;
    }

    /**
     * Tulis target ke satu sisi dengan syarat baris belum berubah sejak dibaca.
     *
     * @return string|null hash baris setelah ditulis (null bila dihapus)
     */
    private function write(Connection $connection, int|string $id, ?array $current, ?array $target): ?string
    {
        $query = fn () => $connection->table($this->table)->where('id', $id);

        if ($target === null) {
            if ($current !== null && $query()->whereRaw("{$this->hashExpr} = ?", [$current['__h']])->delete() === 0) {
                throw new SyncDeferred;
            }

            return null;
        }

        if ($current === null) {
            $connection->table($this->table)->insert($target);
        } elseif ($query()->whereRaw("{$this->hashExpr} = ?", [$current['__h']])->update(array_diff_key($target, ['id' => true])) === 0) {
            throw new SyncDeferred;
        }

        return $query()->selectRaw("{$this->hashExpr} AS h")->value('h');
    }

    /* --------------------------------------------------------------- helpers */

    /** @return array<int|string, array> id => kolom + '__h' */
    private function fetchRows(Connection $connection, array $ids, bool $lock = false): array
    {
        $query = $connection->table($this->table)
            ->select('*')
            ->selectRaw("{$this->hashExpr} AS __h")
            ->whereIn('id', $ids);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get()->mapWithKeys(fn ($row) => [$row->id => (array) $row])->all();
    }

    /**
     * @param  array<int|string, array>  $localRows  baris lokal (dengan __h)
     * @param  array<int|string, string>  $serverHashes
     */
    private function saveBase(array $localRows, array $serverHashes): void
    {
        $now = now();
        $records = [];

        foreach ($localRows as $id => $row) {
            if (! isset($serverHashes[$id])) {
                continue;
            }

            $records[] = [
                'table_name' => $this->table,
                'row_id' => (string) $id,
                'bucket' => $this->intKey ? intdiv((int) $id, (int) $this->options['chunk_size']) : 0,
                'local_hash' => $row['__h'],
                'server_hash' => $serverHashes[$id],
                'data' => json_encode($this->strip($row), JSON_INVALID_UTF8_SUBSTITUTE),
                'synced_at' => $now,
            ];
        }

        if ($records) {
            $this->local->table('db_sync_rows')->upsert(
                $records,
                ['table_name', 'row_id'],
                ['bucket', 'local_hash', 'server_hash', 'data', 'synced_at']
            );
        }
    }

    private function baseQuery()
    {
        return $this->local->table('db_sync_rows')->where('table_name', $this->table);
    }

    private function error(int|string $id, Throwable $e): void
    {
        $this->stats['failed']++;

        if (count($this->stats['errors']) < 20) {
            $this->stats['errors'][] = sprintf('id %s: %s', $id, mb_strimwidth($e->getMessage(), 0, 300, '…'));
        }
    }

    /** Nilai kolom yang bisa ditulis: tanpa hash dan tanpa kolom generated. */
    private function strip(array $row): array
    {
        unset($row['__h']);

        return array_diff_key($row, array_flip($this->generated));
    }

    private static function wrap(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }
}
