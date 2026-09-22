<?php

use App\Models\Outlet;
use App\Models\Penjualan;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_penjualans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignIdFor(Penjualan::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(Outlet::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cashier_session_id')->nullable()->constrained('cashier_sessions')->nullOnDelete();
            $table->decimal('returned_total', 15, 2)->nullable();
            $table->decimal('replacement_total', 15, 2)->nullable();
            $table->decimal('difference', 15, 2)->default(0)->nullable();
            $table->string('payment_method_name')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['outlet_id', 'created_at']);
            $table->index(['penjualan_id', 'created_at']);
        });

        Schema::create('refund_penjualan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_penjualan_id')->constrained('refund_penjualans')->cascadeOnDelete();
            $table->string('type'); // return | replacement
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('penjualan_item_id')->nullable()->constrained('penjualan_items')->nullOnDelete();
            $table->foreignId('owner_stock_id')->nullable()->constrained('owner_stocks')->nullOnDelete();
            $table->unsignedInteger('qty')->nullable();
            $table->decimal('unit_price', 15, 2)->nullable();
            $table->decimal('subtotal', 15, 2)->nullable();
            $table->timestamps();

            $table->index(['type', 'penjualan_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_penjualan_items');
        Schema::dropIfExists('refund_penjualans');
    }
};
