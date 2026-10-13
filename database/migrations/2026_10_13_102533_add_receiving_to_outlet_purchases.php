<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('outlet_purchase_items', 'qty_received')) {
            Schema::table('outlet_purchase_items', function (Blueprint $table) {
                // null = belum diterima; terisi saat penerimaan barang diselesaikan
                $table->integer('qty_received')->nullable()->after('qty');
            });
        }

        if (! Schema::hasColumn('outlet_purchases', 'received_at')) {
            Schema::table('outlet_purchases', function (Blueprint $table) {
                $table->foreignId('received_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
                $table->timestamp('received_at')->nullable()->after('received_by');
            });
        }

        // Data lama: stoknya sudah langsung masuk saat disimpan, jadi dianggap sudah diterima penuh.
        DB::table('outlet_purchase_items')
            ->whereNull('qty_received')
            ->whereIn('outlet_purchase_id', DB::table('outlet_purchases')->where('status', 'received')->select('id'))
            ->update(['qty_received' => DB::raw('qty')]);

        DB::table('outlet_purchases')
            ->where('status', 'received')
            ->whereNull('received_at')
            ->update(['received_at' => DB::raw('created_at'), 'received_by' => DB::raw('created_by')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('outlet_purchases', 'received_at')) {
            Schema::table('outlet_purchases', function (Blueprint $table) {
                $table->dropConstrainedForeignId('received_by');
                $table->dropColumn('received_at');
            });
        }

        if (Schema::hasColumn('outlet_purchase_items', 'qty_received')) {
            Schema::table('outlet_purchase_items', function (Blueprint $table) {
                $table->dropColumn('qty_received');
            });
        }
    }
};