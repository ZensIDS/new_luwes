<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->string('sku')->nullable()->after('product_id');
            $table->index(['product_id', 'sku']);
        });

        Schema::table('stock_pembelians', function (Blueprint $table) {
            $table->string('sku')->nullable()->after('product_id');
        });

        Schema::table('pembelians', function (Blueprint $table) {
            $table->string('code_gr')->nullable()->after('code');
        });

        Schema::table('owner_stocks', function (Blueprint $table) {
            $table->renameColumn('batch_number', 'sku');
            $table->renameColumn('hpp', 'harga_beli');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('hpp');
            $table->dropColumn('hpp_method');
            $table->dropColumn('brand');
            $table->dropColumn('berat');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
