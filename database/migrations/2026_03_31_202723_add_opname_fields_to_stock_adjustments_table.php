<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->decimal('system_qty', 10, 2)->nullable()->after('quantity');
            $table->decimal('physical_qty', 10, 2)->nullable()->after('system_qty');
            $table->string('reason')->nullable()->after('keterangan');
            $table->string('status')->default('Selesai')->after('reason');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropColumn('system_qty');
            $table->dropColumn('physical_qty');
            $table->dropColumn('reason');
            $table->dropColumn('status');
        });
    }
};
