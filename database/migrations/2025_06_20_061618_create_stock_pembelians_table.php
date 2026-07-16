<?php

use App\Models\Pembelian;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('stock_pembelians', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Pembelian::class)->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Product::class)->nullable()->constrained()->cascadeOnDelete();
            $table->integer('harga_beli')->nullable();
            $table->integer('qty');
            $table->integer('subtotal')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('imei')->nullable();
            $table->enum('condition', ['new', 'used', 'refurbished'])->default('new');
            $table->enum('status', ['available', 'sent_to_outlet', 'reserved'])->default('available');
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_pembelians');
    }
};
