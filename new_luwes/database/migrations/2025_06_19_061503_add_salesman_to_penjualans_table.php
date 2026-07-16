<?php

// use App\Models\Salesman;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('penjualans', function (Blueprint $table) {
            // $table->foreignIdFor(Salesman::class)->nullable()->constrained()->cascadeOnDelete();
            $table->string('salesman_id')->nullable()->after('voucher_id');
        });
    }

    public function down()
    {
        Schema::table('penjualans', function (Blueprint $table) {
            $table->dropForeign(['salesman_id']);
            $table->dropColumn(['salesman_id']);
        });
    }
};
