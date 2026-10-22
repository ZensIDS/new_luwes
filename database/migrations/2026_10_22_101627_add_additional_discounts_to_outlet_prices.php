<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Diskon Tambahan 2, 3, 4 (bertingkat setelah Diskon Tambahan 1 = kolom disc_tambahan_*).
     * Default persen / 0 sehingga data lama tidak berubah hasil hitungnya.
     */
    public function up(): void
    {
        foreach ([2, 3, 4] as $n) {
            $previous = $n === 2 ? 'disc_tambahan_value' : 'disc_tambahan_'.($n - 1).'_value';

            if (! Schema::hasColumn('outlet_prices', "disc_tambahan_{$n}_type")) {
                Schema::table('outlet_prices', function (Blueprint $table) use ($n, $previous) {
                    $table->string("disc_tambahan_{$n}_type")->nullable()->default('percentage')->after($previous);
                });
            }

            if (! Schema::hasColumn('outlet_prices', "disc_tambahan_{$n}_value")) {
                Schema::table('outlet_prices', function (Blueprint $table) use ($n) {
                    $table->decimal("disc_tambahan_{$n}_value", 15, 2)->nullable()->default(0)->after("disc_tambahan_{$n}_type");
                });
            }
        }
    }

    public function down(): void
    {
        foreach ([4, 3, 2] as $n) {
            foreach (["disc_tambahan_{$n}_value", "disc_tambahan_{$n}_type"] as $column) {
                if (Schema::hasColumn('outlet_prices', $column)) {
                    Schema::table('outlet_prices', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }
};