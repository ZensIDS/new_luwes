<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Memperbaiki sinkron yang macet pada tabel set_stock_draft_items:
 * "Duplicate entry '72-8232' for key set_stock_draft_items_draft_id_product_id_unique".
 *
 * Penyebab: produk yang sama di-scan di draft yang sama di lokal DAN hosting sebelum sinkron berjalan, sehingga
 * kedua sisi membuat baris (draft_id + produk) yang sama dengan id berbeda. Sinkron tidak bisa memasukkan keduanya.
 * Baris yang kalah dihapus di sisinya sendiri (baris itu belum pernah tersinkron), baris pemenang akan ikut sinkron.
 *
 * Aturan pemenang per pasangan:
 *  - Qty sama                      : 1 inputan, baris lokal dipakai.
 *  - Draft AKTIF, qty beda         : yang updated_at paling baru (keranjang bersifat menimpa); scan_count dijumlah.
 *  - Draft sudah DONE, qty beda    : yang qty-nya cocok dengan yang benar-benar masuk ke stok (dibaca dari Kartu Stok
 *                                    "Set Stock toko (draft #N)"). Bila tidak bisa dipastikan = DILEWATI dan dilaporkan.
 *
 * Jalankan dari PC server lokal (punya koneksi 'server'):
 *   php artisan set-stock:fix-dup-items            (simulasi)
 *   php artisan set-stock:fix-dup-items --apply    (backup kedua database dulu, lalu sinkron lagi)
 */
class FixSetStockDuplicateItems extends Command
{
    protected $signature = 'set-stock:fix-dup-items
        {--apply : Tulis perubahan (default: hanya simulasi)}
        {--force : Lewati konfirmasi}';

    protected $description = 'Selesaikan item keranjang Set Stock yang bentrok (draft + produk sama) antara database lokal dan hosting';

    public function handle(): int
    {
        $local = DB::connection();
        $server = DB::connection(config('dbsync.server_connection', 'server'));

        $key = fn ($r) => $r->draft_id . '|' . $r->product_id;
        $cols = ['id', 'draft_id', 'product_id', 'qty', 'scan_count', 'updated_at'];

        $localItems = $local->table('set_stock_draft_items')->get($cols)->keyBy($key);

        $pairs = [];
        foreach ($server->table('set_stock_draft_items')->get($cols) as $s) {
            $l = $localItems->get($key($s));
            if ($l && (int) $l->id !== (int) $s->id) {
                $pairs[] = [$l, $s];
            }
        }

        if (! $pairs) {
            $this->info('Tidak ada item Set Stock yang bentrok.');

            return self::SUCCESS;
        }

        $draftIds = array_values(array_unique(array_map(fn ($p) => (int) $p[0]->draft_id, $pairs)));
        $draftsLocal = $local->table('set_stock_drafts')->whereIn('id', $draftIds)->get(['id', 'outlet_id', 'status'])->keyBy('id');
        $draftsServer = $server->table('set_stock_drafts')->whereIn('id', $draftIds)->get(['id', 'outlet_id', 'status'])->keyBy('id');

        $plans = [];
        $rows = [];
        foreach ($pairs as [$l, $s]) {
            $dl = $draftsLocal->get($l->draft_id);
            $ds = $draftsServer->get($l->draft_id);
            $outletId = (int) ($dl->outlet_id ?? $ds->outlet_id ?? 0);
            $done = ($dl && $dl->status === 'done') || ($ds && $ds->status === 'done');

            $winner = null; // 'local' | 'server' | null (dilewati)
            $reason = '';
            $applied = null;

            if (abs((float) $l->qty - (float) $s->qty) < 0.0001) {
                $winner = 'local';
                $reason = 'qty sama = 1 inputan';
            } elseif (! $done) {
                $winner = (string) $s->updated_at > (string) $l->updated_at ? 'server' : 'local';
                $reason = 'draft aktif: yang terbaru dipakai';
            } else {
                $applied = $this->appliedQty([$local, $server], $outletId, (int) $l->product_id, (int) $l->draft_id);
                if ($applied === null) {
                    $reason = 'DILEWATI: draft done, tidak ada catatan Kartu Stok untuk memastikan';
                } elseif (abs((float) $l->qty - $applied) < 0.0001) {
                    $winner = 'local';
                    $reason = 'draft done: qty lokal = yang masuk stok';
                } elseif (abs((float) $s->qty - $applied) < 0.0001) {
                    $winner = 'server';
                    $reason = 'draft done: qty hosting = yang masuk stok';
                } else {
                    $reason = 'DILEWATI: draft done, qty yang masuk stok (' . $applied . ') tidak cocok dengan keduanya';
                }
            }

            $rows[] = [
                $l->draft_id, $done ? 'done' : 'aktif', $l->product_id,
                $l->id . ' / ' . (float) $l->qty, $s->id . ' / ' . (float) $s->qty,
                $applied === null ? '-' : $applied,
                ($winner ? 'pakai ' . ($winner === 'local' ? 'lokal' : 'hosting') . ' - ' : '') . $reason,
            ];
            if ($winner) {
                $plans[] = [$l, $s, $winner, $done];
            }
        }

        $this->warn(count($pairs) . ' item bentrok:');
        $this->table(['Draft', 'Status', 'Produk', 'Lokal id / qty', 'Hosting id / qty', 'Qty masuk stok', 'Putusan'], array_slice($rows, 0, 60));
        if (count($rows) > 60) {
            $this->line('... ' . (count($rows) - 60) . ' baris lainnya.');
        }
        $skipped = count($pairs) - count($plans);
        $this->info(count($plans) . ' akan diselesaikan, ' . $skipped . ' dilewati.');

        if (! $this->option('apply')) {
            $this->warn('Simulasi selesai. Backup kedua database, lalu jalankan dengan --apply.');

            return self::SUCCESS;
        }
        if (! $plans) {
            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm('Sudah backup lokal & hosting dan yakin menerapkan?')) {
            return self::FAILURE;
        }

        $n = 0;
        foreach ($plans as [$l, $s, $winner, $done]) {
            [$win, $lose, $winConn, $loseConn] = $winner === 'local'
                ? [$l, $s, $local, $server]
                : [$s, $l, $server, $local];

            // Baris kalah belum pernah tersinkron ke sisi lain, jadi cukup dihapus di sisinya sendiri.
            $loseConn->transaction(function () use ($loseConn, $lose) {
                $loseConn->table('set_stock_draft_items')->where('id', $lose->id)->delete();
            });

            // Draft aktif: jumlahkan scan_count supaya riwayat scan tidak hilang.
            if (! $done) {
                $winConn->table('set_stock_draft_items')->where('id', $win->id)->update([
                    'scan_count' => (int) $win->scan_count + (int) $lose->scan_count,
                ]);
            }
            $n++;
        }

        $this->info($n . ' item bentrok diselesaikan. Jalankan sinkron lagi.');

        return self::SUCCESS;
    }

    /**
     * Qty input yang benar-benar diproses saat draft disimpan, dari Kartu Stok kedua database.
     * Set ulang < 72 jam: dari catatan "ditambahkan +N". Menimpa: saldo tertinggi di antara batch (batch lain dinolkan).
     * null bila tidak ada catatan (mis. qty sudah sama dengan stok sehingga tidak ada perubahan).
     *
     * @param  array<int,ConnectionInterface>  $connections
     */
    private function appliedQty(array $connections, int $outletId, int $productId, int $draftId): ?float
    {
        $base = 'Set Stock toko (draft #' . $draftId . ')';
        $found = false;
        $max = 0.0;

        foreach ($connections as $conn) {
            $movements = $conn->table('stock_movements')
                ->where('owner_id', $outletId)
                ->where('product_id', $productId)
                ->where('type', 'adjustment')
                ->where(fn ($q) => $q->where('notes', $base)->orWhere('notes', 'like', $base . ' -%'))
                ->get(['notes', 'balance']);

            foreach ($movements as $m) {
                $found = true;
                if (preg_match('/ditambahkan \+(\d+)/', (string) $m->notes, $x)) {
                    return (float) $x[1];
                }
                $max = max($max, (float) $m->balance);
            }
        }

        return $found ? $max : null;
    }
}