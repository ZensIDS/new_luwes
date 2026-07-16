<?php

use App\Models\Voucher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('penjualans', function (Blueprint $table) {
            $table->foreignIdFor(Voucher::class)->nullable()->constrained()->cascadeOnDelete();
            $table->softDeletes();
        });
        Schema::table('vouchers', function (Blueprint $table) {
            $table->string('kasir_id')->nullable();
        });
    }

    public function down()
    {
        Schema::table('penjualans', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
            $table->dropForeign(['voucher_id']);
            $table->dropColumn(['voucher_id']);
        });
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn('kasir_id');
        });
    }
};
