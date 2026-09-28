<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Perbaikan data sekali jalan (bukan migration) untuk stok toko yang dibuat lewat jalur lama
 * (DeliveryOrderController versi main): HPP kosong dan tidak ada movement sama sekali.
 *
 *  1. Isi HPP dari kolom lama owner_stocks.harga_beli (kalau masih ada), lalu dari stocks.harga_beli.
 *  2. Buat 1 movement "saldo awal" (type in) untuk setiap owner stock yang qty-nya > 0 dan belum punya movement.
 *
 * Aman dijalankan ulang: hanya menyentuh baris yang masih kosong. Default = simulasi; pakai --apply untuk menulis.
 */
class BackfillOwnerStockLedger extends Command
{
    protected $signature = 'stock:backfill-owner-ledger {--apply : Tulis perubahan ke database (default: hanya simulasi)}';

    protected $description = 'Isi HPP dan saldo awal movement untuk stok toko lama yang belum punya ledger';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->warn($apply ? 'MODE APPLY: data akan diubah.' : 'MODE SIMULASI: tidak ada data yang diubah (tambahkan --apply untuk menulis).');

        DB::beginTransaction();

        try {
            // ---- 1. HPP ----
            $emptyHpp = fn ($query) => $query->whereNull('hpp')->orWhere('hpp', 0);

            if (Schema::hasColumn('owner_stocks', 'harga_beli')) {
                $legacy = DB::table('owner_stocks')
                    ->whereNull('deleted_at')
                    ->where($emptyHpp)
                    ->where('harga_beli', '>', 0);
                $count = (clone $legacy)->count();
                $this->line("HPP dari kolom lama owner_stocks.harga_beli : {$count} baris");
                if ($apply && $count > 0) {
                    $legacy->update(['hpp' => DB::raw('harga_beli')]);
                }
            }

            $fromStock = DB::table('owner_stocks as os')
                ->join('stocks as s', 's.id', '=', 'os.stock_id')
                ->whereNull('os.deleted_at')
                ->where(fn ($query) => $query->whereNull('os.hpp')->orWhere('os.hpp', 0))
                ->where('s.harga_beli', '>', 0);
            $count = (clone $fromStock)->count();
            $this->line("HPP dari stocks.harga_beli (via stock_id)     : {$count} baris");
            if ($apply && $count > 0) {
                $fromStock->update(['os.hpp' => DB::raw('s.harga_beli')]);
            }

            // ---- 2. Saldo awal movement ----
            $missing = DB::table('owner_stocks as os')
                ->whereNull('os.deleted_at')
                ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                    ->from('stock_movements as m')
                    ->whereColumn('m.owner_stock_id', 'os.id'));

            $tanpaMovement = (clone $missing)->count();
            $positif = (clone $missing)->where('os.qty', '>', 0)->count();
            $this->line("Owner stock tanpa movement                    : {$tanpaMovement} baris");
            $this->line("  - qty > 0 (akan dibuatkan saldo awal)       : {$positif} baris");
            $this->line('  - qty <= 0 (dilewati)                       : ' . ($tanpaMovement - $positif) . ' baris');

            $dibuat = 0;
            if ($apply && $positif > 0) {
                $now = now();
                (clone $missing)->where('os.qty', '>', 0)
                    ->select('os.id', 'os.owner_id', 'os.product_id', 'os.qty', 'os.created_at')
                    ->chunkById(500, function ($rows) use (&$dibuat, $now) {
                        $insert = $rows->map(fn ($row) => [
                            'product_id' => $row->product_id,
                            'owner_id' => $row->owner_id,
                            'owner_stock_id' => $row->id,
                            'user_id' => null,
                            'type' => 'in',
                            'reference_type' => null,
                            'reference_id' => null,
                            'qty_in' => (int) $row->qty,
                            'qty_out' => 0,
                            'balance' => (int) $row->qty,
                            'notes' => 'Saldo awal (backfill ledger stok toko)',
                            'created_at' => $row->created_at ?? $now,
                            'updated_at' => $now,
                        ])->all();

                        DB::table('stock_movements')->insert($insert);
                        $dibuat += count($insert);
                    }, 'os.id', 'id');
            }

            if ($apply) {
                DB::commit();
                $this->info("Selesai. Movement saldo awal dibuat: {$dibuat}.");
            } else {
                DB::rollBack();
                $this->info('Simulasi selesai. Jalankan ulang dengan --apply untuk menulis.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Gagal, semua perubahan dibatalkan: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}