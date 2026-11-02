<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Progres sinkron yang sedang berjalan (dibaca dashboard) + permintaan pembatalan.
 *
 * source           : asal sinkron: manual (tombol dashboard) | cli (artisan / scheduler)
 * scope            : modul / tabel / force yang dipakai, supaya "Ulangi Sinkron" memakai cakupan yang sama
 * total_tables     : jumlah tabel yang akan diproses (0 = masih menyiapkan)
 * done_tables      : tabel yang sudah tuntas
 * current_table    : tabel yang sedang diproses
 * rows_so_far      : baris yang sudah berubah sejauh ini
 * cancel_requested : diisi dashboard; proses sinkron berhenti dengan aman di batch berikutnya
 *
 * Jalankan di server lokal DAN hosting (daftar migrasi kedua sisi harus sama).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('db_sync_runs', function (Blueprint $table) {
            $table->string('source', 10)->default('cli')->after('node');
            $table->text('scope')->nullable()->after('source');
            $table->unsignedInteger('total_tables')->default(0)->after('conflict_count');
            $table->unsignedInteger('done_tables')->default(0)->after('total_tables');
            $table->string('current_table', 64)->nullable()->after('done_tables');
            $table->unsignedInteger('rows_so_far')->default(0)->after('current_table');
            $table->boolean('cancel_requested')->default(false)->after('rows_so_far');
        });
    }

    public function down(): void
    {
        Schema::table('db_sync_runs', function (Blueprint $table) {
            $table->dropColumn(['source', 'scope', 'total_tables', 'done_tables', 'current_table', 'rows_so_far', 'cancel_requested']);
        });
    }
};