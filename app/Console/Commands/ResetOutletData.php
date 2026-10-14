<?php

namespace App\Console\Commands;

use App\Models\OwnerStock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bersihkan SELURUH data sisi toko/outlet (sekali jalan, bukan migration) supaya toko mulai dari nol
 * setelah dummy & bekas ujicoba dibuang. Yang tersisa hanya sisi gudang.
 *
 * DIHAPUS PERMANEN (hanya yang terkait toko):
 *  - Stok toko: owner_stocks (termasuk batch MINUS & yang soft-delete), log aktivitas OwnerStock.
 *  - Riwayat stok toko: stock_movements dengan owner_id terisi, stock_adjustments (opname toko) dengan owner_id terisi.
 *  - Pembelian outlet: outlet_purchases + outlet_purchase_items.
 *  - Penjualan toko (outlet_id terisi) beserta item, promo terpakai, voucher terpakai, refund penjualan.
 *  - Kasir: cashier_sessions, cashier_shifts, cashier_drawer_entries, invoice_sequences (nomor invoice mulai lagi).
 *  - Pemasukan lain: SEMUA baris other_incomes (tanpa --outlet). Dengan --outlet hanya milik outlet itu.
 *  - Voucher: SEMUA voucher beserta produk/outlet/pemakaiannya (hanya tanpa --outlet). Penjualan marketplace
 *    yang pernah memakai voucher tetap ada; hanya tautan voucher_id-nya dikosongkan (FK ke voucher itu cascade
 *    delete, jadi tanpa langkah ini penjualan marketplace ikut terhapus).
 *
 * TIDAK DISENTUH:
 *  - Stok gudang (stocks) & Kartu Stok gudang (movement tanpa owner_id, termasuk "keluar ke outlet").
 *  - Request Order, Picking List, Delivery Order (riwayat tetap ada).
 *  - Penjualan marketplace (outlet_id kosong), pembelian gudang, kas, pengeluaran, refund pembelian.
 *  - Master: outlet, user, produk, harga outlet, promo, metode bayar, kategori pemasukan lain.
 *
 * Default = simulasi; pakai --apply untuk menulis. Backup database dulu sebelum --apply.
 */
class ResetOutletData extends Command
{
    protected $signature = 'outlet:reset-data
        {--apply : Hapus data di database (default: hanya simulasi)}
        {--outlet=* : Batasi ke outlet tertentu (ID outlet, boleh diulang). Default: semua outlet}
        {--force : Lewati pertanyaan konfirmasi saat --apply}';

    protected $description = 'Hapus semua data sisi toko/outlet (stok, riwayat, penjualan, kasir, pembelian outlet); data gudang tidak disentuh';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $ids = array_values(array_filter(array_map('intval', (array) $this->option('outlet'))));

        $this->warn($apply
            ? 'MODE APPLY: data toko akan DIHAPUS PERMANEN.'
            : 'MODE SIMULASI: tidak ada data yang diubah (tambahkan --apply untuk menghapus).');
        $this->line($ids ? 'Outlet dipilih: ' . implode(', ', $ids) : 'Outlet: SEMUA (penjualan marketplace tanpa outlet tidak ikut dihapus)');

        $all = empty($ids);

        $byOutlet = fn ($q, string $col) => $q->whereNotNull($col)->when($ids, fn ($x) => $x->whereIn($col, $ids));

        $penjualanIds = fn () => $byOutlet(DB::table('penjualans'), 'outlet_id')->select('id');
        $sessionIds = fn () => $byOutlet(DB::table('cashier_sessions'), 'outlet_id')->select('id');
        $ownerStockIds = fn () => ($ids ? DB::table('owner_stocks')->whereIn('owner_id', $ids) : DB::table('owner_stocks'))->select('id');
        $purchaseIds = fn () => $byOutlet(DB::table('outlet_purchases'), 'outlet_id')->select('id');
        $refundPenjualanIds = fn () => DB::table('refund_penjualans')->whereIn('penjualan_id', $penjualanIds())->select('id');
        $activityTable = config('activitylog.table_name', 'activity_log');

        // Urutan penting: anak dulu, induk belakangan (menghormati foreign key).
        $steps = [
            // Penjualan marketplace (tanpa outlet) yang memakai voucher: kosongkan tautannya dulu.
            // penjualans.voucher_id = FK cascadeOnDelete, jadi tanpa ini menghapus voucher menghapus penjualannya.
            ['Lepas voucher dari penjualan marketplace (update)', 'penjualans', fn () => DB::table('penjualans')->whereNull('outlet_id')->whereNotNull('voucher_id'), 'voucher_id'],
            ['Voucher terpakai (voucher_redemptions)', 'voucher_redemptions', fn () => $all
                ? DB::table('voucher_redemptions')
                : DB::table('voucher_redemptions')->whereIn('penjualan_id', $penjualanIds())],
            ['Promo terpakai (promotion_applications)', 'promotion_applications', fn () => DB::table('promotion_applications')->whereIn('penjualan_id', $penjualanIds())],
            ['Item refund penjualan', 'refund_penjualan_items', fn () => DB::table('refund_penjualan_items')->whereIn('refund_penjualan_id', $refundPenjualanIds())],
            ['Refund penjualan', 'refund_penjualans', fn () => DB::table('refund_penjualans')->whereIn('penjualan_id', $penjualanIds())],
            ['Item penjualan toko', 'penjualan_items', fn () => DB::table('penjualan_items')->whereIn('penjualan_id', $penjualanIds())],
            ['Penjualan toko', 'penjualans', fn () => $byOutlet(DB::table('penjualans'), 'outlet_id')],
            ['Entri laci kasir', 'cashier_drawer_entries', fn () => $byOutlet(DB::table('cashier_drawer_entries'), 'outlet_id')],
            ['Shift kasir', 'cashier_shifts', fn () => DB::table('cashier_shifts')->whereIn('cashier_session_id', $sessionIds())],
            ['Sesi kasir', 'cashier_sessions', fn () => $byOutlet(DB::table('cashier_sessions'), 'outlet_id')],
            ['Counter nomor invoice', 'invoice_sequences', fn () => $byOutlet(DB::table('invoice_sequences'), 'outlet_id')],
            ['Item pembelian outlet', 'outlet_purchase_items', fn () => DB::table('outlet_purchase_items')->whereIn('outlet_purchase_id', $purchaseIds())],
            ['Pembelian outlet', 'outlet_purchases', fn () => $byOutlet(DB::table('outlet_purchases'), 'outlet_id')],
            ['Pemasukan lain (other_incomes)', 'other_incomes', fn () => $all
                ? DB::table('other_incomes')
                : $byOutlet(DB::table('other_incomes'), 'outlet_id')],
            ['Opname/penyesuaian stok toko', 'stock_adjustments', fn () => $byOutlet(DB::table('stock_adjustments'), 'owner_id')],
            ['Movement stok toko (Kartu Stok toko)', 'stock_movements', fn () => $byOutlet(DB::table('stock_movements'), 'owner_id')],
            ['Log aktivitas stok toko', $activityTable, fn () => DB::table($activityTable)
                ->where('subject_type', OwnerStock::class)
                ->whereIn('subject_id', $ownerStockIds())],
            ['Stok toko (owner_stocks)', 'owner_stocks', fn () => $ids ? DB::table('owner_stocks')->whereIn('owner_id', $ids) : DB::table('owner_stocks')],
        ];

        // Voucher dihapus semua; hanya saat tanpa --outlet (voucher bersifat global).
        if ($all) {
            array_push($steps,
                ['Produk voucher (voucher_products)', 'voucher_products', fn () => DB::table('voucher_products')],
                ['Outlet voucher (voucher_outlets)', 'voucher_outlets', fn () => DB::table('voucher_outlets')],
                ['Voucher', 'vouchers', fn () => DB::table('vouchers')],
            );
        } else {
            $this->warn('Dengan --outlet, voucher TIDAK dihapus (bersifat global) dan other_incomes hanya milik outlet terpilih. Jalankan tanpa --outlet untuk menghapus semuanya.');
        }

        // ---- Pengecekan sebelum jalan ----
        $blocked = false;

        // Refund lama (tabel refunds) yang menempel ke penjualan toko mempengaruhi saldo kas; tidak kita tebak.
        if (Schema::hasTable('refunds') && Schema::hasColumn('refunds', 'penjualan_id')) {
            $oldRefunds = DB::table('refunds')->whereIn('penjualan_id', $penjualanIds())->count();
            if ($oldRefunds > 0) {
                $this->error("Ada {$oldRefunds} refund lama (tabel refunds) yang terkait penjualan toko. Refund ini mempengaruhi saldo kas, jadi tidak dihapus otomatis. Tangani manual dulu.");
                $blocked = true;
            }
        }

        // DO yang belum diterima outlet akan menambah stok toko lagi saat diterima.
        if (Schema::hasTable('delivery_orders')) {
            $pendingDo = DB::table('delivery_orders')
                ->whereNull('deleted_at')
                ->whereIn('status', ['draft', 'sent'])
                ->when($ids, fn ($q) => $q->whereIn('owner_id', $ids))
                ->count();
            if ($pendingDo > 0) {
                $this->warn("Perhatian: ada {$pendingDo} Delivery Order berstatus draft/sent (belum diterima). Saat diterima nanti stok toko akan bertambah lagi. Tuntaskan atau batalkan dulu bila perlu.");
            }
        }

        // ---- Ringkasan ----
        $rows = [];
        $total = 0;
        foreach ($steps as [$label, $table, $query]) {
            if (! Schema::hasTable($table)) {
                $rows[] = [$label, $table, 'tabel tidak ada'];

                continue;
            }
            $count = $query()->count();
            $total += $count;
            $rows[] = [$label, $table, $count];
        }
        $this->table(['Data', 'Tabel', 'Baris terdampak'], $rows);
        $this->line("Total baris: {$total}");

        if ($blocked) {
            $this->error('Dibatalkan karena ada masalah di atas.');

            return self::FAILURE;
        }

        if (! $apply) {
            $this->info('Simulasi selesai. Jalankan ulang dengan --apply untuk menghapus.');

            return self::SUCCESS;
        }

        if ($total === 0) {
            $this->info('Tidak ada data toko yang perlu dihapus.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->warn('Pastikan sudah backup database. Penghapusan ini permanen dan tidak bisa di-undo lewat aplikasi.');
            if ($this->ask('Ketik RESET untuk melanjutkan') !== 'RESET') {
                $this->info('Dibatalkan.');

                return self::SUCCESS;
            }
        }

        DB::beginTransaction();

        try {
            foreach ($steps as $step) {
                [$label, $table, $query] = $step;
                $nullifyColumn = $step[3] ?? null;

                if (! Schema::hasTable($table)) {
                    continue;
                }

                if ($nullifyColumn) {
                    $affected = $query()->update([$nullifyColumn => null]);
                    $this->line(sprintf('%-45s %d baris diubah', $label, $affected));
                } else {
                    $deleted = $query()->delete();
                    $this->line(sprintf('%-45s %d baris dihapus', $label, $deleted));
                }
            }

            DB::commit();
            $this->info('Selesai. Data sisi toko sudah bersih; stok & riwayat gudang tidak berubah.');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Gagal, semua perubahan dibatalkan: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}