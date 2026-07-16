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
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->foreignId('request_order_id')->nullable()->constrained();
            $table->foreignId('picking_list_id')->nullable()->constrained();
            $table->foreignId('owner_id')->nullable()->constrained('outlets');
            $table->foreignId('prepared_by')->nullable()->constrained('users');
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->date('delivery_date')->nullable();
            $table->date('received_date')->nullable();
            $table->enum('status', ['draft', 'sent', 'delivered', 'completed'])->default('draft');
            $table->text('notes')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('delivery_orders');
    }
};
