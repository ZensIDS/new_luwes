<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah products.harga_beli_besar (harga beli per SATUAN BESAR, boleh kosong).
 *
 * - products.harga_beli tetap per SATUAN KECIL dan tetap jadi acuan hitung HPP / laba.
 * - harga_beli_besar hanya untuk ditampilkan; diisi otomatis = harga_beli x konversi_qty.
 * - products.harga_beli di migration awal bertipe INTEGER, sehingga hasil bagi (mis. 100.000 / 12 = 8.333,33)
 *   ikut terpotong. Di sini diubah ke DECIMAL(15,2) supaya harga satuan kecil hasil pembagian tidak hilang desimalnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) harga_beli: integer -> decimal(15,2)
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE products MODIFY harga_beli DECIMAL(15,2) NULL');
        } else {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('harga_beli', 15, 2)->nullable()->change();
            });
        }

        // 2) kolom harga satuan besar
        if (! Schema::hasColumn('products', 'harga_beli_besar')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('harga_beli_besar', 15, 2)->nullable()->after('harga_beli');
            });
        }

        // 3) isi produk lama yang sudah punya konversi: besar = kecil x konversi
        DB::table('products')
            ->whereNull('harga_beli_besar')
            ->whereNotNull('harga_beli')
            ->where('harga_beli', '>', 0)
            ->where('konversi_qty', '>=', 1)
            ->update(['harga_beli_besar' => DB::raw('ROUND(harga_beli * konversi_qty, 2)')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'harga_beli_besar')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('harga_beli_besar');
            });
        }
        // Tipe harga_beli sengaja tidak dikembalikan ke integer supaya tidak memotong desimal.
    }
};