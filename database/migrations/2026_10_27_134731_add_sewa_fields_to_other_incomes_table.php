<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Data tambahan Pendapatan Lain kategori Sewa (semua nullable, baris lama tidak berubah):
     * - sewa_mulai / sewa_selesai : periode sewa
     * - supplier_id               : supplier penyewa
     * - product_id                : produk yang disewa (harus produk milik supplier tersebut, dicek di server)
     * Supplier / produk dihapus -> kolom jadi NULL; nama produk tetap ada di product_name_snapshot.
     */
    public function up(): void
    {
        Schema::table('other_incomes', function (Blueprint $table) {
            if (! Schema::hasColumn('other_incomes', 'sewa_mulai')) {
                $table->date('sewa_mulai')->nullable()->after('jumlah');
            }
            if (! Schema::hasColumn('other_incomes', 'sewa_selesai')) {
                $table->date('sewa_selesai')->nullable()->after('sewa_mulai');
            }
            if (! Schema::hasColumn('other_incomes', 'supplier_id')) {
                $table->foreignId('supplier_id')->nullable()->after('sewa_selesai')
                    ->constrained('suppliers')->nullOnDelete();
            }
            if (! Schema::hasColumn('other_incomes', 'product_id')) {
                $table->foreignId('product_id')->nullable()->after('supplier_id')
                    ->constrained('products')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('other_incomes', function (Blueprint $table) {
            foreach (['supplier_id', 'product_id'] as $column) {
                if (Schema::hasColumn('other_incomes', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
            foreach (['sewa_mulai', 'sewa_selesai'] as $column) {
                if (Schema::hasColumn('other_incomes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};