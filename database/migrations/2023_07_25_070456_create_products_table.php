<?php

use App\Models\Category;
use App\Models\Outlet;
use App\Models\Supplier;
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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('pic')->nullable();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->foreignIdFor(Category::class)->nullable()->constrained()->cascadeOnDelete();
            // $table->date('expired')->nullable();
            $table->text('desc')->nullable();
            $table->string('warna')->nullable();
            $table->string('ukuran')->nullable();
            $table->foreignIdFor(Outlet::class)->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Supplier::class)->nullable()->constrained()->cascadeOnDelete();
            $table->integer('harga_beli')->nullable();
            $table->integer('harga_jual')->nullable();
            $table->integer('diskon')->nullable();
            $table->integer('berat')->nullable();
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
        Schema::dropIfExists('products');
    }
};
