<?php

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cashier_sessions')) {
            Schema::create('cashier_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignIdFor(Outlet::class)->constrained()->cascadeOnDelete();
                $table->foreignIdFor(User::class, 'cashier_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('open');
                $table->decimal('opening_cash', 15, 2)->default(0);
                $table->decimal('closing_cash', 15, 2)->nullable();
                $table->decimal('expected_cash', 15, 2)->nullable();
                $table->decimal('cash_sales', 15, 2)->default(0);
                $table->decimal('cash_in', 15, 2)->default(0);
                $table->decimal('cash_out', 15, 2)->default(0);
                $table->decimal('cash_removed', 15, 2)->nullable();
                $table->decimal('carry_over_cash', 15, 2)->nullable();
                $table->decimal('discrepancy', 15, 2)->nullable();
                $table->text('opening_note')->nullable();
                $table->text('closing_note')->nullable();
                $table->timestamp('opened_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['outlet_id', 'cashier_id', 'status']);
                $table->index(['outlet_id', 'opened_at']);
            });
        }

        if (! Schema::hasTable('cashier_drawer_entries')) {
            Schema::create('cashier_drawer_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cashier_session_id')->constrained('cashier_sessions')->cascadeOnDelete();
                $table->foreignIdFor(Outlet::class)->constrained()->cascadeOnDelete();
                $table->foreignIdFor(User::class, 'cashier_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type');
                $table->decimal('amount', 15, 2);
                $table->decimal('expected_cash', 15, 2)->nullable();
                $table->decimal('difference', 15, 2)->nullable();
                $table->text('note')->nullable();
                $table->timestamp('recorded_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['cashier_session_id', 'type']);
                $table->index(['outlet_id', 'recorded_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_drawer_entries');
        Schema::dropIfExists('cashier_sessions');
    }
};
