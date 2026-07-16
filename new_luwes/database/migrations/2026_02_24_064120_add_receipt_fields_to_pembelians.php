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
        Schema::table('pembelians', function (Blueprint $table) {
            $table->dateTime('receipt_date')->nullable()->after('total');
            $table->string('receipt_pic')->nullable()->after('receipt_date');
            $table->enum('receipt_status', ['draft', 'validated', 'completed'])->nullable()->default('draft')->after('receipt_pic');
            $table->string('receipt_photo')->nullable()->after('receipt_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pembelians', function (Blueprint $table) {
            $table->dropColumn('receipt_date');
            $table->dropColumn('receipt_pic');
            $table->dropColumn('receipt_status');
            $table->dropColumn('receipt_photo');
        });
    }
};
