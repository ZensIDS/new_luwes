<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda item penjualan yang dijual melebihi stok sistem (diambil dari batch MINUS).
     * Dipakai untuk laporan "Penjualan melebihi stok" dan tanda kuning di kasir.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('penjualan_items', 'is_oversold')) {
            Schema::table('penjualan_items', function (Blueprint $table) {
                $table->boolean('is_oversold')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('penjualan_items', 'is_oversold')) {
            Schema::table('penjualan_items', function (Blueprint $table) {
                $table->dropColumn('is_oversold');
            });
        }
    }
};