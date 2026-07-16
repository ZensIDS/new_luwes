<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_pembelians', function (Blueprint $table) {
            $table->string('type')->default('gudang_ke_supplier')->after('tanggal');  // gudang_ke_supplier | outlet_ke_gudang
            $table->string('status')->default('retur')->after('type');               // retur | complete
        });

        Schema::table('refund_pembelian_items', function (Blueprint $table) {
            $table->unsignedBigInteger('stock_pembelian_id')->nullable()->after('product_id');
            $table->unsignedBigInteger('stock_id')->nullable()->after('stock_pembelian_id');
            $table->string('sku')->nullable()->after('stock_id');
            $table->decimal('harga', 15, 2)->default(0)->after('qty');
            $table->string('resolution')->nullable()->after('alasan'); // barang | uang
        });
    }

    public function down(): void
    {
        Schema::table('refund_pembelians', function (Blueprint $table) {
            $table->dropColumn(['type', 'status']);
        });

        Schema::table('refund_pembelian_items', function (Blueprint $table) {
            $table->dropColumn(['stock_pembelian_id', 'stock_id', 'sku', 'harga', 'resolution']);
        });
    }
};
