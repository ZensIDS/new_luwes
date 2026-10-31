<?php

namespace App\Console\Commands;

use App\Models\CashierDrawerEntry;
use App\Models\CashierSession;
use App\Models\OtherIncome;
use App\Models\OwnerStock;
use App\Services\ExpiredStockService;
use App\Services\LowStockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Bersihkan SELURUH data sisi toko/outlet (sekali jalan, bukan migration) supaya toko mulai dari nol
 * setelah dummy & bekas ujicoba dibuang. Yang tersisa hanya sisi gudang.
 *
 * DIHAPUS PERMANEN (hanya yang terkait toko):
 *  - Stok toko: owner_stocks (termasuk batch MINUS & yang soft-delete).
 *  - Riwayat stok toko: stock_movements dengan owner_id terisi, stock_adjustments (opname/Set Stock toko) dengan owner_id terisi.
 *  - Set Stock: set_stock_drafts + set_stock_draft_items (keranjang scan aktif maupun yang sudah selesai).
 *  - Pembelian outlet: outlet_purchases + outlet_purchase_items.
 *  - Penjualan toko (outlet_id terisi) beserta item, transaksi pembayaran (transactions), promo terpakai,
 *    voucher terpakai, refund penjualan.
 *  - Kasir: cashier_sessions, cashier_shifts, cashier_drawer_entries, invoice_sequences (nomor invoice mulai lagi),
 *    keranjang kasir (user_cart yang punya outlet_id).
 *  - Pendapatan Lain (other_incomes, termasuk Listing & Sewa): SEMUA baris tanpa --outlet. Dengan --outlet
 *    atau --keep-other-incomes hanya yang punya outlet_id.
 *  - Voucher: SEMUA voucher beserta produk/outlet/pemakaiannya (hanya tanpa --outlet dan tanpa --keep-vouchers).
 *    Penjualan marketplace yang pernah memakai voucher tetap ada; hanya tautan voucher_id-nya dikosongkan
 *    (FK ke voucher itu cascade delete, jadi tanpa langkah ini penjualan marketplace ikut terhapus).
 *  - Log aktivitas milik data di atas: OwnerStock, CashierSession, CashierDrawerEntry, OtherIncome.
 *
 * DIPERBAIKI (bukan dihapus):
 *  - promotions.used_qty dikurangi sebesar pemakaian pada penjualan toko yang dihapus, supaya kuota flash sale /
 *    bundle tidak "habis" oleh transaksi yang sudah tidak ada. Promo marketplace tidak ikut berubah.
 *  - Cache badge stok minus, notice expired, dan peta min stok dibuang setelah --apply.
 *
 * TIDAK DISENTUH:
 *  - Stok gudang (stocks) & Kartu Stok gudang (movement tanpa owner_id, termasuk "keluar ke outlet").
 *  - Request Order, Picking List, Delivery Order (riwayat tetap ada).
 *  - Penjualan marketplace (outlet_id kosong), pembelian gudang, kas, pengeluaran, refund pembelian.
 *  - Master: outlet, user, produk, harga outlet (outlet_prices), promo, metode bayar, kategori pendapatan lain.
 *
 * Default = simulasi; pakai --apply untuk menulis. Backup database dulu sebelum --apply.
 *
 * CATATAN DbSync: bila sinkron lokal <-> hosting aktif, hapus di KEDUA database atau hentikan sinkron dulu
 * (lihat peringatan yang tampil saat command dijalankan).
 */
class ResetOutletData extends Command
{
    protected $signature = 'outlet:reset-data
        {--apply : Hapus data di database (default: hanya simulasi)}
        {--outlet=* : Batasi ke outlet tertentu (ID outlet, boleh diulang). Default: semua outlet}
        {--keep-vouchers : Jangan hapus voucher (pemakaian voucher pada penjualan toko tetap dibersihkan)}
        {--keep-other-incomes : Jangan hapus Pendapatan Lain yang tidak punya outlet (Listing, Sewa umum, dll)}
        {--force : Lewati pertanyaan konfirmasi saat --apply}';

    protected $description = 'Hapus semua data sisi toko/outlet (stok, Set Stock, riwayat, penjualan, kasir, pembelian outlet); data gudang tidak disentuh';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $ids = array_values(array_filter(array_map('intval', (array) $this->option('outlet'))));

        $this->warn($apply
            ? 'MODE APPLY: data toko akan DIHAPUS PERMANEN.'
            : 'MODE SIMULASI: tidak ada data yang diubah (tambahkan --apply untuk menghapus).');
        $this->line($ids ? 'Outlet dipilih: ' . implode(', ', $ids) : 'Outlet: SEMUA (penjualan marketplace tanpa outlet tidak ikut dihapus)');

        // Pastikan semua ID outlet yang diketik benar-benar ada, supaya salah ketik tidak diam-diam "tidak menghapus apa pun".
        if ($ids) {
            $missing = array_values(array_diff($ids, DB::table('outlets')->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all()));
            if ($missing) {
                $this->error('ID outlet tidak ditemukan: ' . implode(', ', $missing));

                return self::FAILURE;
            }
        }

        $all = empty($ids);
        $deleteVouchers = $all && ! $this->option('keep-vouchers');
        $deleteAllIncomes = $all && ! $this->option('keep-other-incomes');

        $byOutlet = fn ($q, string $col) => $q->whereNotNull($col)->when($ids, fn ($x) => $x->whereIn($col, $ids));

        $penjualanIds = fn () => $byOutlet(DB::table('penjualans'), 'outlet_id')->select('id');
        $sessionIds = fn () => $byOutlet(DB::table('cashier_sessions'), 'outlet_id')->select('id');
        $drawerEntryIds = fn () => $byOutlet(DB::table('cashier_drawer_entries'), 'outlet_id')->select('id');
        $ownerStockIds = fn () => ($ids ? DB::table('owner_stocks')->whereIn('owner_id', $ids) : DB::table('owner_stocks'))->select('id');
        $purchaseIds = fn () => $byOutlet(DB::table('outlet_purchases'), 'outlet_id')->select('id');
        $refundPenjualanIds = fn () => DB::table('refund_penjualans')->whereIn('penjualan_id', $penjualanIds())->select('id');
        $otherIncomes = fn () => $deleteAllIncomes
            ? DB::table('other_incomes')
            : $byOutlet(DB::table('other_incomes'), 'outlet_id');
        $setStockDraftIds = fn () => $byOutlet(DB::table('set_stock_drafts'), 'outlet_id')->select('id');
        // user_cart.outlet_id bertipe string (lihat CartController: wherePivot('outlet_id', (string) $outletId)).
        $posCart = fn () => DB::table('user_cart')
            ->whereNotNull('outlet_id')
            ->when($ids, fn ($q) => $q->whereIn('outlet_id', array_map('strval', $ids)));
        $activityTable = config('activitylog.table_name', 'activity_log');
        $activityOf = fn (string $model, callable $subjectIds) => fn () => DB::table($activityTable)
            ->where('subject_type', $model)
            ->whereIn('subject_id', $subjectIds());

        // Pemakaian promo (flash sale / bundle) dari penjualan toko yang akan dihapus: per promotion_id.
        // Mengikuti PromotionService::apply() yang menaikkan used_qty sebesar (int) quantity tiap aplikasi.
        $promoUsage = fn () => DB::table('promotion_applications')
            ->whereIn('penjualan_id', $penjualanIds())
            ->whereNotNull('promotion_id')
            ->selectRaw('promotion_id, SUM(FLOOR(quantity)) as total_qty')
            ->groupBy('promotion_id')
            ->pluck('total_qty', 'promotion_id');
        $promoTracked = Schema::hasTable('promotion_applications') && Schema::hasTable('promotions');

        // Urutan penting: anak dulu, induk belakangan (menghormati foreign key).
        // Log aktivitas ditaruh PALING AWAL karena subject-nya (sesi kasir, laci, pendapatan lain, batch stok) dihapus
        // di langkah berikutnya; setelah itu subject_id-nya tidak bisa dicari lagi.
        // Format langkah: [label, tabel, query, (opsional) kolom yang di-UPDATE alih-alih dihapus].
        $steps = [
            ['Log aktivitas stok toko (OwnerStock)', $activityTable, $activityOf(OwnerStock::class, $ownerStockIds)],
            ['Log aktivitas sesi kasir', $activityTable, $activityOf(CashierSession::class, $sessionIds)],
            ['Log aktivitas laci kasir', $activityTable, $activityOf(CashierDrawerEntry::class, $drawerEntryIds)],
            ['Log aktivitas Pendapatan Lain', $activityTable, $activityOf(OtherIncome::class, fn () => $otherIncomes()->select('id'))],
        ];

        if ($deleteVouchers) {
            // Penjualan marketplace (tanpa outlet) yang memakai voucher: kosongkan tautannya dulu.
            // penjualans.voucher_id = FK cascadeOnDelete, jadi tanpa ini menghapus voucher menghapus penjualannya.
            $steps[] = ['Lepas voucher dari penjualan marketplace (update)', 'penjualans', fn () => DB::table('penjualans')->whereNull('outlet_id')->whereNotNull('voucher_id'), ['voucher_id' => null]];
        }

        array_push($steps,
            ['Voucher terpakai (voucher_redemptions)', 'voucher_redemptions', fn () => $deleteVouchers
                ? DB::table('voucher_redemptions')
                : DB::table('voucher_redemptions')->whereIn('penjualan_id', $penjualanIds())],
            ['Promo terpakai (promotion_applications)', 'promotion_applications', fn () => DB::table('promotion_applications')->whereIn('penjualan_id', $penjualanIds())],
            ['Item refund penjualan', 'refund_penjualan_items', fn () => DB::table('refund_penjualan_items')->whereIn('refund_penjualan_id', $refundPenjualanIds())],
            ['Refund penjualan', 'refund_penjualans', fn () => DB::table('refund_penjualans')->whereIn('penjualan_id', $penjualanIds())],
            ['Transaksi pembayaran penjualan (transactions)', 'transactions', fn () => DB::table('transactions')->whereIn('penjualan_id', $penjualanIds())],
            ['Item penjualan toko', 'penjualan_items', fn () => DB::table('penjualan_items')->whereIn('penjualan_id', $penjualanIds())],
            ['Penjualan toko', 'penjualans', fn () => $byOutlet(DB::table('penjualans'), 'outlet_id')],
            ['Entri laci kasir', 'cashier_drawer_entries', fn () => $byOutlet(DB::table('cashier_drawer_entries'), 'outlet_id')],
            ['Shift kasir', 'cashier_shifts', fn () => DB::table('cashier_shifts')->whereIn('cashier_session_id', $sessionIds())],
            ['Sesi kasir', 'cashier_sessions', fn () => $byOutlet(DB::table('cashier_sessions'), 'outlet_id')],
            ['Counter nomor invoice', 'invoice_sequences', fn () => $byOutlet(DB::table('invoice_sequences'), 'outlet_id')],
            ['Keranjang kasir (user_cart)', 'user_cart', $posCart],
            ['Item pembelian outlet', 'outlet_purchase_items', fn () => DB::table('outlet_purchase_items')->whereIn('outlet_purchase_id', $purchaseIds())],
            ['Pembelian outlet', 'outlet_purchases', fn () => $byOutlet(DB::table('outlet_purchases'), 'outlet_id')],
            ['Pendapatan Lain (other_incomes)', 'other_incomes', $otherIncomes],
            ['Item keranjang Set Stock', 'set_stock_draft_items', fn () => DB::table('set_stock_draft_items')->whereIn('draft_id', $setStockDraftIds())],
            ['Keranjang Set Stock', 'set_stock_drafts', fn () => $byOutlet(DB::table('set_stock_drafts'), 'outlet_id')],
            ['Opname/penyesuaian stok toko', 'stock_adjustments', fn () => $byOutlet(DB::table('stock_adjustments'), 'owner_id')],
            ['Movement stok toko (Kartu Stok toko)', 'stock_movements', fn () => $byOutlet(DB::table('stock_movements'), 'owner_id')],
            ['Stok toko (owner_stocks)', 'owner_stocks', fn () => $ids ? DB::table('owner_stocks')->whereIn('owner_id', $ids) : DB::table('owner_stocks')],
        );

        // Voucher dihapus semua; hanya saat tanpa --outlet dan tanpa --keep-vouchers (voucher bersifat global).
        if ($deleteVouchers) {
            array_push($steps,
                ['Produk voucher (voucher_products)', 'voucher_products', fn () => DB::table('voucher_products')],
                ['Outlet voucher (voucher_outlets)', 'voucher_outlets', fn () => DB::table('voucher_outlets')],
                ['Voucher', 'vouchers', fn () => DB::table('vouchers')],
            );
        } elseif (! $all) {
            $this->warn('Dengan --outlet, voucher TIDAK dihapus (bersifat global) dan Pendapatan Lain hanya milik outlet terpilih. Jalankan tanpa --outlet untuk menghapus semuanya.');
        } else {
            $this->warn('--keep-vouchers: voucher TIDAK dihapus; hanya pemakaian voucher pada penjualan toko yang dibersihkan.');
        }

        if ($all && $this->option('keep-other-incomes')) {
            $this->warn('--keep-other-incomes: Pendapatan Lain tanpa outlet (Listing, Sewa umum) TIDAK dihapus.');
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

        // DO yang belum diterima outlet akan menambah stok toko lagi saat diterima (DeliveryOrderController::send()).
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

        // Sesi kasir yang masih terbuka akan ikut terhapus; kasir harus buka kasir lagi setelahnya.
        if (Schema::hasTable('cashier_sessions')) {
            $openSessions = $byOutlet(DB::table('cashier_sessions'), 'outlet_id')
                ->whereNull('deleted_at')
                ->where('status', 'open')
                ->count();
            if ($openSessions > 0) {
                $this->warn("Perhatian: ada {$openSessions} sesi kasir yang masih TERBUKA. Sesi ini ikut dihapus; kasir perlu membuka kasir lagi setelah reset.");
            }
        }

        // Keranjang Set Stock yang belum disimpan (draft aktif) juga ikut hilang.
        if (Schema::hasTable('set_stock_drafts')) {
            $activeDrafts = $byOutlet(DB::table('set_stock_drafts'), 'outlet_id')->where('status', 'active')->count();
            if ($activeDrafts > 0) {
                $this->warn("Perhatian: ada {$activeDrafts} keranjang Set Stock yang masih aktif (belum disimpan). Isi scan-nya ikut terhapus.");
            }
        }

        $this->warnIfDbSyncEnabled();

        // ---- Ringkasan ----
        $rows = [];
        $total = 0;
        foreach ($steps as $step) {
            [$label, $table, $query] = $step;

            if (! Schema::hasTable($table)) {
                $rows[] = [$label, $table, 'tabel tidak ada'];

                continue;
            }
            $count = $query()->count();
            $total += $count;
            $rows[] = [$label, $table, $count];
        }

        if ($promoTracked) {
            $promoCount = $promoUsage()->count();
            $total += $promoCount;
            $rows[] = ['Kuota promo dikembalikan (promotions.used_qty dikurangi)', 'promotions', $promoCount];
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
            // Harus dibaca SEBELUM promotion_applications dihapus.
            $usage = $promoTracked ? $promoUsage() : collect();

            foreach ($steps as $step) {
                [$label, $table, $query] = $step;
                $set = $step[3] ?? null;

                if (! Schema::hasTable($table)) {
                    continue;
                }

                if ($set) {
                    $affected = $query()->update($set);
                    $this->line(sprintf('%-60s %d baris diubah', $label, $affected));
                } else {
                    $deleted = $query()->delete();
                    $this->line(sprintf('%-60s %d baris dihapus', $label, $deleted));
                }
            }

            if ($promoTracked) {
                $restored = 0;
                foreach ($usage as $promotionId => $qty) {
                    $qty = (int) $qty;
                    if ($qty < 1) {
                        continue;
                    }
                    // used_qty unsigned: jangan sampai minus bila datanya pernah tidak sinkron.
                    $restored += DB::table('promotions')
                        ->where('id', (int) $promotionId)
                        ->update(['used_qty' => DB::raw("GREATEST(CAST(used_qty AS SIGNED) - {$qty}, 0)")]);
                }
                $this->line(sprintf('%-60s %d baris diubah', 'Kuota promo dikembalikan (promotions.used_qty)', $restored));
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            $this->error('Gagal, semua perubahan dibatalkan: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->flushCaches($ids);

        $this->info('Selesai. Data sisi toko sudah bersih; stok & riwayat gudang tidak berubah.');

        return self::SUCCESS;
    }

    /**
     * Sinkron lokal <-> hosting (config/dbsync.php) membandingkan baris di dua database. Bila hanya satu sisi
     * yang dibersihkan, sisi lain masih memegang data lama dan sinkron berikutnya bisa menyalinnya kembali
     * (terutama tabel append-only stock_movements & activity_log), menjumlahkan ulang kolom penambah
     * (owner_stocks.qty, promotions.used_qty), atau menahan penghapusan massal lewat delete_guard.
     */
    protected function warnIfDbSyncEnabled(): void
    {
        if (! config('dbsync.enabled')) {
            return;
        }

        $this->warn('DbSync AKTIF di server ini. Agar hasil reset tidak "hidup lagi" setelah sinkron berikutnya:');
        $this->line('  1. Hentikan sinkron dulu (DB_SYNC_ENABLED=false / hentikan scheduler) sebelum --apply, atau');
        $this->line('  2. Jalankan reset yang sama di database hosting, lalu aktifkan sinkron lagi.');
        $this->line('  Bila penghapusan ditahan delete_guard, jalankan: php artisan dbsync:run --force (hanya jika memang disengaja).');
    }

    /** Buang cache yang bergantung pada stok/penjualan toko. Gagal cache tidak membatalkan reset. */
    protected function flushCaches(array $ids): void
    {
        try {
            $outletIds = $ids ?: DB::table('outlets')->pluck('id')->map(fn ($id) => (int) $id)->all();

            // Badge stok minus (AppServiceProvider)
            Cache::forget('deficit-count:all');
            foreach ($outletIds as $outletId) {
                Cache::forget('deficit-count:' . $outletId);
            }

            // Notice expired / akan expired
            $expired = app(ExpiredStockService::class);
            $expired->forgetCache();
            foreach ($outletIds as $outletId) {
                $expired->forgetCache($outletId);
            }

            // Peta min stok dihitung dari riwayat penjualan
            LowStockService::forgetCache();
        } catch (Throwable $e) {
            $this->warn('Data sudah bersih, tetapi cache gagal dibuang otomatis (' . $e->getMessage() . '). Jalankan: php artisan cache:clear');
        }
    }
}