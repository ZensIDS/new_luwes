<?php

namespace App\Console\Commands;

use App\Models\SetStockDraft;
use App\Services\SetStockDraftMerger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Gabungkan draft Set Stock aktif ganda per toko (akibat sinkron dua database).
 *
 *   php artisan set-stock:merge-drafts
 */
class MergeSetStockDrafts extends Command
{
    protected $signature = 'set-stock:merge-drafts';

    protected $description = 'Gabungkan draft Set Stock aktif ganda milik satu toko menjadi satu';

    public function handle(SetStockDraftMerger $merger): int
    {
        $outletIds = SetStockDraft::whereNotNull('active_outlet_id')
            ->groupBy('active_outlet_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('active_outlet_id');

        if ($outletIds->isEmpty()) {
            $this->info('Tidak ada draft aktif ganda.');

            return self::SUCCESS;
        }

        foreach ($outletIds as $outletId) {
            $merged = DB::transaction(fn () => $merger->mergeActive((int) $outletId));
            $this->info("Toko {$outletId}: {$merged} draft ganda digabung.");
        }

        return self::SUCCESS;
    }
}
