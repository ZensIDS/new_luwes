<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_methods', 'is_fixed_amount')) {
                // true = uang diterima otomatis pas (total + biaya), kasir tidak mengisi nominal.
                $table->boolean('is_fixed_amount')->default(false)->after('desc');
            }
            if (! Schema::hasColumn('payment_methods', 'charge_percent')) {
                // Biaya tambahan (%) dari total belanja, ditanggung pelanggan.
                $table->decimal('charge_percent', 5, 2)->default(0)->after('is_fixed_amount');
            }
        });

        Schema::table('penjualans', function (Blueprint $table) {
            if (! Schema::hasColumn('penjualans', 'payment_fee_percent')) {
                $table->decimal('payment_fee_percent', 5, 2)->default(0)->after('payment_reference');
            }
            if (! Schema::hasColumn('penjualans', 'payment_fee_amount')) {
                // Snapshot biaya metode pembayaran saat transaksi (tidak ikut grand_total / omzet).
                $table->decimal('payment_fee_amount', 15, 2)->default(0)->after('payment_fee_percent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('penjualans', function (Blueprint $table) {
            $table->dropColumn(['payment_fee_percent', 'payment_fee_amount']);
        });
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn(['is_fixed_amount', 'charge_percent']);
        });
    }
};