<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->json('deadline_days')->nullable()->after('no_telp');
            $table->unsignedTinyInteger('deadline_interval_weeks')->nullable()->after('deadline_days');
            $table->date('deadline_reference_date')->nullable()->after('deadline_interval_weeks');
        });
    }

    public function down()
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['deadline_days', 'deadline_interval_weeks', 'deadline_reference_date']);
        });
    }
};
