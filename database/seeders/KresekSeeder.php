<?php

namespace Database\Seeders;

use App\Models\Kresek;
use Illuminate\Database\Seeder;

class KresekSeeder extends Seeder
{
    /**
     * Master kantong plastik awal. Aman dijalankan berulang: dicocokkan lewat nama,
     * harga diperbarui dan kresek yang pernah dihapus diaktifkan kembali.
     *
     * php artisan db:seed --class=KresekSeeder
     */
    public function run(): void
    {
        $kreseks = [
            ['name' => 'Kantong plastik No 1', 'price' => 100],
            ['name' => 'Kantong plastik No 2', 'price' => 300],
            ['name' => 'Kantong plastik No 3', 'price' => 500],
            ['name' => 'Kantong plastik No 4', 'price' => 600],
            ['name' => 'Kantong plastik No 5', 'price' => 1200],
            ['name' => 'Kantong plastik No 6', 'price' => 1700],
        ];

        foreach ($kreseks as $row) {
            $kresek = Kresek::withTrashed()->firstOrNew(['name' => $row['name']]);
            $kresek->price = $row['price'];
            $kresek->is_active = true;
            $kresek->deleted_at = null;
            $kresek->save();
        }
    }
}