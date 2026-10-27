<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom aturan harga outlet boleh kosong (NULL = dianggap 0 / tipe bawaan oleh PriceCalculator),
 * supaya insert dengan nilai kosong (form lama, import, sinkron) tidak gagal
 * "Column 'disc_toko_value' cannot be null".
 */
return new class extends Migration
{
    private const COLUMNS = [
        'disc_brand_type' => ['string', 'nominal'],
        'disc_brand_value' => ['decimal', 0],
        'margin_type' => ['string', 'percentage'],
        'margin_value' => ['decimal', 0],
        'disc_toko_type' => ['string', 'nominal'],
        'disc_toko_value' => ['decimal', 0],
    ];

    public function up(): void
    {
        $this->alter(true);
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $column => [, $default]) {
            DB::table('outlet_prices')->whereNull($column)->update([$column => $default]);
        }

        $this->alter(false);
    }

    private function alter(bool $nullable): void
    {
        Schema::table('outlet_prices', function (Blueprint $table) use ($nullable) {
            foreach (self::COLUMNS as $column => [$type, $default]) {
                ($type === 'string' ? $table->string($column) : $table->decimal($column, 15, 2))
                    ->nullable($nullable)
                    ->default($default)
                    ->change();
            }
        });
    }
};
