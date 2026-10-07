<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 9 todolist_optimize_query.md.
 *
 * Hanya menambah index yang terbukti dipakai query (berdasarkan hasil SHOW INDEX di DB):
 *  - products.code                       : scan barcode kasir, price checker, keranjang, orderBy('code')
 *  - stocks (product_id, deleted_at, qty): withSum('stocks','qty') dan SUM(qty) per produk
 *                                          (dashboard, stok minimum, daftar produk). Jadi covering index.
 *  - stocks.expired_at                   : near-expiry di dashboard (range + orderBy)
 *  - delivery_order_items (product_id, created_at): slow moving 90 hari (NOT EXISTS) di dashboard
 *
 * Aman dijalankan ulang: tiap index dicek dulu, dan dilewati bila sudah ada atau kolomnya tidak ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('products', ['code'], 'products_code_index');

        $stockColumns = Schema::hasColumn('stocks', 'deleted_at')
            ? ['product_id', 'deleted_at', 'qty']
            : ['product_id', 'qty'];
        $this->addIndex('stocks', $stockColumns, 'stocks_product_id_deleted_at_qty_index');

        $this->addIndex('stocks', ['expired_at'], 'stocks_expired_at_index');
        $this->addIndex('delivery_order_items', ['product_id', 'created_at'], 'delivery_order_items_product_id_created_at_index');
    }

    public function down(): void
    {
        $this->dropIndex('products', 'products_code_index');
        $this->dropIndex('stocks', 'stocks_product_id_deleted_at_qty_index');
        $this->dropIndex('stocks', 'stocks_expired_at_index');
        $this->dropIndex('delivery_order_items', 'delivery_order_items_product_id_created_at_index');
    }

    private function addIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        if ($this->indexExists($table, $name) || $this->indexCoversColumns($table, $columns)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->index($columns, $name);
        });
    }

    private function dropIndex(string $table, string $name): void
    {
        if (Schema::hasTable($table) && $this->indexExists($table, $name)) {
            Schema::table($table, function (Blueprint $blueprint) use ($name) {
                $blueprint->dropIndex($name);
            });
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->contains(fn ($row) => $row->Key_name === $name);
    }

    /**
     * True bila sudah ada index (nama apa pun) dengan urutan kolom yang persis sama,
     * supaya tidak membuat index kembar bila hosting pernah diberi index manual.
     */
    private function indexCoversColumns(string $table, array $columns): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->groupBy('Key_name')
            ->contains(fn ($rows) => $rows->sortBy('Seq_in_index')->pluck('Column_name')->values()->all() === $columns);
    }
};