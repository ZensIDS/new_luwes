<?php

namespace App\Console\Commands;

use App\Models\OwnerStock;
use App\Services\OutletStockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Memperbaiki sinkron yang macet: "Duplicate entry 'OUTLET-PRODUK-SET-YYYYMMDD-PRODUK' for key owner_stocks_owner_product_batch_unique".
 *
 * Penyebab: Set Stock produk yang sama di toko yang sama dijalankan di lokal DAN hosting pada hari yang sama, sehingga
 * kedua sisi membuat batch bernomor sama (SET-tanggal-idproduk) dengan id berbeda. Dua baris itu tidak bisa digabung
 * oleh sinkron, dan stoknya akan terhitung dobel bila keduanya dibiarkan.
 *
 * Jalankan dari PC server lokal (punya koneksi 'server').
 *
 *   php artisan set-stock:fix-dup-batches                  (simulasi: tampilkan pasangan bentrok)
 *   php artisan set-stock:fix-dup-batches --keep=local --apply   (batch hosting dinolkan + diganti nomor, lokal dipakai)
 *   php artisan set-stock:fix-dup-batches --keep=server --apply  (batch lokal dinolkan + diganti nomor, hosting dipakai)
 *   php artisan set-stock:fix-dup-batches --keep=both --apply    (hanya ganti nomor batch lokal; stok KEDUANYA tetap = dobel)
 *   php artisan set-stock:fix-dup-batches --keep=auto --apply    (putuskan per pasangan, lihat di bawah)
 *
 * --keep=auto: qty & HPP kedua sisi SAMA = dianggap 1 inputan yang sama (batch hosting dinolkan, lokal dipakai).
 *              qty / HPP BEDA = dianggap 2 inputan berbeda (nomor batch lokal diganti, stok KEDUANYA dipertahankan
 *              = menumpuk/menambah, sesuai aturan Set Stock < 72 jam). Simulasi menampilkan kolom "Putusan auto".
 *
 * Backup kedua database dulu. Setelah selesai, jalankan sinkron lagi.
 */
class FixSetStockDuplicateBatches extends Command
{
    protected $signature = 'set-stock:fix-dup-batches
        {--keep= : local | server | both | auto (wajib bersama --apply)}
        {--apply : Tulis perubahan (default: hanya simulasi)}
        {--force : Lewati konfirmasi}';

    protected $description = 'Selesaikan batch Set Stock yang bentrok antara database lokal dan hosting';

    public function handle(OutletStockService $stockService): int
    {
        $apply = (bool) $this->option('apply');
        $keep = (string) $this->option('keep');
        if ($apply && ! in_array($keep, ['local', 'server', 'both', 'auto'], true)) {
            $this->error('Pilih --keep=local, --keep=server, --keep=both atau --keep=auto.');

            return self::FAILURE;
        }

        $server = DB::connection(config('dbsync.server_connection', 'server'));

        $local = DB::table('owner_stocks')->where('batch_number', 'like', 'SET-%')
            ->get(['id', 'owner_id', 'product_id', 'batch_number', 'qty', 'hpp', 'created_at'])
            ->keyBy(fn ($r) => $r->owner_id . '|' . $r->product_id . '|' . $r->batch_number);

        $conflicts = [];
        foreach ($server->table('owner_stocks')->where('batch_number', 'like', 'SET-%')
            ->get(['id', 'owner_id', 'product_id', 'batch_number', 'qty', 'hpp', 'created_at']) as $s) {
            $l = $local->get($s->owner_id . '|' . $s->product_id . '|' . $s->batch_number);
            if ($l && (int) $l->id !== (int) $s->id) {
                $conflicts[] = [$l, $s];
            }
        }

        if (! $conflicts) {
            $this->info('Tidak ada batch Set Stock yang bentrok.');

            return self::SUCCESS;
        }

        $this->warn(count($conflicts) . ' batch bentrok (nomor batch sama, id berbeda di lokal & hosting):');
        $this->table(
            ['Toko', 'Produk', 'Batch', 'Lokal id', 'Lokal qty', 'Hosting id', 'Hosting qty', 'Putusan auto'],
            array_map(fn ($c) => [
                $c[0]->owner_id, $c[0]->product_id, $c[0]->batch_number, $c[0]->id, (float) $c[0]->qty, $c[1]->id, (float) $c[1]->qty,
                $this->isSameInput($c[0], $c[1]) ? 'sama: 1 inputan (nolkan hosting)' : 'beda: 2 inputan (pertahankan keduanya)',
            ], array_slice($conflicts, 0, 40))
        );
        if (count($conflicts) > 40) {
            $this->line('... ' . (count($conflicts) - 40) . ' baris lainnya.');
        }

        if (! $apply) {
            $this->warn('Simulasi selesai. Backup kedua database, lalu jalankan dengan --keep=... --apply.');

            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm('Sudah backup lokal & hosting dan yakin menerapkan --keep=' . $keep . '?')) {
            return self::FAILURE;
        }

        $n = 0;
        foreach ($conflicts as [$l, $s]) {
            $newName = $l->batch_number . '-DUP';
            // auto: qty+HPP sama = inputan yang sama (dobel karena sinkron), beda = inputan berbeda (tumpuk).
            $mode = $keep === 'auto' ? ($this->isSameInput($l, $s) ? 'local' : 'both') : $keep;

            if ($mode === 'server' || $mode === 'both') {
                // Lokal: ganti nomor batch; untuk 'server' stoknya juga dinolkan (tercatat di Kartu Stok).
                DB::transaction(function () use ($l, $newName, $mode, $stockService) {
                    $row = OwnerStock::whereKey($l->id)->lockForUpdate()->first();
                    if (! $row) {
                        return;
                    }
                    $row->update(['batch_number' => $newName . '-L', 'sku' => $newName . '-L']);
                    if ($mode === 'server') {
                        $stockService->adjust($row, 0.0, today()->toDateString(), 'Set Stock', null, 'Set Stock dobel (lokal & hosting): batch lokal dinolkan, dipakai batch hosting');
                    }
                });
            } else {
                // keep=local: hosting diganti nomor batch + dinolkan (query langsung ke hosting, tercatat di Kartu Stok).
                $server = DB::connection(config('dbsync.server_connection', 'server'));
                $server->transaction(function () use ($server, $s, $newName) {
                    $row = $server->table('owner_stocks')->where('id', $s->id)->lockForUpdate()->first();
                    if (! $row) {
                        return;
                    }
                    $qty = (float) $row->qty;
                    $server->table('owner_stocks')->where('id', $row->id)->update([
                        'batch_number' => $newName . '-H', 'sku' => $newName . '-H', 'qty' => 0, 'updated_at' => now(),
                    ]);
                    if ($qty != 0.0) {
                        $server->table('stock_movements')->insert([
                            'product_id' => $row->product_id,
                            'owner_id' => $row->owner_id,
                            'owner_stock_id' => $row->id,
                            'type' => 'adjustment',
                            'qty_in' => $qty < 0 ? abs($qty) : 0,
                            'qty_out' => $qty > 0 ? $qty : 0,
                            'balance' => 0,
                            'notes' => 'Set Stock dobel (lokal & hosting): batch hosting dinolkan, dipakai batch lokal',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                });
            }
            $n++;
        }

        $this->info($n . ' batch bentrok diselesaikan. Jalankan sinkron lagi.');

        return self::SUCCESS;
    }

    /** Qty dan HPP sama di kedua sisi: kemungkinan besar 1 inputan yang sama yang terbuat dobel. */
    private function isSameInput(object $local, object $server): bool
    {
        return abs((float) $local->qty - (float) $server->qty) < 0.0001
            && abs((float) $local->hpp - (float) $server->hpp) < 0.01;
    }
}