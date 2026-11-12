<?php

namespace App\Console\Commands;

use App\Models\OwnerStock;
use App\Services\OutletStockService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Koreksi data Set Stock LAMA yang sudah terlanjur MENIMPA padahal seharusnya MENAMBAH
 * (produk yang sama di-set stock lagi < 72 jam dari Set Stock sebelumnya).
 *
 * Cara kerja:
 *  1. Untuk tiap toko + produk, urutkan draft Set Stock yang sudah selesai (saved_at) SEBELUM --before.
 *  2. Rantai = draft berurutan yang jaraknya <= --hours dari draft sebelumnya. Draft pertama dalam rantai
 *     memang menimpa (benar). Draft ke-2 dst seharusnya menambah.
 *  3. Stok sebelum draft ke-k (yang dulu "hilang" tertimpa) = qty input draft - perubahan bersih stok biasa
 *     pada draft itu (dari Kartu Stok; batch MINUS tidak dihitung).
 *  4. Total yang hilang per produk = jumlah dari (3) untuk draft ke-2 dst. Nilai ini DITAMBAHKAN ke stok
 *     saat ini (bukan di-set ulang), jadi penjualan yang terjadi sesudahnya tidak ikut terhapus.
 *     Dicatat sebagai StockAdjustment + Kartu Stok dengan catatan "Koreksi Set Stock 72 jam".
 *
 * Aman dijalankan ulang: rantai yang sudah dikoreksi (penanda draft terakhir di catatan Kartu Stok) dilewati.
 *
 * Default = simulasi. Pakai --apply untuk menulis. Backup database dulu sebelum --apply.
 * --before WAJIB diisi: waktu fitur "tambah < 72 jam" mulai live. Draft sesudahnya sudah menambah dengan benar.
 *
 * Contoh:
 *   php artisan set-stock:fix-additive --before="2026-11-13 12:00:00"            (simulasi)
 *   php artisan set-stock:fix-additive --before="2026-11-13 12:00:00" --apply
 *   php artisan set-stock:fix-additive --before="2026-11-13 12:00:00" --outlet=2 --apply
 */
class FixSetStockAdditive extends Command
{
    protected $signature = 'set-stock:fix-additive
        {--before= : WAJIB. Waktu fitur baru live (format Y-m-d H:i:s, zona waktu aplikasi). Hanya draft sebelum waktu ini diperiksa}
        {--hours=72 : Rentang jam yang dianggap set ulang (default 72)}
        {--outlet=* : Batasi ke outlet tertentu (ID outlet, boleh diulang). Default: semua outlet}
        {--apply : Tulis koreksi ke database (default: hanya simulasi)}
        {--force : Lewati pertanyaan konfirmasi saat --apply}';

    protected $description = 'Koreksi Set Stock lama yang menimpa padahal seharusnya menambah (set ulang < 72 jam)';

    public function handle(OutletStockService $stockService): int
    {
        if (! $this->option('before')) {
            $this->error('Opsi --before wajib, contoh: --before="2026-11-13 12:00:00" (waktu fitur baru live).');

            return self::FAILURE;
        }

        try {
            $before = Carbon::parse($this->option('before'));
        } catch (Throwable $e) {
            $this->error('Format --before tidak valid. Pakai Y-m-d H:i:s.');

            return self::FAILURE;
        }

        $hours = max(1, (int) $this->option('hours'));
        $apply = (bool) $this->option('apply');
        $outletIds = array_filter(array_map('intval', (array) $this->option('outlet')));

        $rows = DB::table('set_stock_draft_items as i')
            ->join('set_stock_drafts as d', 'd.id', '=', 'i.draft_id')
            ->where('d.status', 'done')
            ->whereNotNull('d.saved_at')
            ->where('d.saved_at', '<', $before)
            ->when($outletIds, fn ($q) => $q->whereIn('d.outlet_id', $outletIds))
            ->orderBy('d.outlet_id')
            ->orderBy('i.product_id')
            ->orderBy('d.saved_at')
            ->orderBy('d.id')
            ->get(['d.id as draft_id', 'd.outlet_id', 'd.saved_at', 'i.product_id', 'i.qty']);

        // Kelompokkan per toko + produk, lalu pecah menjadi rantai.
        $plans = [];
        foreach ($rows->groupBy(fn ($r) => $r->outlet_id . ':' . $r->product_id) as $group) {
            $chain = [];
            $prev = null;
            foreach ($group as $row) {
                $at = Carbon::parse($row->saved_at);
                if ($prev && $prev->diffInSeconds($at) > $hours * 3600) {
                    $this->collectPlan($plans, $chain);
                    $chain = [];
                }
                $chain[] = $row;
                $prev = $at;
            }
            $this->collectPlan($plans, $chain);
        }

        if (! $plans) {
            $this->info('Tidak ada Set Stock yang perlu dikoreksi.');

            return self::SUCCESS;
        }

        $display = array_map(fn ($p) => [
            $p['outlet_id'], $p['product_id'], $p['code'], mb_substr((string) $p['name'], 0, 28),
            count($p['chain']), implode(',', array_map(fn ($r) => '#' . $r->draft_id, $p['chain'])),
            $p['missing'], $p['current'], $p['current'] + $p['missing'],
        ], $plans);

        $this->table(
            ['Toko', 'ID Prod', 'Kode', 'Nama', 'Set', 'Draft', 'Tambah', 'Stok skrg', 'Stok baru'],
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
                    $marker = $this->marker($plan);

                    $stockService->adjust(
                        $primary,
                        $target,
                        today()->toDateString(),
                        'Koreksi Set Stock 72 jam',
                        null,
                        $marker . ' +' . $plan['missing'] . ' (set ulang < 72 jam seharusnya menambah)'
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

    /** Hitung kekurangan stok untuk satu rantai (>= 2 draft) dan simpan ke $plans bila belum dikoreksi. */
    private function collectPlan(array &$plans, array $chain): void
    {
        if (count($chain) < 2) {
            return;
        }

        $first = $chain[0];
        $last = end($chain);
        $outletId = (int) $first->outlet_id;
        $productId = (int) $first->product_id;

        $plan = [
            'outlet_id' => $outletId,
            'product_id' => $productId,
            'chain' => $chain,
            'last_draft' => (int) $last->draft_id,
        ];

        // Sudah pernah dikoreksi? (penanda draft terakhir rantai ada di Kartu Stok)
        $already = DB::table('stock_movements')
            ->where('owner_id', $outletId)
            ->where('product_id', $productId)
            ->where('notes', 'like', $this->marker($plan) . '%')
            ->exists();
        if ($already) {
            return;
        }

        $deficitIds = OwnerStock::withTrashed()->deficit()
            ->where('owner_id', $outletId)
            ->where('product_id', $productId)
            ->pluck('id')
            ->all();

        $missing = 0;
        foreach (array_slice($chain, 1) as $row) {
            $base = 'Set Stock toko (draft #' . $row->draft_id . ')';
            $net = (float) DB::table('stock_movements')
                ->where('owner_id', $outletId)
                ->where('product_id', $productId)
                ->where('type', 'adjustment')
                ->where(fn ($q) => $q->where('notes', $base)->orWhere('notes', 'like', $base . ' -%'))
                ->when($deficitIds, fn ($q) => $q->whereNotIn('owner_stock_id', $deficitIds))
                ->selectRaw('COALESCE(SUM(qty_in - qty_out), 0) as net')
                ->value('net');

            // Stok sebelum draft ini = qty input - perubahan bersih batch biasa. Tidak boleh negatif.
            $missing += max(0, (int) round((int) $row->qty - $net));
        }

        if ($missing <= 0) {
            return;
        }

        $info = DB::table('products')->where('id', $productId)->first(['code', 'name']);
        $plan['code'] = $info->code ?? ('#' . $productId);
        $plan['name'] = $info->name ?? '';
        $plan['missing'] = $missing;
        $plan['current'] = (int) OwnerStock::where('owner_id', $outletId)
            ->where('product_id', $productId)
            ->notDeficit()
            ->sum('qty');

        $plans[] = $plan;
    }

    private function marker(array $plan): string
    {
        return 'Koreksi Set Stock 72 jam (sampai draft #' . $plan['last_draft'] . ')';
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