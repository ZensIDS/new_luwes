<?php

use App\Models\Outlet;
use App\Models\Penjualan;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL may keep an earlier ALTER TABLE after a later DDL statement fails.
        // Keep this migration safe to retry after a partial first attempt.
        if (! Schema::hasColumn('vouchers', 'max_discount_amount')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->decimal('max_discount_amount', 15, 2)->nullable()->after('min_purchase');
            });
        }

        if (! Schema::hasColumn('vouchers', 'outlet_id')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->foreignIdFor(Outlet::class)->nullable()->after('max_discount_amount')->constrained()->nullOnDelete();
                $table->index(['outlet_id', 'start_at', 'end_at']);
            });
        }

        if (! Schema::hasTable('outlet_prices')) {
            Schema::create('outlet_prices', function (Blueprint $table) {
                $table->id();
                $table->foreignIdFor(Outlet::class)->constrained()->cascadeOnDelete();
                $table->foreignIdFor(Product::class)->constrained()->cascadeOnDelete();
                $table->string('disc_brand_type')->default('nominal');
                $table->decimal('disc_brand_value', 15, 2)->default(0);
                $table->string('margin_type')->default('percentage');
                $table->decimal('margin_value', 15, 2)->default(0);
                $table->string('disc_toko_type')->default('nominal');
                $table->decimal('disc_toko_value', 15, 2)->default(0);
                $table->date('effective_from')->nullable();
                $table->date('effective_until')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['outlet_id', 'product_id']);
                $table->index(['outlet_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('voucher_redemptions')) {
            Schema::create('voucher_redemptions', function (Blueprint $table) {
                $table->id();
                $table->foreignIdFor(Voucher::class)->constrained()->restrictOnDelete();
                $table->foreignIdFor(Penjualan::class)->constrained()->restrictOnDelete();
                $table->foreignIdFor(Outlet::class)->nullable()->constrained()->nullOnDelete();
                $table->foreignIdFor(User::class, 'cashier_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('code');
                $table->string('type');
                $table->decimal('value', 15, 2)->default(0);
                $table->decimal('amount', 15, 2)->default(0);
                $table->timestamps();

                $table->unique('voucher_id');
                $table->index(['code', 'outlet_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('outlet_prices');
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['outlet_id']);
            $table->dropIndex('vouchers_outlet_id_start_at_end_at_index');
            $table->dropColumn('outlet_id');
            $table->dropColumn('max_discount_amount');
        });
    }
};
