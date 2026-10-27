<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * db_sync_conflicts : salinan data baris KONFLIK (lokal vs hosting vs sinkron terakhir) dari sinkron
 *                     terakhir. Ditulis server lokal ke hosting, supaya dashboard hosting (yang tidak
 *                     bisa menjangkau database lokal) tetap bisa menampilkan "Lihat perbedaan".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('db_sync_conflicts', function (Blueprint $table) {
            $table->string('table_name', 64);
            $table->string('row_id', 64);
            $table->text('detail')->nullable();
            $table->longText('snapshot');
            $table->timestamp('detected_at')->nullable();

            $table->primary(['table_name', 'row_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('db_sync_conflicts');
    }
};
