<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repair product columns on databases whose migration history says the
     * product updates ran but whose physical table is still on the old shape.
     */
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $columns = [
            'brand' => static fn (Blueprint $table) => $table->string('brand')->nullable(),
            'model' => static fn (Blueprint $table) => $table->string('model')->nullable(),
            'is_serialized' => static fn (Blueprint $table) => $table->boolean('is_serialized')->default(false),
            'satuan' => static fn (Blueprint $table) => $table->string('satuan')->nullable(),
            'min_stock' => static fn (Blueprint $table) => $table->integer('min_stock')->default(0),
            'lokasi' => static fn (Blueprint $table) => $table->string('lokasi')->nullable(),
            'satuan_besar' => static fn (Blueprint $table) => $table->string('satuan_besar')->nullable(),
            'konversi_qty' => static fn (Blueprint $table) => $table->decimal('konversi_qty', 10, 2)->nullable(),
            'status_produk' => static fn (Blueprint $table) => $table->string('status_produk')->default('sudah'),
            'status_produk_note' => static fn (Blueprint $table) => $table->string('status_produk_note')->nullable(),
        ];

        foreach ($columns as $name => $definition) {
            if (! Schema::hasColumn('products', $name)) {
                Schema::table('products', $definition);
            }
        }
    }

    /**
     * This migration only repairs missing columns and intentionally does not
     * drop columns on rollback, which avoids deleting existing product data.
     */
    public function down(): void
    {
        // Intentionally left blank.
    }
};
