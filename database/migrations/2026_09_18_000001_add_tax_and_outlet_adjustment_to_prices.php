<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('outlet_prices')) {
            if (! Schema::hasColumn('outlet_prices', 'pajak_type')) {
                Schema::table('outlet_prices', function (Blueprint $table) {
                    $table->string('pajak_type')->nullable()->after('disc_brand_value');
                    $table->decimal('pajak_value', 15, 2)->nullable()->after('pajak_type');
                });
            }

            if (! Schema::hasColumn('outlet_prices', 'outlet_adjustment_type')) {
                Schema::table('outlet_prices', function (Blueprint $table) {
                    $table->string('outlet_adjustment_type')->nullable()->after('disc_toko_value');
                    $table->decimal('outlet_adjustment_value', 15, 2)->nullable()->after('outlet_adjustment_type');
                });
            }
        }

        if (Schema::hasTable('penjualan_items')) {
            if (! Schema::hasColumn('penjualan_items', 'pajak_type')) {
                Schema::table('penjualan_items', function (Blueprint $table) {
                    $table->string('pajak_type')->nullable()->after('hpp');
                    $table->decimal('pajak_value', 15, 2)->nullable()->after('pajak_type');
                    $table->decimal('pajak_amount', 15, 2)->default(0)->after('pajak_value');
                    $table->decimal('hpp_setelah_pajak', 15, 2)->nullable()->after('pajak_amount');
                    $table->decimal('harga_dasar', 15, 2)->nullable()->after('disc_tambahan_amount');
                });
            }

            if (! Schema::hasColumn('penjualan_items', 'outlet_adjustment_type')) {
                Schema::table('penjualan_items', function (Blueprint $table) {
                    $table->string('outlet_adjustment_type')->nullable()->after('outlet_surcharge');
                    $table->decimal('outlet_adjustment_value', 15, 2)->nullable()->after('outlet_adjustment_type');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('penjualan_items')) {
            $columns = array_values(array_filter([
                Schema::hasColumn('penjualan_items', 'pajak_type') ? 'pajak_type' : null,
                Schema::hasColumn('penjualan_items', 'pajak_value') ? 'pajak_value' : null,
                Schema::hasColumn('penjualan_items', 'pajak_amount') ? 'pajak_amount' : null,
                Schema::hasColumn('penjualan_items', 'hpp_setelah_pajak') ? 'hpp_setelah_pajak' : null,
                Schema::hasColumn('penjualan_items', 'harga_dasar') ? 'harga_dasar' : null,
                Schema::hasColumn('penjualan_items', 'outlet_adjustment_type') ? 'outlet_adjustment_type' : null,
                Schema::hasColumn('penjualan_items', 'outlet_adjustment_value') ? 'outlet_adjustment_value' : null,
            ]));
            if ($columns) {
                Schema::table('penjualan_items', fn (Blueprint $table) => $table->dropColumn($columns));
            }
        }

        if (Schema::hasTable('outlet_prices')) {
            $columns = array_values(array_filter([
                Schema::hasColumn('outlet_prices', 'pajak_type') ? 'pajak_type' : null,
                Schema::hasColumn('outlet_prices', 'pajak_value') ? 'pajak_value' : null,
                Schema::hasColumn('outlet_prices', 'outlet_adjustment_type') ? 'outlet_adjustment_type' : null,
                Schema::hasColumn('outlet_prices', 'outlet_adjustment_value') ? 'outlet_adjustment_value' : null,
            ]));
            if ($columns) {
                Schema::table('outlet_prices', fn (Blueprint $table) => $table->dropColumn($columns));
            }
        }
    }
};
