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
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('kode_supplier')->nullable()->after('name');
            $table->string('pic_supplier')->nullable()->after('kode_supplier');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->string('satuan')->nullable()->after('brand');
            // $table->integer('min_stock')->default(0)->after('satuan');
            $table->string('lokasi')->nullable()->after('min_stock');

            // Remove unused supplier_id column if it exists
            if (Schema::hasColumn('products', 'supplier_id')) {
                $table->dropForeign(['supplier_id']); // if foreign key exists
                $table->dropColumn('supplier_id');
            }
        });
        Schema::create('product_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['product_id', 'supplier_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_supplier');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['satuan', 'min_stock', 'lokasi']);
            // Re-add supplier_id if needed (optional, not required for rollback)
            $table->unsignedBigInteger('supplier_id')->nullable()->after('category_id');
        });
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['kode_supplier', 'pic_supplier']);
        });
    }
};
