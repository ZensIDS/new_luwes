<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu draft AKTIF per toko (dipakai bersama). active_outlet_id diisi outlet_id selama
        // draft aktif dan di-NULL-kan saat selesai, sehingga unique hanya berlaku untuk yang aktif.
        Schema::create('set_stock_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->unsignedBigInteger('active_outlet_id')->nullable()->unique();
            $table->string('status', 20)->default('active'); // active | done
            $table->string('save_token', 64)->nullable();
            $table->foreignId('saved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('saved_at')->nullable();
            $table->timestamps();

            $table->index(['outlet_id', 'status']);
        });

        Schema::create('set_stock_draft_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draft_id')->constrained('set_stock_drafts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('qty')->default(0);
            $table->unsignedInteger('scan_count')->default(1);
            // Dinaikkan tiap qty diubah: dipakai mendeteksi 2 orang mengubah item yang sama bersamaan.
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('last_scanned_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['draft_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('set_stock_draft_items');
        Schema::dropIfExists('set_stock_drafts');
    }
};