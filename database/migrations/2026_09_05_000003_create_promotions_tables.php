<?php

use App\Models\Outlet;
use App\Models\Penjualan;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('promotions')) {
            Schema::create('promotions', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->nullable()->unique();
                $table->string('type'); // flash_sale, bundle
                $table->string('discount_type')->default('percentage'); // percentage, nominal, fixed_price
                $table->decimal('discount_value', 15, 2)->default(0);
            // Legacy column name; for bundle promotions this stores the
            // nominal discount amount per bundle, not the final bundle price.
            $table->decimal('bundle_price', 15, 2)->nullable();
                $table->unsignedInteger('max_qty')->nullable();
                $table->unsignedInteger('quota_qty')->nullable();
                $table->unsignedInteger('used_qty')->default(0);
                $table->decimal('min_purchase', 15, 2)->default(0);
                $table->foreignIdFor(Outlet::class)->nullable()->constrained()->nullOnDelete();
                $table->dateTime('start_at')->nullable();
                $table->dateTime('end_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('stackable')->default(false);
                $table->unsignedInteger('priority')->default(100);
                $table->text('desc')->nullable();
                $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['outlet_id', 'is_active', 'start_at', 'end_at']);
                $table->index(['type', 'is_active']);
            });
        }

        if (! Schema::hasTable('promotion_products')) {
            Schema::create('promotion_products', function (Blueprint $table) {
                $table->id();
                $table->foreignIdFor(Promotion::class)->constrained()->cascadeOnDelete();
                $table->foreignIdFor(Product::class)->constrained()->cascadeOnDelete();
                $table->decimal('required_qty', 10, 2)->default(1);
                $table->timestamps();

                $table->unique(['promotion_id', 'product_id']);
            });
        }

        if (! Schema::hasTable('promotion_applications')) {
            Schema::create('promotion_applications', function (Blueprint $table) {
                $table->id();
                $table->foreignIdFor(Penjualan::class)->constrained()->restrictOnDelete();
                $table->foreignIdFor(Promotion::class)->nullable()->constrained()->nullOnDelete();
                $table->string('type');
                $table->string('name');
                $table->string('code')->nullable();
                $table->decimal('basis_amount', 15, 2)->default(0);
                $table->decimal('amount', 15, 2)->default(0);
                $table->decimal('quantity', 15, 2)->default(0);
                $table->json('details')->nullable();
                $table->timestamps();

                $table->index(['penjualan_id', 'promotion_id']);
            });
        }

        if (! Schema::hasColumn('penjualans', 'promotion_total')) {
            Schema::table('penjualans', function (Blueprint $table) {
                $table->decimal('promotion_total', 15, 2)->default(0)->after('discount_total');
            });
        }

        if (! Schema::hasColumn('penjualan_items', 'base_price')) {
            Schema::table('penjualan_items', function (Blueprint $table) {
                $table->decimal('base_price', 15, 2)->nullable()->after('price');
                $table->decimal('base_subtotal', 15, 2)->nullable()->after('subtotal');
                $table->decimal('promotion_discount', 15, 2)->default(0)->after('base_subtotal');
                $table->json('promotion_details')->nullable()->after('promotion_discount');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_applications');
        Schema::dropIfExists('promotion_products');
        Schema::dropIfExists('promotions');

        if (Schema::hasColumn('penjualan_items', 'base_price')) {
            Schema::table('penjualan_items', function (Blueprint $table) {
                $table->dropColumn(['base_price', 'base_subtotal', 'promotion_discount', 'promotion_details']);
            });
        }
        if (Schema::hasColumn('penjualans', 'promotion_total')) {
            Schema::table('penjualans', function (Blueprint $table) {
                $table->dropColumn('promotion_total');
            });
        }
    }
};
