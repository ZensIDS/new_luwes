<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('limit_discount')->nullable()->default(0);
            $table->softDeletes()->after('updated_at');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
        Schema::table('pembelians', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
        Schema::table('pembelian_products', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
        Schema::table('kas', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
        Schema::table('penjualan_items', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
        Schema::table('vouchers', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
        Schema::table('suppliers', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
        Schema::table('sliders', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
        Schema::table('refunds', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
        Schema::table('refund_items', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
        Schema::table('refund_pembelians', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
        Schema::table('refund_pembelian_items', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
        Schema::table('pengeluarans', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
        Schema::table('outlets', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
        Schema::table('banks', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
        Schema::table('reviews', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('limit_discount');
            $table->dropColumn('deleted_at');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
        Schema::table('pembelians', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
        Schema::table('pembelian_products', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
        Schema::table('kas', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
        Schema::table('penjualan_items', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
        Schema::table('sliders', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
        Schema::table('refund_items', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
        Schema::table('refund_pembelians', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
        Schema::table('refund_pembelian_items', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
        Schema::table('pengeluarans', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
        Schema::table('banks', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }
};
