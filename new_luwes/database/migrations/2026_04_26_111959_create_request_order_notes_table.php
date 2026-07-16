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
        Schema::create('request_order_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_order_id')->constrained()->cascadeOnDelete();
            $table->string('kategori');
            $table->integer('qty')->default(0);
            $table->string('nama_pj')->nullable();
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
        Schema::dropIfExists('request_order_notes');
    }
};
