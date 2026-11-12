<?php

namespace App\Services;

use App\Models\SetStockDraft;
use Illuminate\Support\Facades\DB;

/**
 * Menggabung draft Set Stock AKTIF ganda milik satu toko (terjadi bila dua database yang disinkronkan
 * sama-sama membuat draft aktif untuk toko itu). Draft tertua dipertahankan; item draft lain dipindah.
 * Produk yang sama di dua draft: qty dari item yang terakhir diubah dipakai, scan_count dijumlahkan.
 *
 * Panggil di dalam DB::transaction milik pemanggil.
 */
class SetStockDraftMerger
{
    /** @return int jumlah draft ganda yang digabung (dihapus) */
    public function mergeActive(int $outletId): int
    {
        $drafts = SetStockDraft::where('active_outlet_id', $outletId)->orderBy('id')->lockForUpdate()->get();
        if ($drafts->count() < 2) {
            return 0;
        }

        $keep = $drafts->first();

        foreach ($drafts->slice(1) as $extra) {
            $items = DB::table('set_stock_draft_items')->where('draft_id', $extra->id)->get();

            foreach ($items as $item) {
                $existing = DB::table('set_stock_draft_items')
                    ->where('draft_id', $keep->id)
                    ->where('product_id', $item->product_id)
                    ->first();

                if (! $existing) {
                    DB::table('set_stock_draft_items')->where('id', $item->id)->update(['draft_id' => $keep->id]);

                    continue;
                }

                $update = [
                    'scan_count' => (int) $existing->scan_count + (int) $item->scan_count,
                    'last_scanned_at' => max((string) $existing->last_scanned_at, (string) $item->last_scanned_at) ?: null,
                ];
                if ((string) $item->updated_at > (string) $existing->updated_at) {
                    $update += [
                        'qty' => $item->qty,
                        'updated_by' => $item->updated_by,
                        'updated_at' => $item->updated_at,
                        'version' => (int) $existing->version + 1,
                    ];
                }
                DB::table('set_stock_draft_items')->where('id', $existing->id)->update($update);
                DB::table('set_stock_draft_items')->where('id', $item->id)->delete();
            }

            DB::table('set_stock_drafts')->where('id', $extra->id)->delete();
        }

        return $drafts->count() - 1;
    }
}
