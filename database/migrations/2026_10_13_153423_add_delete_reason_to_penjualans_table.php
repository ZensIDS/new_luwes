<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alasan dan pelaku penghapusan penjualan (soft delete), dipakai saat
     * penghapusan mengembalikan stok (SaleCancellationService).
     */
    public function up(): void
    {
        Schema::table('penjualans', function (Blueprint $table) {
            if (! Schema::hasColumn('penjualans', 'delete_reason')) {
                $table->string('delete_reason', 255)->nullable();
            }
            if (! Schema::hasColumn('penjualans', 'deleted_by')) {
                $table->unsignedBigInteger('deleted_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('penjualans', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('penjualans', 'delete_reason') ? 'delete_reason' : null,
                Schema::hasColumn('penjualans', 'deleted_by') ? 'deleted_by' : null,
            ]);
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};