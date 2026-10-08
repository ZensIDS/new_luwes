<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penjualans', function (Blueprint $table) {
            // Selisih pembulatan total ke atas (kelipatan 100). 0 untuk transaksi lama.
            $table->decimal('rounding_amount', 15, 2)->default(0)->after('voucher_total');
        });
    }

    public function down(): void
    {
        Schema::table('penjualans', function (Blueprint $table) {
            $table->dropColumn('rounding_amount');
        });
    }
};