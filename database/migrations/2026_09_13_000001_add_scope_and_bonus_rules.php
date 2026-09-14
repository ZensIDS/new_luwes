<?php

use App\Models\Outlet;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Voucher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('voucher_products')) {
            Schema::create('voucher_products', function (Blueprint $table) {
                $table->id();
                $table->foreignIdFor(Voucher::class)->constrained()->cascadeOnDelete();
                $table->foreignIdFor(Product::class)->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['voucher_id', 'product_id']);
            });
        }

        if (! Schema::hasTable('voucher_outlets')) {
            Schema::create('voucher_outlets', function (Blueprint $table) {
                $table->id();
                $table->foreignIdFor(Voucher::class)->constrained()->cascadeOnDelete();
                $table->foreignIdFor(Outlet::class)->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['voucher_id', 'outlet_id']);
            });
        }

        if (! Schema::hasTable('promotion_outlets')) {
            Schema::create('promotion_outlets', function (Blueprint $table) {
                $table->id();
                $table->foreignIdFor(Promotion::class)->constrained()->cascadeOnDelete();
                $table->foreignIdFor(Outlet::class)->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['promotion_id', 'outlet_id']);
            });
        }

        if (! Schema::hasTable('promotion_bonuses')) {
            Schema::create('promotion_bonuses', function (Blueprint $table) {
                $table->id();
                $table->foreignIdFor(Promotion::class)->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->unsignedInteger('qty')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('outlet_prices', 'disc_tambahan_type')) {
            Schema::table('outlet_prices', function (Blueprint $table) {
                $table->string('disc_tambahan_type')->nullable()->after('disc_brand_value');
                $table->decimal('disc_tambahan_value', 15, 2)->nullable()->after('disc_tambahan_type');
            });
        }

        if (! Schema::hasColumn('penjualan_items', 'disc_tambahan_type')) {
            Schema::table('penjualan_items', function (Blueprint $table) {
                $table->string('disc_tambahan_type')->nullable()->after('disc_brand_amount');
                $table->decimal('disc_tambahan_value', 15, 2)->nullable()->after('disc_tambahan_type');
                $table->decimal('disc_tambahan_amount', 15, 2)->default(0)->after('disc_tambahan_value');
                $table->decimal('outlet_surcharge', 15, 2)->default(0)->after('disc_toko_amount');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_bonuses');
        Schema::dropIfExists('promotion_outlets');
        Schema::dropIfExists('voucher_outlets');
        Schema::dropIfExists('voucher_products');

        if (Schema::hasColumn('outlet_prices', 'disc_tambahan_type')) {
            Schema::table('outlet_prices', function (Blueprint $table) {
                $table->dropColumn(['disc_tambahan_type', 'disc_tambahan_value']);
            });
        }

        if (Schema::hasColumn('penjualan_items', 'disc_tambahan_type')) {
            Schema::table('penjualan_items', function (Blueprint $table) {
                $table->dropColumn([
                    'disc_tambahan_type',
                    'disc_tambahan_value',
                    'disc_tambahan_amount',
                    'outlet_surcharge',
                ]);
            });
        }
    }
};
