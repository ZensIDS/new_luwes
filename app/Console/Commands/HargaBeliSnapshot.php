<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Alat bantu verifikasi manual (todolist Tahap 7). HANYA MEMBACA, tidak menulis ke database.
 *
 * Mengambil "foto" harga beli / HPP, lalu membandingkannya setelah Anda mencoba fitur di aplikasi:
 *   products.harga_beli, products.harga_beli_besar, owner_stocks.hpp (semua baris, termasuk yang soft-delete).
 *
 *   php artisan harga-beli:snapshot                                  -> simpan foto ke storage/app/harga-beli-snapshot-<waktu>.csv
 *   php artisan harga-beli:snapshot --compare=storage/app/xxx.csv    -> tampilkan yang berubah sejak foto itu
 *   php artisan harga-beli:snapshot --product=12,ABC-001             -> batasi ke produk tertentu (id atau kode)
 */
class HargaBeliSnapshot extends Command
{
    protected $signature = 'harga-beli:snapshot
        {--compare= : Path CSV foto sebelumnya; bila diisi, command membandingkan alih-alih menyimpan}
        {--product= : Batasi ke id atau kode produk ini (pisahkan koma)}';

    protected $description = 'Foto harga beli / HPP (products, owner_stocks) dan bandingkan setelahnya (hanya baca)';

    public function handle(): int
    {
        $productIds = $this->resolveProducts((string) $this->option('product'));
        if ($productIds === null) {
            return self::FAILURE;
        }

        $current = $this->collect($productIds);

        if ($this->option('compare')) {
            return $this->compare((string) $this->option('compare'), $current);
        }

        $dir = storage_path('app');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir . '/harga-beli-snapshot-' . now()->format('Ymd-His') . '.csv';

        $fh = fopen($path, 'w');
        fputcsv($fh, ['tabel', 'id', 'keterangan', 'harga_beli', 'harga_beli_besar', 'hpp']);
        foreach ($current as $row) {
            fputcsv($fh, $row);
        }
        fclose($fh);

        $this->info(count($current) . ' baris disimpan: ' . $path);
        $this->line('Setelah mencoba fitur di aplikasi, bandingkan dengan: php artisan harga-beli:snapshot --compare=' . $path);

        return self::SUCCESS;
    }

    /** @return array<string, array> kunci "tabel:id" => baris */
    private function collect(array $productIds): array
    {
        $rows = [];

        $products = DB::table('products')->select('id', 'code', 'harga_beli', 'harga_beli_besar')->orderBy('id');
        if ($productIds) {
            $products->whereIn('id', $productIds);
        }
        foreach ($products->cursor() as $r) {
            $rows['products:' . $r->id] = ['products', $r->id, 'produk ' . $r->code, $this->num($r->harga_beli), $this->num($r->harga_beli_besar), ''];
        }

        $stocks = DB::table('owner_stocks')->select('id', 'owner_id', 'product_id', 'batch_number', 'hpp')->orderBy('id');
        if ($productIds) {
            $stocks->whereIn('product_id', $productIds);
        }
        foreach ($stocks->cursor() as $r) {
            $rows['owner_stocks:' . $r->id] = [
                'owner_stocks', $r->id, 'outlet ' . $r->owner_id . ' / produk ' . $r->product_id . ' / batch ' . ($r->batch_number ?? '-'),
                '', '', $this->num($r->hpp),
            ];
        }

        return $rows;
    }

    private function compare(string $path, array $current): int
    {
        if (! is_file($path)) {
            $this->error("File foto tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $old = [];
        $fh = fopen($path, 'r');
        fgetcsv($fh);
        while (($line = fgetcsv($fh)) !== false) {
            $old[$line[0] . ':' . $line[1]] = $line;
        }
        fclose($fh);

        $labels = [3 => 'harga_beli', 4 => 'harga_beli_besar', 5 => 'hpp'];
        $changes = [];
        $added = 0;
        $removed = 0;

        foreach ($current as $key => $row) {
            if (! isset($old[$key])) {
                $added++;
                $changes[] = [$row[0], $row[1], $row[2], 'BARU', '-', $this->show($row, $row[0] === 'products' ? 3 : 5)];

                continue;
            }
            foreach ($labels as $i => $label) {
                if (($old[$key][$i] ?? '') !== ($row[$i] ?? '')) {
                    $changes[] = [$row[0], $row[1], $row[2], $label, $old[$key][$i] === '' ? '-' : $old[$key][$i], $row[$i] === '' ? '-' : $row[$i]];
                }
            }
        }
        foreach (array_keys($old) as $key) {
            if (! isset($current[$key])) {
                $removed++;
            }
        }

        if (! $changes && ! $removed) {
            $this->info('Tidak ada perubahan harga beli / HPP sejak foto itu.');

            return self::SUCCESS;
        }

        $this->warn(count($changes) . ' perubahan nilai (' . $added . ' baris baru, ' . $removed . ' baris hilang dari foto):');
        $this->table(['Tabel', 'ID', 'Keterangan', 'Kolom', 'Sebelum', 'Sesudah'], array_slice($changes, 0, 50));
        if (count($changes) > 50) {
            $this->line('... (' . (count($changes) - 50) . ' perubahan lainnya tidak ditampilkan; batasi dengan --product=)');
        }

        return self::SUCCESS;
    }

    private function show(array $row, int $index): string
    {
        return $row[$index] === '' ? '-' : $row[$index];
    }

    private function num($value): string
    {
        return $value === null ? '' : number_format(round((float) $value, 2), 2, '.', '');
    }

    /** @return int[]|null null = ada token yang tidak ditemukan */
    private function resolveProducts(string $value): ?array
    {
        $tokens = collect(explode(',', $value))->map(fn ($v) => trim($v))->filter()->values();
        if ($tokens->isEmpty()) {
            return [];
        }

        $numeric = $tokens->filter(fn ($t) => ctype_digit($t))->map(fn ($t) => (int) $t)->all();
        $found = DB::table('products')
            ->where(fn ($q) => $q->whereIn('id', $numeric ?: [0])->orWhereIn('code', $tokens->all()))
            ->get(['id', 'code']);

        $missing = $tokens->reject(fn ($t) => $found->contains(fn ($p) => (string) $p->id === $t || (string) $p->code === $t));
        if ($missing->isNotEmpty()) {
            $this->error('Produk tidak ditemukan: ' . $missing->implode(', '));

            return null;
        }

        return $found->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
