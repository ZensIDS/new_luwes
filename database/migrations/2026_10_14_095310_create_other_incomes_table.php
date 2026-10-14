<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catatan Other Income.
     *
     * - outlet_id NULL = pemasukan umum (milik pemilik secara keseluruhan). Listing selalu umum.
     * - source_type + source_id terisi untuk income otomatis (Listing dari produk), NULL untuk input manual.
     * - UNIQUE (source_type, source_id, other_income_category_id): satu produk hanya bisa melahirkan
     *   satu Listing income seumur hidupnya, walau status bolak-balik atau tombol simpan diklik dua kali.
     *   (Baris manual punya source NULL, dan NULL tidak dianggap kembar oleh unique index MySQL.)
     * - product_name_snapshot: nama produk saat income tercatat, tetap utuh walau produk dihapus/diganti.
     */
    public function up(): void
    {
        if (Schema::hasTable('other_incomes')) {
            return;
        }

        Schema::create('other_incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->foreignId('other_income_category_id')->constrained('other_income_categories')->restrictOnDelete();
            $table->date('tanggal');
            $table->decimal('jumlah', 15, 2);
            $table->text('keterangan')->nullable();
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('product_name_snapshot')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tanggal', 'other_incomes_tanggal_index');
            $table->index(['outlet_id', 'tanggal'], 'other_incomes_outlet_tanggal_index');
            $table->unique(
                ['source_type', 'source_id', 'other_income_category_id'],
                'other_incomes_source_category_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('other_incomes');
    }
};