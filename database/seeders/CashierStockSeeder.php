<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Backwards-compatible entry point for the former focused seeder name.
 */
class CashierStockSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DemoDataSeeder::class);
    }
}
