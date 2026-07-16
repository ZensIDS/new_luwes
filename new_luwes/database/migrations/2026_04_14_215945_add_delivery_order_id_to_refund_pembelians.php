<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_pembelians', function (Blueprint $table) {
            // Add delivery_order_id for outlet_ke_gudang type
            // pembelian_id is no longer needed but kept for backward compatibility
            $table->foreignId('delivery_order_id')
                ->nullable()
                ->after('outlet_id')
                ->constrained('delivery_orders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('refund_pembelians', function (Blueprint $table) {
            $table->dropForeign(['delivery_order_id']);
            $table->dropColumn('delivery_order_id');
        });
    }
};
