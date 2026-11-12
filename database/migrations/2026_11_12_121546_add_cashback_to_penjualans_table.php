<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penjualans', function (Blueprint $table) {
            // Cashback diinput kasir: jumlah lembar + total nominal. Mengurangi total bayar persis seperti
            // voucher, tetapi tidak punya kode voucher (tidak ada baris voucher_redemptions).
            $table->unsignedInteger('cashback_qty')->default(0)->after('voucher_total');
            $table->decimal('cashback_total', 15, 2)->default(0)->after('cashback_qty');
        });
    }

    public function down(): void
    {
        Schema::table('penjualans', function (Blueprint $table) {
            $table->dropColumn(['cashback_qty', 'cashback_total']);
        });
    }
};