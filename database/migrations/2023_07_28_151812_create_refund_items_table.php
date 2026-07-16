<?php

use App\Models\Penjualan;
use App\Models\Product;
use App\Models\Refund;
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
        Schema::create('refund_items', function (Blueprint $table) {
            $table->id();
            // $table->timestamp('tanggal')->nullable();
            // $table->foreignIdFor(Penjualan::class)->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Refund::class)->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Product::class)->nullable()->constrained()->cascadeOnDelete();
            // $table->string('satuan')->nullable();
            $table->integer('qty');
            // $table->bigInteger('price');
            // $table->bigInteger('discount');
            // $table->bigInteger('subtotal');
            $table->text('alasan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('refund_items');
    }
};
