<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('status_produk')->default('sudah')->after('lokasi');
            $table->string('status_produk_note')->nullable()->after('status_produk');
        });

        Schema::table('pembelians', function (Blueprint $table) {
            $table->enum('owner_approval_status', ['pending', 'approved', 'rejected'])
                ->default('pending')
                ->after('is_published');
            $table->foreignId('owner_approved_by')
                ->nullable()
                ->after('owner_approval_status')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('owner_approved_at')->nullable()->after('owner_approved_by');
            $table->text('owner_approval_note')->nullable()->after('owner_approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('pembelians', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_approved_by');
            $table->dropColumn([
                'owner_approval_status',
                'owner_approved_at',
                'owner_approval_note',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['status_produk', 'status_produk_note']);
        });
    }
};
