<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Draft belanja langsung (status "draft") dibuat sebelum supplier dipilih,
 * jadi outlet_purchases.supplier_id harus boleh NULL.
 *
 * Kolom status TIDAK diubah (string; default tetap 'received' karena data lama bergantung padanya),
 * sehingga setiap pembuatan draft wajib mengisi status secara eksplisit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlet_purchases', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });

        $this->setSupplierNullability(true);
        $this->restoreForeignKey();
    }

    public function down(): void
    {
        // Draft belum punya supplier dan belum berpengaruh ke stok/laporan: aman dibuang agar kolom bisa NOT NULL lagi.
        DB::table('outlet_purchase_items')
            ->whereIn('outlet_purchase_id', DB::table('outlet_purchases')->whereNull('supplier_id')->select('id'))
            ->delete();
        DB::table('outlet_purchases')->whereNull('supplier_id')->delete();

        Schema::table('outlet_purchases', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });

        $this->setSupplierNullability(false);
        $this->restoreForeignKey();
    }

    private function setSupplierNullability(bool $nullable): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement(sprintf(
                'ALTER TABLE outlet_purchases MODIFY supplier_id BIGINT UNSIGNED %s',
                $nullable ? 'NULL' : 'NOT NULL'
            ));

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement(sprintf(
                'ALTER TABLE outlet_purchases ALTER COLUMN supplier_id %s NOT NULL',
                $nullable ? 'DROP' : 'SET'
            ));

            return;
        }

        // SQLite deployments should include doctrine/dbal so Laravel can
        // rebuild the table while changing the nullable flag.
        Schema::table('outlet_purchases', function (Blueprint $table) use ($nullable) {
            $table->foreignId('supplier_id')->nullable($nullable)->change();
        });
    }

    private function restoreForeignKey(): void
    {
        Schema::table('outlet_purchases', function (Blueprint $table) {
            $table->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete();
        });
    }
};