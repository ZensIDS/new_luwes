<?php

use App\Models\Stock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStockIdAndSerialNumberToPenjualanItemsTable extends Migration
{
    public function up()
    {
        Schema::table('penjualan_items', function (Blueprint $table) {
            $table->foreignIdFor(Stock::class)->nullable()->constrained()->cascadeOnDelete();
            $table->string('serial_number')->nullable();
        });
    }

    public function down()
    {
        Schema::table('penjualan_items', function (Blueprint $table) {
            $table->dropForeign(['stock_id']);
            $table->dropColumn(['stock_id', 'serial_number']);
        });
    }
}
