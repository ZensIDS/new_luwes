<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Harga beli bisa berdesimal (mis. Rp 16.666,67), tapi kolomnya sebelumnya INTEGER
 * sehingga desimalnya terpotong. Ubah jadi DECIMAL(15,2).
 * Menggunakan ALTER langsung (MySQL/MariaDB) agar tidak butuh doctrine/dbal.
 */
return new class extends Migration
{
    private array $columns = [
        'products'          => ['harga_beli' => 'NULL'],
        'pembelian_products' => ['harga_beli' => 'NOT NULL DEFAULT 0', 'subtotal' => 'NOT NULL DEFAULT 0'],
        'stock_pembelians'  => ['harga_beli' => 'NULL', 'subtotal' => 'NULL'],
        'stocks'            => ['harga_beli' => 'NULL', 'subtotal' => 'NULL'],
    ];

    public function up(): void
    {
        foreach ($this->columns as $table => $cols) {
            foreach ($cols as $col => $nullability) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $col)) {
                    DB::statement("ALTER TABLE `{$table}` MODIFY `{$col}` DECIMAL(15,2) {$nullability}");
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->columns as $table => $cols) {
            foreach ($cols as $col => $nullability) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $col)) {
                    DB::statement("ALTER TABLE `{$table}` MODIFY `{$col}` INT {$nullability}");
                }
            }
        }
    }
};