<?php

use App\Models\Outlet;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlet_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignIdFor(Outlet::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Supplier::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('purchase_date');
            $table->string('invoice_number')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('status')->default('received');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['outlet_id', 'purchase_date']);
        });

        Schema::create('outlet_purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_stock_id')->nullable()->constrained('owner_stocks')->nullOnDelete();
            $table->integer('qty');
            $table->decimal('harga_beli', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->string('batch_number')->nullable();
            $table->date('expired_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_purchase_items');
        Schema::dropIfExists('outlet_purchases');
    }
};
