<?php

use App\Models\OwnerStock;
use App\Models\Outlet;
use App\Models\StockAdjustment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Older installations renamed these OwnerStock columns to harga_beli/sku.
        // Restore the POS-facing names while preserving the existing values.
        $hasLegacyHargaBeli = Schema::hasColumn('owner_stocks', 'harga_beli');
        if (! Schema::hasColumn('owner_stocks', 'hpp')) {
            $afterColumn = $hasLegacyHargaBeli ? 'harga_beli' : 'qty';
            Schema::table('owner_stocks', function (Blueprint $table) use ($afterColumn) {
                $table->decimal('hpp', 15, 2)->nullable()->after($afterColumn);
            });
        }
        if ($hasLegacyHargaBeli) {
            DB::table('owner_stocks')
                ->whereNull('hpp')
                ->update(['hpp' => DB::raw('harga_beli')]);
        }

        $hasLegacySku = Schema::hasColumn('owner_stocks', 'sku');
        if (! Schema::hasColumn('owner_stocks', 'batch_number')) {
            $afterColumn = $hasLegacySku ? 'sku' : 'qty';
            Schema::table('owner_stocks', function (Blueprint $table) use ($afterColumn) {
                $table->string('batch_number')->nullable()->after($afterColumn);
                $table->index(['owner_id', 'product_id', 'batch_number']);
            });
        }
        if ($hasLegacySku) {
            DB::table('owner_stocks')
                ->whereNull('batch_number')
                ->update(['batch_number' => DB::raw('sku')]);
        }

        Schema::table('owner_stocks', function (Blueprint $table) {
            $table->string('source_type')->nullable()->after('hpp');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->foreignId('created_by')->nullable()->after('source_id')->constrained('users')->nullOnDelete();
            $table->index(['owner_id', 'product_id', 'qty']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('product_id')->constrained('outlets')->nullOnDelete();
            $table->foreignIdFor(OwnerStock::class)->nullable()->after('owner_id')->constrained()->nullOnDelete();
            $table->index(['owner_id', 'product_id', 'created_at']);
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('stock_id')->constrained('outlets')->nullOnDelete();
            $table->foreignIdFor(OwnerStock::class)->nullable()->after('owner_id')->constrained()->nullOnDelete();
            $table->index(['owner_id', 'product_id']);
        });

        Schema::table('user_cart', function (Blueprint $table) {
            $table->string('outlet_id')->nullable()->after('user_id');
            $table->foreignIdFor(OwnerStock::class)->nullable()->after('stock_id')->constrained()->nullOnDelete();
            $table->index(['user_id', 'outlet_id']);
        });

        Schema::table('user_wishlist', function (Blueprint $table) {
            $table->foreignIdFor(OwnerStock::class)->nullable()->after('qty')->constrained()->nullOnDelete();
        });

        Schema::table('penjualans', function (Blueprint $table) {
            $table->decimal('subtotal', 15, 2)->nullable()->after('total');
            $table->decimal('discount_total', 15, 2)->nullable()->after('subtotal');
            $table->decimal('voucher_total', 15, 2)->default(0)->after('discount_total');
            $table->decimal('grand_total', 15, 2)->nullable()->after('voucher_total');
            $table->decimal('paid_amount', 15, 2)->nullable()->after('grand_total');
            $table->decimal('change_amount', 15, 2)->nullable()->after('paid_amount');
            $table->foreignId('payment_method_id')->nullable()->after('change_amount')->constrained('payment_methods')->nullOnDelete();
            $table->string('payment_method_name')->nullable()->after('payment_method_id');
            $table->string('status')->default('paid')->after('payment_method_name');
            $table->index(['outlet_id', 'created_at']);
        });

        Schema::table('penjualan_items', function (Blueprint $table) {
            $table->foreignIdFor(OwnerStock::class)->nullable()->after('stock_id')->constrained()->nullOnDelete();
            $table->decimal('hpp', 15, 2)->nullable()->after('owner_stock_id');
            $table->decimal('harga_akhir', 15, 2)->nullable()->after('hpp');
            $table->string('disc_brand_type')->nullable()->after('harga_akhir');
            $table->decimal('disc_brand_value', 15, 2)->nullable()->after('disc_brand_type');
            $table->decimal('disc_brand_amount', 15, 2)->default(0)->after('disc_brand_value');
            $table->string('margin_type')->nullable()->after('disc_brand_amount');
            $table->decimal('margin_value', 15, 2)->nullable()->after('margin_type');
            $table->decimal('margin_amount', 15, 2)->default(0)->after('margin_value');
            $table->decimal('harga_aktif', 15, 2)->nullable()->after('margin_amount');
            $table->string('disc_toko_type')->nullable()->after('harga_aktif');
            $table->decimal('disc_toko_value', 15, 2)->nullable()->after('disc_toko_type');
            $table->decimal('disc_toko_amount', 15, 2)->default(0)->after('disc_toko_value');
            $table->index(['penjualan_id', 'owner_stock_id']);
        });
    }

    public function down(): void
    {
        Schema::table('penjualan_items', function (Blueprint $table) {
            $table->dropForeign(['owner_stock_id']);
            $table->dropColumn([
                'owner_stock_id', 'hpp', 'harga_akhir', 'disc_brand_type',
                'disc_brand_value', 'disc_brand_amount', 'margin_type',
                'margin_value', 'margin_amount', 'harga_aktif', 'disc_toko_type',
                'disc_toko_value', 'disc_toko_amount',
            ]);
        });

        Schema::table('penjualans', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
            $table->dropColumn([
                'subtotal', 'discount_total', 'voucher_total', 'grand_total',
                'paid_amount', 'change_amount', 'payment_method_id',
                'payment_method_name', 'status',
            ]);
        });

        Schema::table('user_wishlist', function (Blueprint $table) {
            $table->dropForeign(['owner_stock_id']);
            $table->dropColumn('owner_stock_id');
        });

        Schema::table('user_cart', function (Blueprint $table) {
            $table->dropForeign(['owner_stock_id']);
            $table->dropColumn(['outlet_id', 'owner_stock_id']);
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropForeign(['owner_stock_id']);
            $table->dropColumn(['owner_id', 'owner_stock_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropForeign(['owner_stock_id']);
            $table->dropColumn(['owner_id', 'owner_stock_id']);
        });

        Schema::table('owner_stocks', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['source_type', 'source_id', 'created_by']);
        });
    }
};
