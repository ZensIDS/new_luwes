<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Counter nomor invoice per (outlet, kasir, tanggal).
     * Dipakai InvoiceNumberGenerator supaya nomor tidak pernah kembar,
     * walaupun satu kasir membuka kasir di dua tab/perangkat sekaligus.
     */
    public function up(): void
    {
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('user_id');
            $table->date('tanggal');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['outlet_id', 'user_id', 'tanggal'], 'invoice_sequences_outlet_user_tanggal_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
    }
};