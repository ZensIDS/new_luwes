<?php

use App\Models\Outlet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('hpp', 15, 2)->default(0)->after('harga_jual');
            $table->integer('min_stock')->default(0)->after('hpp');
            $table->enum('hpp_method', ['average', 'fifo'])->default('average')->after('min_stock');
            $table->decimal('stock_value', 15, 2)->default(0)->after('hpp_method');
        });

        Schema::table('stocks', function (Blueprint $table) {
            // $table->dropForeign(['outlet_id']);
            // $table->dropColumn('outlet_id'); // Remove outlet ownership
            $table->integer('qty_reserved')->nullable()->default(0)->after('qty');
            $table->integer('qty_available')->nullable()->storedAs('qty - qty_reserved');
            $table->string('batch_number')->nullable()->after('serial_number');
            $table->string('location')->nullable()->after('condition');
            $table->enum('stock_status', ['available', 'reserved', 'damaged', 'expired'])->default('available');
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignIdFor(Outlet::class)->nullable()->constrained()->cascadeOnDelete();
            $table->dropColumn('hpp');
            $table->dropColumn('min_stock');
            $table->dropColumn('hpp_method');
            $table->dropColumn('stock_value');
            $table->dropColumn('qty_reserved');
            $table->dropColumn('qty_available');
            $table->dropColumn('batch_number');
            $table->dropColumn('location');
            $table->dropColumn('stock_status');
        });
    }
};
