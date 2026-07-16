<?php

use App\Models\Outlet;
use App\Models\Pembelian;
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
        Schema::create('refund_pembelians', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->foreignIdFor(Pembelian::class)->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Outlet::class)->nullable()->constrained()->cascadeOnDelete();
            $table->string('customer_id')->nullable();
            $table->timestamp('tanggal')->nullable();
            $table->string('total')->nullable();
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
        Schema::dropIfExists('refund_pembelians');
    }
};
