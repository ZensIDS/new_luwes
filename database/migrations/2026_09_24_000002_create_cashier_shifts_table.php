<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cashier_shifts')) {
            Schema::create('cashier_shifts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cashier_session_id')->constrained('cashier_sessions')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name', 150);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();

                $table->index(['cashier_session_id', 'started_at']);
                $table->index(['cashier_session_id', 'ended_at']);
            });
        }

        if (! Schema::hasColumn('penjualans', 'cashier_shift_id')) {
            Schema::table('penjualans', function (Blueprint $table) {
                $table->foreignId('cashier_shift_id')
                    ->nullable()
                    ->after('cashier_session_id')
                    ->constrained('cashier_shifts')
                    ->nullOnDelete();
                $table->index(['cashier_shift_id', 'created_at']);
            });
        }

        $this->backfillLegacyShifts();
    }

    public function down(): void
    {
        if (Schema::hasColumn('penjualans', 'cashier_shift_id')) {
            Schema::table('penjualans', function (Blueprint $table) {
                $table->dropForeign(['cashier_shift_id']);
                $table->dropIndex(['cashier_shift_id', 'created_at']);
                $table->dropColumn('cashier_shift_id');
            });
        }

        Schema::dropIfExists('cashier_shifts');
    }

    private function backfillLegacyShifts(): void
    {
        if (! Schema::hasColumn('cashier_sessions', 'opening_cashier_id')) {
            return;
        }

        DB::table('cashier_sessions')
            ->leftJoin('users', 'users.id', '=', 'cashier_sessions.opening_cashier_id')
            ->select([
                'cashier_sessions.id',
                'cashier_sessions.cashier_id',
                'cashier_sessions.status',
                'cashier_sessions.opened_at',
                'cashier_sessions.closed_at',
                'users.name as legacy_name',
            ])
            ->orderBy('cashier_sessions.id')
            ->get()
            ->each(function ($session): void {
                $shiftId = DB::table('cashier_shifts')->insertGetId([
                    'cashier_session_id' => $session->id,
                    'created_by' => $session->cashier_id,
                    'name' => $session->legacy_name ?: 'Kasir',
                    'started_at' => $session->opened_at,
                    'ended_at' => $session->status === 'closed' ? $session->closed_at : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('penjualans')
                    ->where('cashier_session_id', $session->id)
                    ->whereNull('cashier_shift_id')
                    ->update(['cashier_shift_id' => $shiftId]);
            });
    }
};
