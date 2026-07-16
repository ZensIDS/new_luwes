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
        Schema::create('owner_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained('outlets');
            $table->foreignId('product_id')->nullable()->constrained();
            $table->foreignId('stock_id')->nullable()->constrained('stocks');
            $table->integer('qty')->default(0);
            $table->string('batch_number')->nullable();
            $table->date('expired_at')->nullable();
            $table->decimal('hpp', 15, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['owner_id', 'product_id', 'batch_number']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('owner_stocks');
    }
};
