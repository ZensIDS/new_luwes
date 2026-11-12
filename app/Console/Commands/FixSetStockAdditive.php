<?php

namespace App\Console\Commands;

use App\Models\OwnerStock;
use App\Services\OutletStockService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Koreksi data Set Stock yang MENIMPA padahal seharusnya MENAMBAH
 * (produk yang sama di-set stock lagi < 72 jam dari Set Stock sebelumnya).
 *
 * Data campuran (sebagian sistem lama, sebagian sistem baru) dikenali OTOMATIS per draft:
 *  - Draft yang sudah diproses sistem baru punya catatan Kartu Stok "... - ditambahkan +N (set ulang < 72 jam)"
 *    -> dianggap BENAR, dilewati.
 *  - Draft ke-2 dst dalam satu rantai (jarak <= --hours dari draft sebelumnya) TANPA catatan itu
 *    -> dulu menimpa, stok sebelumnya "hilang": perlu dikoreksi.
 *  - Draft yang sudah pernah dikoreksi command ini (penanda "Koreksi Set Stock 72 jam" di Kartu Stok) dilewati.
 *
 * Stok yang hilang per draft = qty input draft - perubahan bersih batch biasa pada draft itu (dari Kartu Stok;
 * batch MINUS tidak dihitung). Totalnya DITAMBAHKAN ke stok saat ini (bukan di-set ulang), jadi penjualan atau
 * Set Stock sistem baru yang terjadi sesudahnya tidak ikut terhapus.
 *
 * Default = simulasi. Pakai --apply untuk menulis. Backup database dulu sebelum --apply. Aman dijalankan ulang.
 *
 * Contoh:
 *   php artisan set-stock:fix-additive                         (simulasi, semua toko, otomatis)
 *   php artisan set-stock:fix-additive --detail                (tampilkan status tiap draft dalam rantai)
 *   php artisan set-stock:fix-additive --outlet=2 --apply
 *   php artisan set-stock:fix-additive --before="2026-11-13 12:00:00"   (opsional: batasi hanya draft sebelum waktu ini)
 */
class FixSetStockAdditive extends Command
{
    protected $signature = 'set-stock:fix-additive
        {--before= : Opsional. Hanya periksa draft yang disimpan sebelum waktu ini (Y-m-d H:i:s). Default: semua draft}
        {--hours=72 : Rentang jam yang dianggap set ulang (default 72)}
        {--outlet=* : Batasi ke outlet tertentu (ID outlet, boleh diulang). Default: semua outlet}
        {--detail : Tampilkan status tiap draft (benar / perlu koreksi / sudah dikoreksi)}
        {--apply : Tulis koreksi ke database (default: hanya simulasi)}
        {--force : Lewati pertanyaan konfirmasi saat --apply}';

    protected $description = 'Koreksi Set Stock yang menimpa padahal seharusnya menambah (set ulang < 72 jam); mendukung data campuran sistem lama dan baru';

    private const MARKER = 'Koreksi Set Stock 72 jam';

    public function handle(OutletStockService $stockService): int
    {
        $before = null;
        if ($this->option('before')) {
            try {
                $before = Carbon::parse($this->option('before'));
            } catch (Throwable $e) {
                $this->error('Format --before tidak valid. Pakai Y-m-d H:i:s.');

                return self::FAILURE;
            }
        }

        $hours = max(1, (int) $this->option('hours'));
        $apply = (bool) $this->option('apply');
        $detail = (bool) $this->option('detail');
        $outletIds = array_filter(array_map('intval', (array) $this->option('outlet')));

        $rows = DB::table('set_stock_draft_items as i')
            ->join('set_stock_drafts as d', 'd.id', '=', 'i.draft_id')
            ->where('d.status', 'done')
            ->whereNotNull('d.saved_at')
            ->when($before, fn ($q) => $q->where('d.saved_at', '<', $before))
            ->when($outletIds, fn ($q) => $q->whereIn('d.outlet_id', $outletIds))
            ->orderBy('d.outlet_id')
            ->orderBy('i.product_id')
            ->orderBy('d.saved_at')
            ->orderBy('d.id')
            ->get(['d.id as draft_id', 'd.outlet_id', 'd.saved_at', 'i.product_id', 'i.qty']);

        // Kelompokkan per toko + produk, lalu pecah menjadi rantai (jarak antar draft <= --hours).
        $plans = [];
        $detailRows = [];
        $stats = ['benar' => 0, 'dikoreksi' => 0, 'perlu' => 0, 'nol' => 0];

        foreach ($rows->groupBy(fn ($r) => $r->outlet_id . ':' . $r->product_id) as $group) {
            $chain = [];
            $prev = null;
            foreach ($group as $row) {
                $at = Carbon::parse($row->saved_at);
                if ($prev && $prev->diffInSeconds($at) > $hours * 3600) {
                    $this->collectPlan($plans, $detailRows, $stats, $chain);
                    $chain = [];
                }
                $chain[] = $row;
                $prev = $at;
            }
            $this->collectPlan($plans, $detailRows, $stats, $chain);
        }

        if ($detail && $detailRows) {
            $this->table(
                ['Toko', 'ID Prod', 'Kode', 'Draft', 'Disimpan', 'Qty input', 'Status', 'Kurang'],
                $detailRows
            );
        }

        $this->line(sprintf(
            'Draft set ulang < %d jam: %d sudah benar (sistem baru), %d sudah pernah dikoreksi, %d perlu koreksi, %d tidak ada selisih.',
            $hours, $stats['benar'], $stats['dikoreksi'], $stats['perlu'], $stats['nol']
        ));

        if (! $plans) {
            $this->info('Tidak ada Set Stock yang perlu dikoreksi.');

            return self::SUCCESS;
        }

        $display = array_map(fn ($p) => [
            $p['outlet_id'], $p['product_id'], $p['code'], mb_substr((string) $p['name'], 0, 28),
            implode(',', array_map(fn ($id) => '#' . $id, $p['fix_drafts'])),
            $p['missing'], $p['current'], $p['current'] + $p['missing'],
        ], $plans);

        $this->table(
            ['Toko', 'ID Prod', 'Kode', 'Nama', 'Draft dikoreksi', 'Tambah', 'Stok skrg', 'Stok baru'],
            $display
        );
        $this->info(count($plans) . ' produk perlu dikoreksi, total tambahan ' . array_sum(array_column($plans, 'missing')) . ' unit.');

        if (! $apply) {
            $this->warn('SIMULASI: belum ada yang ditulis. Jalankan lagi dengan --apply setelah backup database.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Terapkan koreksi stok di atas? Pastikan database sudah di-backup.')) {
            $this->warn('Dibatalkan.');

            return self::SUCCESS;
        }

        $done = 0;
        $skipped = [];
        foreach ($plans as $plan) {
            try {
                DB::transaction(function () use ($plan, $stockService, &$done, &$skipped) {
                    $batches = OwnerStock::where('owner_id', $plan['outlet_id'])
                        ->where('product_id', $plan['product_id'])
                        ->notDeficit()
                        ->lockForUpdate()
                        ->get();

                    if ($batches->isEmpty()) {
                        $skipped[] = $plan['code'] . ' (toko ' . $plan['outlet_id'] . '): tidak ada batch stok.';

                        return;
                    }

                    $primary = $this->pickPrimary($batches);
                    $target = (float) $primary->qty + $plan['missing'];

                    $stockService->adjust(
                        $primary,
                        $target,
                        today()->toDateString(),
                        self::MARKER,
                        null,
                        $this->marker($plan['fix_drafts']) . ' +' . $plan['missing'] . ' (set ulang < 72 jam seharusnya menambah)'
                    );
                    $done++;
                });
            } catch (Throwable $e) {
                $skipped[] = $plan['code'] . ' (toko ' . $plan['outlet_id'] . '): ' . $e->getMessage();
            }
        }

        $this->info($done . ' produk dikoreksi.');
        foreach ($skipped as $line) {
            $this->warn('Dilewati: ' . $line);
        }

        return $skipped ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Nilai satu rantai (>= 2 draft). Draft pertama memang menimpa (benar). Draft ke-2 dst diklasifikasi:
     * sistem baru (benar) / sudah dikoreksi / perlu koreksi. Rantai dengan kekurangan masuk $plans.
     */
    private function collectPlan(array &$plans, array &$detailRows, array &$stats, array $chain): void
    {
        if (count($chain) < 2) {
            return;
        }

        $first = $chain[0];
        $outletId = (int) $first->outlet_id;
        $productId = (int) $first->product_id;

        $info = DB::table('products')->where('id', $productId)->first(['code', 'name']);
        $code = $info->code ?? ('#' . $productId);

        [$correctedIds, $legacyMax] = $this->alreadyCorrected($outletId, $productId);

        $deficitIds = OwnerStock::withTrashed()->deficit()
            ->where('owner_id', $outletId)
            ->where('product_id', $productId)
            ->pluck('id')
            ->all();

        $missing = 0;
        $fixDrafts = [];

        foreach (array_slice($chain, 1) as $row) {
            $draftId = (int) $row->draft_id;
            $base = 'Set Stock toko (draft #' . $draftId . ')';
            $status = null;
            $lack = 0;

            if (DB::table('stock_movements')
                ->where('owner_id', $outletId)
                ->where('product_id', $productId)
                ->where('type', 'adjustment')
                ->where('notes', 'like', $base . ' - ditambahkan +%')
                ->exists()) {
                // Sistem baru sudah menambahkan saat draft ini disimpan.
                $status = 'BENAR (sistem baru)';
                $stats['benar']++;
            } elseif (isset($correctedIds[$draftId]) || $draftId <= $legacyMax) {
                $status = 'sudah dikoreksi';
                $stats['dikoreksi']++;
            } else {
                $net = (float) DB::table('stock_movements')
                    ->where('owner_id', $outletId)
                    ->where('product_id', $productId)
                    ->where('type', 'adjustment')
                    ->where(fn ($q) => $q->where('notes', $base)->orWhere('notes', 'like', $base . ' -%'))
                    ->when($deficitIds, fn ($q) => $q->whereNotIn('owner_stock_id', $deficitIds))
                    ->selectRaw('COALESCE(SUM(qty_in - qty_out), 0) as net')
                    ->value('net');

                // Stok sebelum draft ini = qty input - perubahan bersih batch biasa. Tidak boleh negatif.
                $lack = max(0, (int) round((int) $row->qty - $net));

                if ($lack > 0) {
                    $status = 'PERLU KOREKSI';
                    $stats['perlu']++;
                    $missing += $lack;
                    $fixDrafts[] = $draftId;
                } else {
                    $status = 'tidak ada selisih';
                    $stats['nol']++;
                }
            }

            $detailRows[] = [
                $outletId, $productId, $code, '#' . $draftId,
                Carbon::parse($row->saved_at)->format('d/m H:i'), (int) $row->qty, $status, $lack ?: '-',
            ];
        }

        if ($missing <= 0) {
            return;
        }

        $plans[] = [
            'outlet_id' => $outletId,
            'product_id' => $productId,
            'code' => $code,
            'name' => $info->name ?? '',
            'fix_drafts' => $fixDrafts,
            'missing' => $missing,
            'current' => (int) OwnerStock::where('owner_id', $outletId)
                ->where('product_id', $productId)
                ->notDeficit()
                ->sum('qty'),
        ];
    }

    /**
     * Draft yang sudah pernah dikoreksi, dibaca dari Kartu Stok.
     * Format baru: "Koreksi Set Stock 72 jam (draft #12,#15)". Format lama: "(sampai draft #15)" = semua draft <= 15.
     *
     * @return array{0:array<int,bool>,1:int}
     */
    private function alreadyCorrected(int $outletId, int $productId): array
    {
        $notes = DB::table('stock_movements')
            ->where('owner_id', $outletId)
            ->where('product_id', $productId)
            ->where('notes', 'like', self::MARKER . '%')
            ->pluck('notes');

        $ids = [];
        $legacyMax = 0;
        foreach ($notes as $note) {
            if (preg_match('/\(sampai draft #(\d+)\)/', $note, $m)) {
                $legacyMax = max($legacyMax, (int) $m[1]);

                continue;
            }
            if (preg_match('/\(draft ([#\d,]+)\)/', $note, $m)) {
                foreach (explode(',', str_replace('#', '', $m[1])) as $id) {
                    if ((int) $id > 0) {
                        $ids[(int) $id] = true;
                    }
                }
            }
        }

        return [$ids, $legacyMax];
    }

    private function marker(array $draftIds): string
    {
        return self::MARKER . ' (draft ' . implode(',', array_map(fn ($id) => '#' . $id, $draftIds)) . ')';
    }

    /** Sama seperti OutletStockService::pickPrimaryBatch: batch tersedia terbaru, kalau tidak ada batch terbaru apa pun. */
    private function pickPrimary($batches): OwnerStock
    {
        $sorted = $batches->sortByDesc(fn (OwnerStock $b) => [
            $b->created_at?->getTimestamp() ?? 0,
            (int) $b->id,
        ])->values();

        $available = $sorted->first(fn (OwnerStock $b) => (int) $b->qty > 0
            && (! $b->expired_at || $b->expired_at->gte(today())));

        return $available ?? $sorted->first();
    }
}