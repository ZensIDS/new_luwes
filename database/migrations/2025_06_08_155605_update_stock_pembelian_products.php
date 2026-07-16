<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('supplier_id');
            $table->string('model')->nullable()->after('brand');
            $table->boolean('is_serialized')->default(false)->after('model');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->string('serial_number')->nullable()->after('expired_at');
            $table->string('imei')->nullable()->after('serial_number');
            $table->enum('condition', ['new', 'used', 'refurbished'])->default('new')->after('imei');
        });

        Schema::table('pembelian_products', function (Blueprint $table) {
            $table->json('serial_numbers')->nullable()->after('expired_at');
        });

        Schema::table('user_cart', function (Blueprint $table) {
            $table->string('serial_number')->nullable()->after('qty');
            $table->unsignedBigInteger('stock_id')->nullable()->after('serial_number');
            $table->foreign('stock_id')->references('id')->on('stocks')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['brand', 'model', 'is_serialized']);
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn(['serial_number', 'imei', 'condition']);
        });

        Schema::table('pembelian_products', function (Blueprint $table) {
            $table->dropColumn('serial_numbers');
        });

        Schema::table('user_cart', function (Blueprint $table) {
            $table->dropForeign(['stock_id']);
            $table->dropColumn(['serial_number', 'stock_id']);
        });
    }
};
