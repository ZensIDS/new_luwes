<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel pendukung sinkronisasi database offline <-> hosting (lihat config/dbsync.php).
 *
 * db_sync_rows : snapshot setiap baris pada sinkron terakhir (hash per sisi + data).
 *                Dipakai untuk tahu sisi mana yang berubah / menghapus, dan sebagai
 *                nilai dasar saat menggabungkan perubahan dari dua sisi. Hanya terisi
 *                di server lokal.
 * db_sync_runs : riwayat sinkron. Ditulis di server lokal dan hosting, supaya
 *                dashboard hosting juga tahu kapan terakhir server lokal sinkron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('db_sync_rows', function (Blueprint $table) {
            $table->string('table_name', 64);
            $table->string('row_id', 64);
            $table->unsignedBigInteger('bucket');
            $table->char('local_hash', 32);
            $table->char('server_hash', 32);
            $table->longText('data');
            $table->timestamp('synced_at')->nullable();

            $table->primary(['table_name', 'row_id']);
            $table->index(['table_name', 'bucket']);
        });

        Schema::create('db_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('node', 20)->default('local');
            $table->string('status', 20);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('rows_changed')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('conflict_count')->default(0);
            $table->longText('summary')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('db_sync_runs');
        Schema::dropIfExists('db_sync_rows');
    }
};
