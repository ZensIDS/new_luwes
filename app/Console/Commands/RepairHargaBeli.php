<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\HargaBeliGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

/**
 * Memulihkan harga beli yang rusak akibat bug mask "#,##0" (desimal dibuang -> 16666.67 jadi 1666667).
 *
 * Sumber kebenaran: activity log produk (properties.old.harga_beli sebelum update yang merusak).
 *
 * Default = DRY RUN (hanya laporan + CSV). Tambahkan --apply untuk benar-benar menulis ke database.
 *
 *   php artisan harga-beli:repair                       (dry run)
 *   php artisan harga-beli:repair --since="2026-10-01"  (batasi log mulai tanggal tertentu)
 *   php artisan harga-beli:repair --apply               (eksekusi, WAJIB backup database dulu)
 *
 * Jalankan SETELAH migration decimal (2026_11_02_000001_change_harga_beli_to_decimal) dijalankan.
 */
class RepairHargaBeli extends Command
{
    protected $signature = 'harga-beli:repair {--since= : Hanya proses activity log sejak tanggal ini (Y-m-d)} {--apply : Tulis perubahan ke database} {--force : Lewati konfirmasi}';

    protected $description = 'Pulihkan harga beli yang rusak (desimal terbuang) dari activity log produk';

    /** Tabel transaksi yang diperbaiki otomatis: [tabel => kolom qty (atau null)] */
    private array $fixTables = [
        'pembelian_products' => 'qty',
        'stock_pembelians'   => 'qty',
        'stocks'             => 'qty',
    ];

    /** Tabel yang hanya dilaporkan (tidak diubah otomatis). */
    private array $reportOnly = [
        'owner_stocks'         => ['harga_beli', 'hpp'],
        'delivery_order_items' => ['harga_beli'],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $since = $this->option('since');

        $this->info($apply ? 'MODE APPLY - perubahan akan ditulis ke database.' : 'MODE DRY RUN - tidak ada yang diubah.');

        // ---- 1. Cari produk yang harganya dirusak, dari activity log ----
        $query = Activity::query()
            ->where('log_name', 'Product')
            ->where('event', 'updated')
            ->orderBy('created_at')->orderBy('id');
        if ($since) {
            $query->where('created_at', '>=', $since);
        }

        // product_id => ['true' => harga benar, 'corrupt' => nilai rusak, 'at' => waktu]
        $broken = [];

        foreach ($query->cursor() as $a) {
            $props = $a->properties;
            $old = $props['old']['harga_beli'] ?? null;
            $new = $props['attributes']['harga_beli'] ?? null;
            if ($old === null || $new === null) {
                continue;
            }
            $old = (float) $old;
            $new = (float) $new;

            // Hanya harga yang punya desimal yang bisa rusak oleh bug ini.
            if (abs($old - round($old)) < 0.0001) {
                continue;
            }
            $corrupt = $this->corruptedValue($old);
            if (abs($new - $corrupt) >= 0.5) {
                continue;
            }

            $pid = (int) $a->subject_id;
            if (! isset($broken[$pid])) {
                $broken[$pid] = ['true' => round($old, 2), 'corrupt' => $corrupt, 'at' => (string) $a->created_at];
            }
        }

        $this->info(count($broken) . ' produk terdeteksi pernah dirusak harga belinya.');
        if (! $broken) {
            return self::SUCCESS;
        }

        $rows = [];      // laporan CSV
        $actions = [];   // closure yang dieksekusi saat --apply
        $touchedPo = [];

        // ---- 2. Produk ----
        $products = Product::withTrashed()->whereIn('id', array_keys($broken))->get(['id', 'code', 'name', 'harga_beli'])->keyBy('id');
        foreach ($broken as $pid => $b) {
            $p = $products[$pid] ?? null;
            if (! $p) {
                continue;
            }
            $current = (float) $p->harga_beli;
            if (abs($current - $b['corrupt']) < 0.5) {
                $rows[] = ['products', $pid, $pid, 'harga_beli', $current, $b['true'], 'RESTORE'];
                $actions[] = fn () => DB::table('products')->where('id', $pid)->update(['harga_beli' => $b['true']]);
            } else {
                $rows[] = ['products', $pid, $pid, 'harga_beli', $current, $b['true'], 'MANUAL (harga produk sudah berubah lagi setelah rusak; cek HPP/penerimaan)'];
            }
        }

        // ---- 3. Tabel transaksi ----
        foreach ($this->fixTables as $table => $qtyCol) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'harga_beli') || ! Schema::hasColumn($table, 'product_id')) {
                continue;
            }
            $hasSubtotal = Schema::hasColumn($table, 'subtotal');
            $hasPo = Schema::hasColumn($table, 'pembelian_id');

            foreach (DB::table($table)->whereIn('product_id', array_keys($broken))->get() as $r) {
                $b = $broken[$r->product_id];
                if (abs((float) $r->harga_beli - $b['corrupt']) >= 0.5) {
                    continue;
                }
                $update = ['harga_beli' => $b['true']];
                if ($hasSubtotal && $r->subtotal !== null && $b['corrupt'] > 0) {
                    $update['subtotal'] = round((float) $r->subtotal * $b['true'] / $b['corrupt'], 2);
                }
                $rows[] = [$table, $r->id, $r->product_id, 'harga_beli', (float) $r->harga_beli, $b['true'], 'RESTORE'];
                if (isset($update['subtotal'])) {
                    $rows[] = [$table, $r->id, $r->product_id, 'subtotal', (float) $r->subtotal, $update['subtotal'], 'RESTORE'];
                }
                $actions[] = fn () => DB::table($table)->where('id', $r->id)->update($update);
                if ($hasPo && $r->pembelian_id) {
                    $touchedPo[$r->pembelian_id] = true;
                }
            }
        }

        // ---- 4. Tabel yang hanya dilaporkan ----
        foreach ($this->reportOnly as $table => $cols) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'product_id')) {
                continue;
            }
            foreach ($cols as $col) {
                if (! Schema::hasColumn($table, $col)) {
                    continue;
                }
                foreach (DB::table($table)->whereIn('product_id', array_keys($broken))->get() as $r) {
                    $b = $broken[$r->product_id];
                    if (abs((float) $r->$col - $b['corrupt']) < 0.5) {
                        $rows[] = [$table, $r->id, $r->product_id, $col, (float) $r->$col, $b['true'], 'MANUAL (hanya dilaporkan, cek sendiri)'];
                    }
                }
            }
        }

        // ---- 5. Total PO dihitung ulang dari subtotal item ----
        foreach (array_keys($touchedPo) as $poId) {
            $actions[] = function () use ($poId) {
                $sum = (float) DB::table('pembelian_products')->where('pembelian_id', $poId)->sum('subtotal');
                DB::table('pembelians')->where('id', $poId)->update(['total' => $sum]);
            };
            $rows[] = ['pembelians', $poId, '', 'total', '', '(dihitung ulang dari subtotal item)', 'RECALC'];
        }

        // ---- Laporan ----
        $dir = storage_path('app');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $csv = $dir . '/harga-beli-repair-' . now()->format('Ymd-His') . '.csv';
        $fh = fopen($csv, 'w');
        fputcsv($fh, ['table', 'id', 'product_id', 'column', 'current', 'restore_to', 'action']);
        foreach ($rows as $r) {
            fputcsv($fh, $r);
        }
        fclose($fh);

        $restore = count(array_filter($rows, fn ($r) => $r[6] === 'RESTORE'));
        $manual = count(array_filter($rows, fn ($r) => str_starts_with($r[6], 'MANUAL')));
        $this->table(['Tabel', 'ID', 'Produk', 'Kolom', 'Sekarang', 'Dipulihkan ke', 'Aksi'], array_slice($rows, 0, 30));
        if (count($rows) > 30) {
            $this->line('... (' . (count($rows) - 30) . ' baris lainnya ada di CSV)');
        }
        $this->info("Dipulihkan otomatis: {$restore} nilai | Perlu dicek manual: {$manual} | CSV: {$csv}");

        if (! $apply) {
            $this->warn('Dry run selesai. Periksa CSV, backup database, lalu jalankan lagi dengan --apply.');
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Sudah backup database dan yakin menerapkan perubahan?')) {
            return self::FAILURE;
        }

        // Jalur 'maintenance' (HargaBeliGuard): perbaikan data darurat, hanya berjalan dengan --apply.
        HargaBeliGuard::allow('maintenance', function () use ($actions) {
            DB::transaction(function () use ($actions) {
                foreach ($actions as $fn) {
                    $fn();
                }
            });
        });

        HargaBeliGuard::logMaintenance('harga-beli:repair', 'perubahan diterapkan', [
            'csv'     => $csv,
            'produk'  => array_values(array_filter($rows, fn ($r) => $r[0] === 'products' && $r[6] === 'RESTORE')),
            'jumlah_nilai_dipulihkan' => $restore,
        ]);

        $this->info('Selesai. Perubahan sudah diterapkan.');
        return self::SUCCESS;
    }

    /** Meniru persis nilai rusak yang dihasilkan mask lama: buang semua non-digit dari "16666.67" -> 1666667. */
    private function corruptedValue(float $price): float
    {
        $s = rtrim(rtrim(number_format($price, 2, '.', ''), '0'), '.');

        return (float) str_replace('.', '', $s);
    }
}