<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('outlet_prices', 'note')) {
            Schema::table('outlet_prices', function (Blueprint $table) {
                $table->string('note', 500)->nullable()->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('outlet_prices', 'note')) {
            Schema::table('outlet_prices', function (Blueprint $table) {
                $table->dropColumn('note');
            });
        }
    }
};