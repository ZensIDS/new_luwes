<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengaman tambahan: kode invoice unik per outlet.
     *
     * Unik pada (outlet_id, code), bukan code saja, karena format lama
     * (INV000001) memang diulang per outlet. Penjualan marketplace
     * (outlet_id NULL) tidak ikut terkunci karena NULL tidak dianggap sama.
     * Baris soft-deleted ikut dihitung (nomornya memang sudah terpakai).
     *
     * Migration ini berhenti dengan pesan jelas bila data lama masih punya
     * kode ganda, supaya data bisa dirapikan dulu.
     */
    public function up(): void
    {
        $duplicates = DB::table('penjualans')
            ->select('outlet_id', 'code', DB::raw('COUNT(*) as total'))
            ->whereNotNull('outlet_id')
            ->whereNotNull('code')
            ->groupBy('outlet_id', 'code')
            ->havingRaw('COUNT(*) > 1')
            ->limit(10)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $sample = $duplicates
                ->map(fn ($row) => "outlet {$row->outlet_id} / {$row->code} ({$row->total}x)")
                ->implode(', ');

            throw new RuntimeException(
                'Masih ada kode penjualan ganda di tabel penjualans (contoh: '.$sample.'). '
                .'Rapikan dulu data tersebut sebelum menjalankan migration ini.'
            );
        }

        Schema::table('penjualans', function (Blueprint $table) {
            $table->unique(['outlet_id', 'code'], 'penjualans_outlet_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('penjualans', function (Blueprint $table) {
            $table->dropUnique('penjualans_outlet_code_unique');
        });
    }
};