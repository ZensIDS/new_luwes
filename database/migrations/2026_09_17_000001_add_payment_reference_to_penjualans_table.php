<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('penjualans', 'payment_reference')) {
            Schema::table('penjualans', function (Blueprint $table) {
                $table->string('payment_reference')->nullable()->after('payment_method_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('penjualans', 'payment_reference')) {
            Schema::table('penjualans', function (Blueprint $table) {
                $table->dropColumn('payment_reference');
            });
        }
    }
};
