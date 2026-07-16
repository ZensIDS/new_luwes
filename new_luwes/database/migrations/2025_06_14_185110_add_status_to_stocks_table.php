<?php

use App\Models\Stock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->string('status')->default('free')->after('condition'); // free or on_keep
            $table->softDeletes();
        });

        Schema::table('user_wishlist', function (Blueprint $table) {
            $table->foreignIdFor(Stock::class)->nullable()->constrained()->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->dropColumn('deleted_at');
        });

        Schema::table('user_wishlist', function (Blueprint $table) {
            $table->dropForeign(['stock_id']);
            $table->dropColumn(['stock_id']);
        });
    }
};
