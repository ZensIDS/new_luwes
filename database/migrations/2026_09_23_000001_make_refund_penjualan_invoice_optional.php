<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_penjualans', function (Blueprint $table) {
            $table->dropForeign(['penjualan_id']);
        });

        $this->setInvoiceNullability(true);
        $this->restoreForeignKey();
    }

    public function down(): void
    {
        Schema::table('refund_penjualans', function (Blueprint $table) {
            $table->dropForeign(['penjualan_id']);
        });

        $this->setInvoiceNullability(false);
        $this->restoreForeignKey();
    }

    private function setInvoiceNullability(bool $nullable): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement(sprintf(
                'ALTER TABLE refund_penjualans MODIFY penjualan_id BIGINT UNSIGNED %s',
                $nullable ? 'NULL' : 'NOT NULL'
            ));

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement(sprintf(
                'ALTER TABLE refund_penjualans ALTER COLUMN penjualan_id %s NOT NULL',
                $nullable ? 'DROP' : 'SET'
            ));

            return;
        }

        // SQLite deployments should include doctrine/dbal so Laravel can
        // rebuild the table while changing the nullable flag.
        Schema::table('refund_penjualans', function (Blueprint $table) use ($nullable) {
            $table->foreignId('penjualan_id')->nullable($nullable)->change();
        });
    }

    private function restoreForeignKey(): void
    {
        Schema::table('refund_penjualans', function (Blueprint $table) {
            $table->foreign('penjualan_id')->references('id')->on('penjualans')->restrictOnDelete();
        });
    }
};
