<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $oldIndex = 'owner_stocks_owner_id_product_id_batch_number_unique';
    private string $newIndex = 'owner_stocks_owner_product_batch_unique';

    public function up(): void
    {
        // Pastikan tidak ada duplikat (owner, product, batch_number) sebelum membuat unique baru.
        // Saat --pretend, query SELECT tidak dijalankan sehingga hasilnya kosong (aman).
        $dupes = DB::table('owner_stocks')
            ->select('owner_id', 'product_id', 'batch_number', DB::raw('COUNT(*) as total'))
            ->whereNotNull('batch_number')
            ->groupBy('owner_id', 'product_id', 'batch_number')
            ->having('total', '>', 1)
            ->get();

        if ($dupes->isNotEmpty()) {
            throw new RuntimeException(
                'Masih ada duplikat owner_id+product_id+batch_number di owner_stocks: '
                . $dupes->toJson() . ' — rapikan dulu sebelum migrasi.'
            );
        }

        $row = DB::selectOne(
            "SELECT COUNT(*) AS c FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = 'owner_stocks' AND index_name = ?",
            [$this->oldIndex]
        );

        // Saat --pretend $row = null: anggap index lama ada agar SQL drop + add tetap tampil.
        $hasOld = ($row->c ?? 1) > 0;

        Schema::table('owner_stocks', function ($table) use ($hasOld) {
            if ($hasOld) {
                $table->dropUnique($this->oldIndex);
            }
            $table->unique(['owner_id', 'product_id', 'batch_number'], $this->newIndex);
        });
    }

    public function down(): void
    {
        Schema::table('owner_stocks', function ($table) {
            $table->dropUnique($this->newIndex);
        });
    }
};