<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Membuat nomor invoice kasir: INV-O{outlet}-K{user}-{yymmdd}-{urut}
 * contoh: INV-O2-K15-261013-0001
 *
 * Unik karena:
 *  1. identitas outlet + kasir ada di dalam nomor (dua kasir tidak pernah bentrok), dan
 *  2. urutan per (outlet, kasir, tanggal) diambil dari baris counter yang dikunci
 *     (SELECT ... FOR UPDATE), jadi satu kasir yang membuka dua tab/perangkat
 *     sekaligus tetap mendapat nomor berbeda.
 *
 * Panggil di dalam DB::transaction checkout: bila checkout dibatalkan (rollback),
 * counter ikut batal sehingga tidak ada nomor yang terlewat.
 */
class InvoiceNumberGenerator
{
    public function next(int $outletId, int $userId, ?Carbon $at = null): string
    {
        $at ??= now();
        $day = $at->toDateString();

        return DB::transaction(function () use ($outletId, $userId, $at, $day) {
            $row = $this->lockedRow($outletId, $userId, $day);

            if (! $row) {
                // Baris hari ini belum ada. insertOrIgnore aman bila dua request
                // membuatnya bersamaan; setelah itu baris dikunci ulang.
                DB::table('invoice_sequences')->insertOrIgnore([
                    'outlet_id' => $outletId,
                    'user_id' => $userId,
                    'tanggal' => $day,
                    'last_number' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $row = $this->lockedRow($outletId, $userId, $day);
            }

            $number = (int) $row->last_number + 1;

            DB::table('invoice_sequences')
                ->where('id', $row->id)
                ->update(['last_number' => $number, 'updated_at' => now()]);

            return sprintf('INV-O%d-K%d-%s-%04d', $outletId, $userId, $at->format('ymd'), $number);
        });
    }

    private function lockedRow(int $outletId, int $userId, string $day): ?object
    {
        return DB::table('invoice_sequences')
            ->where('outlet_id', $outletId)
            ->where('user_id', $userId)
            ->where('tanggal', $day)
            ->lockForUpdate()
            ->first();
    }
}