<?php

use App\Models\Outlet;
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
        Schema::create('penjualans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('customer_id')->nullable();
            $table->string('kasir_id')->nullable();
            $table->foreignIdFor(Outlet::class)->nullable()->constrained()->cascadeOnDelete();
            $table->bigInteger('discount')->nullable()->default(20);
            $table->bigInteger('total')->nullable()->default(20);
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
        Schema::dropIfExists('penjualans');
    }
};
