<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sinkronisasi dua database (lokal <-> hosting) bisa membuat draft aktif toko yang sama di kedua sisi
     * (id berbeda karena auto-increment ganjil/genap). UNIQUE pada active_outlet_id membuat sinkron gagal
     * ("Duplicate entry '2' for key set_stock_drafts_active_outlet_id_unique"). Diganti index biasa;
     * draft ganda digabung otomatis oleh SetStockDraftMerger, pembuatan draft diserialkan dengan kunci baris outlet.
     */
    public function up(): void
    {
        Schema::table('set_stock_drafts', function (Blueprint $table) {
            $table->dropUnique('set_stock_drafts_active_outlet_id_unique');
            $table->index('active_outlet_id', 'set_stock_drafts_active_outlet_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('set_stock_drafts', function (Blueprint $table) {
            $table->dropIndex('set_stock_drafts_active_outlet_id_index');
            $table->unique('active_outlet_id', 'set_stock_drafts_active_outlet_id_unique');
        });
    }
};
