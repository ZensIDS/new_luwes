<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('cashier_sessions', 'opening_cashier_id')) {
                $table->foreignIdFor(User::class, 'opening_cashier_id')
                    ->nullable()
                    ->after('cashier_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('cashier_sessions', 'next_cashier_id')) {
                $table->foreignIdFor(User::class, 'next_cashier_id')
                    ->nullable()
                    ->after('opening_cashier_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('cashier_sessions', 'closing_cashier_id')) {
                $table->foreignIdFor(User::class, 'closing_cashier_id')
                    ->nullable()
                    ->after('next_cashier_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cashier_sessions', function (Blueprint $table) {
            foreach (['opening_cashier_id', 'next_cashier_id', 'closing_cashier_id'] as $column) {
                if (Schema::hasColumn('cashier_sessions', $column)) {
                    $table->dropForeign([$column]);
                    $table->dropColumn($column);
                }
            }
        });
    }
};
