<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('penjualans', 'cashier_session_id')) {
            Schema::table('penjualans', function (Blueprint $table) {
                $table->foreignId('cashier_session_id')
                    ->nullable()
                    ->after('kasir_id')
                    ->constrained('cashier_sessions')
                    ->nullOnDelete();
                $table->index(['cashier_session_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('penjualans', 'cashier_session_id')) {
            Schema::table('penjualans', function (Blueprint $table) {
                $table->dropForeign(['cashier_session_id']);
                $table->dropIndex(['cashier_session_id', 'created_at']);
                $table->dropColumn('cashier_session_id');
            });
        }
    }
};
