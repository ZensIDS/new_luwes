<?php

namespace App\Console\Commands;

use App\Models\OutletPrice;
use App\Models\OwnerStock;
use App\Models\Product;
use App\Services\LatestHpp;
use App\Support\HargaBeliGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Memulihkan HPP yang tertimpa jadi 0 oleh jalur non-resmi (K5).
 *
 * Sasaran: SEMUA produk yang terdaftar di Master Harga Jual (per outlet), berapapun stoknya (termasuk stok 0 / habis /
 * kedaluwarsa), yang HPP tujuan penulisannya sekarang 0. HPP tujuan = HPP batch terbaru (LatestHpp::stockFor, sama
 * seperti jalur Master Harga Jual) bila outlet punya batch, atau products.harga_beli bila tidak punya batch.
 * Jadi batch terbaru yang hpp-nya rusak jadi 0 ikut tersentuh walau batch lama / harga_beli masih > 0.
 * HPP dikembalikan ke nilai > 0 terakhir sebelum jadi 0.
 *
 * Sumber nilai pengganti, urut prioritas (yang pertama ketemu dipakai, sumbernya dilaporkan):
 *   1. log batch outlet (owner_stocks): perubahan hpp "X -> 0"; X = nilai terakhir sebelum jadi 0
 *   2. riwayat Master Harga Jual (log OutletPriceHpp): HPP > 0 terakhir yang diinput
 *   3. log batch outlet: HPP awal saat batch dibuat (> 0)
 *   4. log produk: harga_beli "X -> 0"
 *   5. HPP > 0 yang masih terbaca sistem sekarang (batch lama / harga_beli produk)
 *   6. harga beli > 0 terakhir di Pembelian Outlet (outlet ini)
 *   7. HPP > 0 terbaru produk ini di outlet mana pun / harga_beli produk (cadangan terakhir)
 * Produk yang tidak punya riwayat > 0 dilewati dan dilaporkan, tidak ditebak. Produk yang HPP-nya sengaja
 * dinolkan lewat Master Harga Jual (catatan terakhir di log = 0) juga dilewati.
 *
 * Penulisan mengikuti jalur Master Harga Jual (OutletPriceController::syncLatestHpp): bila outlet punya batch,
 * HPP batch terbaru (LatestHpp::stockFor) diisi; bila tidak, products.harga_beli (+ harga_beli_besar) diisi.
 * Harga jual dihitung langsung dari HPP terbaru (tidak disimpan), jadi tidak perlu sinkron tambahan.
 *
 * Default = DRY RUN (tabel + CSV). Tambahkan --apply untuk menulis (lewat allow('maintenance'), tercatat di log).
 *
 *   php artisan hpp:restore-zero                           (dry run semua outlet)
 *   php artisan hpp:restore-zero --outlet=3                (satu outlet)
 *   php artisan hpp:restore-zero --product=12,ABC-001      (id atau kode produk)
 *   php artisan hpp:restore-zero --apply                   (eksekusi, backup database dulu)
 */
class RestoreZeroHpp extends Command
{
    protected $signature = 'hpp:restore-zero
        {--outlet= : Batasi ke id outlet ini (pisahkan koma)}
        {--product= : Batasi ke id atau kode produk ini (pisahkan koma)}
        {--include-intentional : Pulihkan juga yang catatan terakhir Master Harga Jual-nya HPP 0 (default dilewati)}
        {--apply : Tulis perubahan ke database (default: hanya simulasi)}
        {--force : Lewati konfirmasi saat --apply}';

    protected $description = 'Pulihkan HPP terbaru yang tertimpa jadi 0 (produk di Master Harga Jual) ke nilai > 0 terakhir';

    private const HPP_LOG_NAME = 'OutletPriceHpp';

    public function handle(LatestHpp $latest): int
    {
        $apply = (bool) $this->option('apply');
        $this->warn($apply
            ? 'MODE APPLY: data akan diubah.'
            : 'MODE SIMULASI: tidak ada data yang diubah (tambahkan --apply untuk menulis).');

        $outletIds = $this->idList($this->option('outlet'));
        $productIds = $this->resolveProducts((string) $this->option('product'));
        if ($productIds === null) {
            return self::FAILURE;
        }

        // ---- 1. Pasangan outlet + produk di Master Harga Jual ----
        $pairs = OutletPrice::query()
            ->join('products', 'products.id', '=', 'outlet_prices.product_id')
            ->whereNull('products.deleted_at')
            ->when($outletIds, fn ($q) => $q->whereIn('outlet_prices.outlet_id', $outletIds))
            ->when($productIds, fn ($q) => $q->whereIn('outlet_prices.product_id', $productIds))
            ->orderBy('outlet_prices.outlet_id')->orderBy('outlet_prices.product_id')
            ->get([
                'outlet_prices.id as price_id', 'outlet_prices.outlet_id', 'outlet_prices.product_id',
                'products.code', 'products.name', 'products.konversi_qty',
            ])
            ->groupBy(fn ($r) => $r->outlet_id . ':' . $r->product_id);

        $this->info($pairs->count() . ' pasangan outlet + produk terdaftar di Master Harga Jual.');

        // ---- 2. Yang HPP tujuan penulisannya (batch terbaru / harga_beli) sekarang 0, berapapun stoknya ----
        $candidates = [];
        foreach ($pairs->groupBy(fn ($g) => $g->first()->outlet_id) as $outletId => $groups) {
            foreach ($groups->chunk(300) as $chunk) {
                $hpp = $this->targetHpp((int) $outletId, $chunk->map(fn ($g) => $g->first()->product_id)->all());
                foreach ($chunk as $group) {
                    $first = $group->first();
                    if (($hpp[$first->product_id] ?? 0.0) >= 0.005) {
                        continue;
                    }
                    $candidates[] = [
                        'outlet_id' => (int) $first->outlet_id,
                        'product_id' => (int) $first->product_id,
                        'code' => $first->code,
                        'name' => $first->name,
                        'konversi' => (float) $first->konversi_qty,
                        'price_ids' => $group->pluck('price_id')->map(fn ($id) => (int) $id)->all(),
                    ];
                }
            }
        }

        $this->info(count($candidates) . ' pasangan dengan HPP 0 (berapapun stoknya).');
        if (! $candidates) {
            return self::SUCCESS;
        }

        // ---- 3. Cari HPP pengganti dari riwayat ----
        $rows = [];
        foreach (collect($candidates)->groupBy('outlet_id') as $outletId => $list) {
            foreach ($list->chunk(300) as $chunk) {
                foreach ($this->findEvidence((int) $outletId, $chunk->values()->all(), $latest) as $row) {
                    $rows[] = $row;
                }
            }
        }

        $restore = array_values(array_filter($rows, fn ($r) => $r['status'] === 'RESTORE'));
        $skipped = array_values(array_filter($rows, fn ($r) => $r['status'] !== 'RESTORE'));

        // ---- Laporan ----
        $csv = $this->writeCsv($rows);

        if ($restore) {
            $this->info(count($restore) . ' pasangan bisa dipulihkan:');
            $this->table(
                ['Outlet', 'Kode', 'Produk', 'HPP sekarang', 'HPP pengganti', 'Sumber', 'Tanggal', 'Ditulis ke'],
                array_map(fn ($r) => [
                    $r['outlet_id'], $r['code'], $r['name'], '0', $this->rp($r['value']), $r['source'], $r['at'], $r['target'],
                ], array_slice($restore, 0, 30))
            );
            if (count($restore) > 30) {
                $this->line('... (' . (count($restore) - 30) . ' baris lainnya ada di CSV)');
            }
        }

        if ($skipped) {
            $this->warn(count($skipped) . ' pasangan dilewati (tidak ditebak):');
            $this->table(
                ['Outlet', 'Kode', 'Produk', 'Alasan'],
                array_map(fn ($r) => [$r['outlet_id'], $r['code'], $r['name'], $r['reason']], array_slice($skipped, 0, 30))
            );
            if (count($skipped) > 30) {
                $this->line('... (' . (count($skipped) - 30) . ' baris lainnya ada di CSV)');
            }
        }

        foreach (collect($skipped)->groupBy('reason') as $reason => $group) {
            $this->line('  - ' . $group->count() . ' dilewati: ' . $reason);
        }
        $this->info('Dipulihkan: ' . count($restore) . ' | Dilewati: ' . count($skipped) . ' | CSV: ' . $csv);
        $this->line('Catatan: bila outlet tidak punya batch, penulisan ke products.harga_beli berlaku untuk semua outlet yang memakainya sebagai HPP cadangan.');

        if (! $restore) {
            return self::SUCCESS;
        }

        if (! $apply) {
            $this->warn('Dry run selesai. Periksa CSV, backup database, lalu jalankan lagi dengan --apply.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Sudah backup database dan yakin menerapkan perubahan?')) {
            return self::FAILURE;
        }

        // ---- 4. Apply ----
        $done = [];
        $already = [];

        DB::transaction(function () use ($restore, $latest, &$done, &$already) {
            foreach ($restore as $r) {
                // Hitung ulang: penulisan sebelumnya (mis. products.harga_beli untuk outlet lain) bisa sudah mengisinya.
                if (($this->targetHpp($r['outlet_id'], [$r['product_id']])[$r['product_id']] ?? 0.0) >= 0.005) {
                    $already[] = ['outlet_id' => $r['outlet_id'], 'product_id' => $r['product_id']];

                    continue;
                }

                $value = $r['value'];
                $stock = $latest->stockFor($r['outlet_id'], $r['product_id']);

                HargaBeliGuard::allow('maintenance', function () use ($stock, $value, $r) {
                    if ($stock) {
                        $stock->update(['hpp' => $value]);

                        return;
                    }

                    $update = ['harga_beli' => $value];
                    if ($r['konversi'] >= 1) {
                        $update['harga_beli_besar'] = round($value * $r['konversi'], 2);
                    }
                    // Query builder tidak memicu event model; allow() dipasang eksplisit.
                    Product::whereKey($r['product_id'])->update($update);
                });

                // Riwayat HPP di Master Harga Jual (sama seperti syncLatestHpp).
                $price = OutletPrice::find($r['price_ids'][0]);
                if ($price) {
                    activity(self::HPP_LOG_NAME)
                        ->performedOn($price)
                        ->event('hpp_changed')
                        ->withProperties([
                            'outlet_id' => $r['outlet_id'],
                            'product_id' => $r['product_id'],
                            'old' => 0.0,
                            'new' => $value,
                            'target' => $stock ? 'batch' : 'product',
                            'batch_number' => $stock?->batch_number,
                            'restored_from' => $r['source'],
                        ])
                        ->log('HPP dipulihkan (hpp:restore-zero)');
                }

                $done[] = [
                    'outlet_id' => $r['outlet_id'], 'product_id' => $r['product_id'], 'code' => $r['code'],
                    'hpp' => $value, 'target' => $r['target'], 'source' => $r['source'],
                ];
            }
        });

        HargaBeliGuard::logMaintenance('hpp:restore-zero', 'HPP dipulihkan dari 0', [
            'csv' => $csv,
            'dipulihkan' => $done,
            'sudah_terisi_dilewati' => $already,
        ]);

        $this->info(count($done) . ' pasangan dipulihkan' . ($already ? ', ' . count($already) . ' dilewati karena sudah terisi oleh penulisan sebelumnya' : '') . '.');

        return self::SUCCESS;
    }

    /**
     * Untuk satu outlet: cari HPP pengganti tiap kandidat. Mengembalikan satu baris laporan per kandidat.
     *
     * @param  array<int, array>  $list
     * @return array<int, array>
     */
    private function findEvidence(int $outletId, array $list, LatestHpp $latest): array
    {
        $productIds = array_column($list, 'product_id');
        $priceIds = array_merge(...array_column($list, 'price_ids'));

        // Batch outlet (termasuk yang sudah dihapus), tanpa batch MINUS.
        $batchProduct = OwnerStock::withTrashed()->notDeficit()
            ->where('owner_id', $outletId)->whereIn('product_id', $productIds)
            ->pluck('product_id', 'id');

        $batchActs = [];
        foreach ($batchProduct->keys()->chunk(1000) as $ids) {
            Activity::where('log_name', 'OwnerStock')
                ->where('subject_type', (new OwnerStock)->getMorphClass())
                ->whereIn('subject_id', $ids->all())
                ->get(['id', 'subject_id', 'event', 'created_at', 'properties'])
                ->each(function ($a) use (&$batchActs, $batchProduct) {
                    $batchActs[$batchProduct[$a->subject_id]][] = $a;
                });
        }

        $masterActs = [];
        Activity::where('log_name', self::HPP_LOG_NAME)
            ->where('subject_type', (new OutletPrice)->getMorphClass())
            ->whereIn('subject_id', $priceIds)
            ->get(['id', 'subject_id', 'event', 'created_at', 'properties'])
            ->each(function ($a) use (&$masterActs) {
                $masterActs[$a->subject_id][] = $a;
            });

        $productActs = [];
        Activity::where('log_name', 'Product')
            ->where('subject_type', (new Product)->getMorphClass())
            ->where('event', 'updated')
            ->whereIn('subject_id', $productIds)
            ->get(['id', 'subject_id', 'event', 'created_at', 'properties'])
            ->each(function ($a) use (&$productActs) {
                $productActs[$a->subject_id][] = $a;
            });

        $newestFirst = fn (array $acts) => collect($acts)
            ->sortByDesc(fn ($a) => sprintf('%s-%010d', $a->created_at?->format('YmdHis'), $a->id))
            ->values();

        $result = [];
        foreach ($list as $c) {
            $base = [
                'outlet_id' => $c['outlet_id'], 'product_id' => $c['product_id'], 'code' => $c['code'], 'name' => $c['name'],
                'konversi' => $c['konversi'], 'price_ids' => $c['price_ids'],
                'value' => null, 'source' => '', 'at' => '', 'target' => '', 'reason' => '',
            ];

            $batchList = $newestFirst($batchActs[$c['product_id']] ?? []);
            $masterList = $newestFirst(array_merge(...array_map(fn ($id) => $masterActs[$id] ?? [], $c['price_ids'])));
            $productList = $newestFirst($productActs[$c['product_id']] ?? []);

            // HPP sengaja dinolkan lewat Master Harga Jual (jalur resmi): bukan tertimpa, jangan dipulihkan.
            $lastMaster = $masterList->first();
            if (! $this->option('include-intentional') && $lastMaster
                && isset($lastMaster->properties['new']) && (float) $lastMaster->properties['new'] < 0.005) {
                $result[] = ['status' => 'SKIP', 'reason' => 'HPP dinolkan lewat Master Harga Jual (' . $lastMaster->created_at?->format('d/m/Y H:i') . '); pakai --include-intentional bila ini juga rusak'] + $base;

                continue;
            }

            $found = null;

            // 1. log batch: hpp X -> 0
            foreach ($batchList as $a) {
                $p = $a->properties->toArray();
                $old = $p['old']['hpp'] ?? $p['old']['harga_beli'] ?? null;
                $new = $p['attributes']['hpp'] ?? $p['attributes']['harga_beli'] ?? null;
                if ($a->event === 'updated' && $old !== null && $new !== null
                    && (float) $old >= 0.005 && (float) $new < 0.005) {
                    $found = [(float) $old, 'log batch #' . $a->subject_id . ' (hpp ' . $this->rp((float) $old) . ' -> 0)', $a->created_at];
                    break;
                }
            }

            // 2. riwayat Master Harga Jual: HPP > 0 terakhir
            if (! $found) {
                foreach ($masterList as $a) {
                    if ((float) ($a->properties['new'] ?? 0) >= 0.005) {
                        $found = [(float) $a->properties['new'], 'riwayat Master Harga Jual', $a->created_at];
                        break;
                    }
                }
            }

            // 3. HPP awal batch saat dibuat
            if (! $found) {
                foreach ($batchList as $a) {
                    $p = $a->properties->toArray();
                    $init = (float) ($p['attributes']['hpp'] ?? $p['attributes']['harga_beli'] ?? 0);
                    if ($a->event === 'created' && $init >= 0.005) {
                        $found = [$init, 'HPP awal batch #' . $a->subject_id . ' (log created)', $a->created_at];
                        break;
                    }
                }
            }

            // 4. log produk: harga_beli X -> 0
            if (! $found) {
                foreach ($productList as $a) {
                    $p = $a->properties->toArray();
                    if (isset($p['old']['harga_beli'], $p['attributes']['harga_beli'])
                        && (float) $p['old']['harga_beli'] >= 0.005 && (float) $p['attributes']['harga_beli'] < 0.005) {
                        $found = [(float) $p['old']['harga_beli'], 'log produk (harga_beli ' . $this->rp((float) $p['old']['harga_beli']) . ' -> 0)', $a->created_at];
                        break;
                    }
                }
            }

            // 5. HPP > 0 yang masih terbaca sistem (batch lama / harga_beli produk)
            if (! $found) {
                $shown = $latest->forProduct($c['outlet_id'], $c['product_id']);
                if ($shown >= 0.005) {
                    $found = [$shown, 'HPP batch lama / harga_beli produk (terbaca sistem)', null];
                }
            }

            // 6. harga beli > 0 terakhir di Pembelian Outlet
            if (! $found) {
                $purchase = DB::table('outlet_purchase_items as i')
                    ->join('outlet_purchases as p', 'p.id', '=', 'i.outlet_purchase_id')
                    ->whereNull('p.deleted_at')
                    ->where('p.outlet_id', $c['outlet_id'])
                    ->where('i.product_id', $c['product_id'])
                    ->where('i.harga_beli', '>', 0)
                    ->orderByDesc('p.purchase_date')->orderByDesc('i.id')
                    ->first(['i.harga_beli', 'p.purchase_date', 'p.code']);
                if ($purchase) {
                    $found = [(float) $purchase->harga_beli, 'Pembelian Outlet ' . $purchase->code, $purchase->purchase_date ? \Illuminate\Support\Carbon::parse($purchase->purchase_date) : null];
                }
            }

            // 7. cadangan terakhir: HPP > 0 produk ini di outlet mana pun / harga_beli produk
            if (! $found) {
                $global = $latest->forProductGlobal($c['product_id']);
                if ($global >= 0.005) {
                    $found = [$global, 'HPP produk di outlet lain / harga_beli produk (cadangan)', null];
                }
            }

            if (! $found) {
                $result[] = ['status' => 'SKIP', 'reason' => 'tidak ada riwayat HPP > 0 (selalu 0 atau tidak tercatat), isi manual lewat Master Harga Jual'] + $base;

                continue;
            }

            $stock = $latest->stockFor($c['outlet_id'], $c['product_id']);
            $result[] = [
                'status' => 'RESTORE',
                'value' => round($found[0], 2),
                'source' => $found[1],
                'at' => $found[2]?->format('d/m/Y H:i') ?? '-',
                'target' => $stock ? 'batch ' . ($stock->batch_number ?: '#' . $stock->id) : 'products.harga_beli',
            ] + $base;
        }

        return $result;
    }

    /**
     * HPP pada tujuan penulisan, per produk, TANPA melihat qty: batch terbaru yang tersedia (qty > 0, belum
     * kedaluwarsa), bila tidak ada batch terbaru apa pun (mis. stok 0), bila outlet tanpa batch = products.harga_beli.
     * Mengikuti LatestHpp::stockFor, tetapi tidak mengabaikan batch ber-HPP 0.
     *
     * @param  int[]  $productIds
     * @return array<int, float>
     */
    private function targetHpp(int $outletId, array $productIds): array
    {
        $batches = OwnerStock::where('owner_id', $outletId)
            ->whereIn('product_id', $productIds)
            ->notDeficit()
            ->orderByDesc('created_at')->orderByDesc('id')
            ->get(['id', 'product_id', 'hpp', 'qty', 'expired_at'])
            ->groupBy('product_id');
        $hargaBeli = Product::whereIn('id', $productIds)->pluck('harga_beli', 'id');
        $today = today()->toDateString();

        $result = [];
        foreach ($productIds as $id) {
            $list = $batches->get($id);
            if ($list && $list->isNotEmpty()) {
                $stock = $list->first(fn ($b) => (float) $b->qty > 0
                        && (! $b->expired_at || substr((string) $b->expired_at, 0, 10) >= $today))
                    ?? $list->first();
                $result[$id] = (float) $stock->hpp;
            } else {
                $result[$id] = (float) ($hargaBeli[$id] ?? 0);
            }
        }

        return $result;
    }

    private function writeCsv(array $rows): string
    {
        $dir = storage_path('app');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $path = $dir . '/hpp-restore-zero-' . now()->format('Ymd-His') . '.csv';
        $fh = fopen($path, 'w');
        fputcsv($fh, ['outlet_id', 'product_id', 'code', 'name', 'status', 'hpp_sekarang', 'hpp_pengganti', 'sumber', 'tanggal', 'ditulis_ke', 'alasan_dilewati']);
        foreach ($rows as $r) {
            fputcsv($fh, [
                $r['outlet_id'], $r['product_id'], $r['code'], $r['name'], $r['status'], 0,
                $r['value'] ?? '', $r['source'], $r['at'], $r['target'], $r['reason'],
            ]);
        }
        fclose($fh);

        return $path;
    }

    /** @return int[]|null null = ada token produk yang tidak ditemukan */
    private function resolveProducts(string $value): ?array
    {
        $tokens = collect(explode(',', $value))->map(fn ($v) => trim($v))->filter()->values();
        if ($tokens->isEmpty()) {
            return [];
        }

        $numeric = $tokens->filter(fn ($t) => ctype_digit($t))->map(fn ($t) => (int) $t)->all();
        $found = Product::withTrashed()
            ->where(fn ($q) => $q->whereIn('id', $numeric ?: [0])->orWhereIn('code', $tokens->all()))
            ->get(['id', 'code']);

        $missing = $tokens->reject(fn ($t) => $found->contains(fn ($p) => (string) $p->id === $t || (string) $p->code === $t));
        if ($missing->isNotEmpty()) {
            $this->error('Produk tidak ditemukan: ' . $missing->implode(', '));

            return null;
        }

        return $found->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return int[] */
    private function idList(?string $value): array
    {
        return collect(explode(',', (string) $value))
            ->map(fn ($v) => (int) trim($v))
            ->filter()
            ->values()
            ->all();
    }

    private function rp(float $n): string
    {
        return number_format($n, 2, ',', '.');
    }
}
