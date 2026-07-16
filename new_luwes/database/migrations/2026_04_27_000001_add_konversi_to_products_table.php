<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('satuan_besar')->nullable()->after('satuan');
            $table->decimal('konversi_qty', 10, 2)->nullable()->after('satuan_besar');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['satuan_besar', 'konversi_qty']);
        });
    }
};
