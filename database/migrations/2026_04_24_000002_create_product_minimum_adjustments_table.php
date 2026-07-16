<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_minimum_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('adjustment_percentage'); // e.g. 20 = +20%
            $table->date('active_from');
            $table->date('active_until')->nullable(); // null = open-ended
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'active_from', 'active_until'], 'idx_prod_adj_dates');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_minimum_adjustments');
    }
};
