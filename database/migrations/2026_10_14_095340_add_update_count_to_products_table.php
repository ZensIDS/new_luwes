<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penghitung berapa kali produk disimpan lewat form Product (ProductController::update).
     * Dipakai aturan pemicu Other Income Listing: hanya create atau update PERTAMA yang boleh memicu.
     *
     * Produk yang sudah ada sebelum fitur ini diisi 2 supaya produk lama tidak pernah memicu.
     * Produk baru (setelah migrasi) mulai dari default 0.
     * `updated_at` tidak bisa dipakai karena ikut berubah oleh sinkron HPP, stok, dan proses lain.
     */
    public function up(): void
    {
        if (Schema::hasColumn('products', 'update_count')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('update_count')->default(0);
        });

        // DB::table() tidak memakai scope soft delete, jadi produk yang sudah dihapus ikut terisi.
        DB::table('products')->update(['update_count' => 2]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'update_count')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('update_count');
            });
        }
    }
};