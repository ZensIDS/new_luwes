<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kategori Other Income (pemasukan di luar penjualan kasir).
     *
     * - is_system = true  -> kategori sistem "Listing Product": tidak bisa dibuat/diubah nama/dihapus
     *                        lewat UI; hanya `nominal`-nya yang boleh diatur.
     * - is_system = false -> kategori manual buatan user (boleh dihapus, soft delete).
     *
     * `slug` sengaja TIDAK unique di database: kategori manual yang di-soft-delete lalu dibuat lagi
     * dengan nama sama tidak boleh bentrok. Kategori Listing selalu dicari lewat is_system + slug.
     */
    public function up(): void
    {
        if (! Schema::hasTable('other_income_categories')) {
            Schema::create('other_income_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->index();
                $table->boolean('is_system')->default(false);
                // Hanya dipakai kategori sistem Listing Product. Kosong = belum diatur.
                $table->decimal('nominal', 15, 2)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        $now = now();

        if (! DB::table('other_income_categories')->where('slug', 'listing-product')->where('is_system', true)->exists()) {
            DB::table('other_income_categories')->insert([
                'name' => 'Listing Product',
                'slug' => 'listing-product',
                'is_system' => true,
                'nominal' => null, // sengaja kosong: harus diatur sekali di awal (K10)
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('other_income_categories')->where('slug', 'sewa')->exists()) {
            DB::table('other_income_categories')->insert([
                'name' => 'Sewa',
                'slug' => 'sewa',
                'is_system' => false,
                'nominal' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('other_income_categories');
    }
};