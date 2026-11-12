<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kresek per penjualan: nama dan harga disalin (snapshot) agar nota lama tidak berubah
        // walau master kresek diubah/dihapus. Tidak ada pengurangan stok.
        Schema::create('penjualan_kreseks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penjualan_id')->constrained('penjualans')->cascadeOnDelete();
            $table->unsignedBigInteger('kresek_id')->nullable();
            $table->string('name');
            $table->decimal('price', 15, 2)->default(0);
            $table->unsignedInteger('qty');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index('kresek_id');
        });

        Schema::table('penjualans', function (Blueprint $table) {
            $table->decimal('kresek_total', 15, 2)->default(0)->after('voucher_total');
        });
    }

    public function down(): void
    {
        Schema::table('penjualans', function (Blueprint $table) {
            $table->dropColumn('kresek_total');
        });

        Schema::dropIfExists('penjualan_kreseks');
    }
};