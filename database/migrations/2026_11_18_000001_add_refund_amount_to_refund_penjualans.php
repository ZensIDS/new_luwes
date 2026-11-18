<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Uang yang dikembalikan ke pembeli pada retur TANPA barang pengganti (0 untuk tukar barang biasa). */
    public function up(): void
    {
        Schema::table('refund_penjualans', function (Blueprint $table) {
            $table->decimal('refund_amount', 15, 2)->default(0)->after('difference');
        });
    }

    public function down(): void
    {
        Schema::table('refund_penjualans', function (Blueprint $table) {
            $table->dropColumn('refund_amount');
        });
    }
};
