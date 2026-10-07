<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 7 todolist_optimize_query.md.
 *
 * Laporan barang masuk/keluar/aktivitas memfilter dan mengurutkan `stock_movements.created_at`
 * saja (rentang tanggal, tanpa owner_id/product_id). Index yang sudah ada
 * `(owner_id, product_id, created_at)` tidak bisa dipakai untuk pola itu.
 *
 * Aman dijalankan ulang: dilewati bila index (nama apa pun) dengan kolom yang sama sudah ada.
 */
return new class extends Migration
{
    private const TABLE = 'stock_movements';

    private const INDEX = 'stock_movements_created_at_index';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, 'created_at')) {
            return;
        }

        $alreadyIndexed = collect(DB::select('SHOW INDEX FROM `'.self::TABLE.'`'))
            ->groupBy('Key_name')
            ->contains(fn ($rows) => $rows->sortBy('Seq_in_index')->pluck('Column_name')->values()->all() === ['created_at']);

        if ($alreadyIndexed) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->index('created_at', self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $exists = collect(DB::select('SHOW INDEX FROM `'.self::TABLE.'`'))
            ->contains(fn ($row) => $row->Key_name === self::INDEX);

        if ($exists) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex(self::INDEX);
            });
        }
    }
};