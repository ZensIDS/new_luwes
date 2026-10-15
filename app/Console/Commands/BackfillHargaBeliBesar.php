<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Isi massal products.harga_beli_besar dari harga satuan kecil yang sudah ada:
 *
 *   harga_beli_besar = harga_beli x konversi_qty
 *
 * Anggapan: products.harga_beli (satuan kecil) sudah benar. Yang dihitung hanya produk yang punya
 * harga_beli > 0 dan konversi_qty >= 1. Produk tanpa konversi tidak bisa dihitung, jadi dilewati dan
 * dicatat di file CSV supaya bisa dilengkapi konversinya lalu command dijalankan ulang.
 *
 * Contoh:
 *   php artisan product:backfill-harga-beli-besar                    -> simulasi (hanya laporan)
 *   php artisan product:backfill-harga-beli-besar --apply            -> isi yang masih kosong
 *   php artisan product:backfill-harga-beli-besar GLAD2GLOW --apply  -> hanya produk berawalan nama ini
 *   php artisan product:backfill-harga-beli-besar --overwrite --apply -> hitung ulang juga yang sudah terisi tapi tidak sesuai
 *
 * Sebuah pasangan kecil/besar dianggap SESUAI bila harga_beli_besar / konversi_qty (dibulatkan 2 desimal) = harga_beli.
 * Cara ini dipakai supaya harga besar bulat dari nota (mis. 100.000 untuk isi 12, kecil 8.333,33) tidak salah dianggap tidak sesuai.
 *
 * Aman dijalankan ulang. Pengisian dilakukan dengan satu query massal (tidak tercatat di activity log produk).
 * Dengan --overwrite, nilai lama dibackup ke CSV di storage/app/backfill-harga-beli-besar/.
 */
class BackfillHargaBeliBesar extends Command
{
    protected $signature = 'product:backfill-harga-beli-besar
        {prefix? : Awalan nama produk (kosong = semua produk)}
        {--apply : Tulis perubahan ke database (default: hanya simulasi)}
        {--overwrite : Hitung ulang juga produk yang harga besarnya sudah terisi tapi tidak sesuai dengan kecil x konversi}';

    protected $description = 'Isi massal harga_beli_besar = harga_beli x konversi_qty';

    private const DIR = 'backfill-harga-beli-besar';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $overwrite = (bool) $this->option('overwrite');
        $prefix = trim((string) $this->argument('prefix'));

        $this->warn($apply
            ? 'MODE APPLY: data akan diubah.'
            : 'MODE SIMULASI: tidak ada data yang diubah (tambahkan --apply untuk menulis).');

        $base = fn () => DB::table('products')
            ->whereNull('deleted_at')
            ->when($prefix !== '', fn ($q) => $q->where('name', 'like', addcslashes($prefix, '%_\\') . '%'));

        $hitung = 'ROUND(harga_beli * konversi_qty, 2)';

        // Bisa dihitung: ada harga kecil dan konversi.
        $eligible = fn () => $base()->where('harga_beli', '>', 0)->where('konversi_qty', '>=', 1);

        $total = $base()->count();
        $toFill = $eligible()->where(fn ($q) => $q->whereNull('harga_beli_besar')->orWhere('harga_beli_besar', 0));
        $toFillCount = (clone $toFill)->count();

        $inconsistent = $eligible()
            ->where('harga_beli_besar', '>', 0)
            ->whereRaw("ABS(ROUND(harga_beli_besar / konversi_qty, 2) - harga_beli) > 0.01");
        $inconsistentCount = (clone $inconsistent)->count();

        $consistentCount = $eligible()->where('harga_beli_besar', '>', 0)
            ->whereRaw("ABS(ROUND(harga_beli_besar / konversi_qty, 2) - harga_beli) <= 0.01")->count();

        $noKonversi = $base()->where('harga_beli', '>', 0)
            ->where(fn ($q) => $q->whereNull('konversi_qty')->orWhere('konversi_qty', '<', 1));
        $noKonversiCount = (clone $noKonversi)->count();
        $noPriceCount = $base()->where(fn ($q) => $q->whereNull('harga_beli')->orWhere('harga_beli', '<=', 0))->count();

        $this->table(['Keterangan', 'Jumlah'], [
            ['Total produk' . ($prefix !== '' ? " (awalan \"{$prefix}\")" : ''), $total],
            ['Akan diisi (harga besar masih kosong)', $toFillCount],
            ['Sudah terisi dan sesuai', $consistentCount],
            ['Sudah terisi tapi TIDAK sesuai kecil x konversi' . ($overwrite ? ' (akan dihitung ulang)' : ' (dibiarkan, pakai --overwrite)'), $inconsistentCount],
            ['Dilewati: konversi belum diisi (harga besar tidak bisa dihitung)', $noKonversiCount],
            ['Dilewati: harga beli kosong / 0', $noPriceCount],
        ]);

        if ($noKonversiCount > 0) {
            $list = (clone $noKonversi)->orderBy('name')->get(['id', 'code', 'name', 'satuan', 'satuan_besar', 'konversi_qty', 'harga_beli']);
            $csv = $this->writeCsv(
                'tanpa-konversi',
                ['id', 'code', 'name', 'satuan', 'satuan_besar', 'konversi_qty', 'harga_beli'],
                $list->map(fn ($r) => (array) $r)->all()
            );
            $this->warn("{$noKonversiCount} produk belum punya konversi, daftar lengkap: storage/app/{$csv}");
            $this->table(
                ['ID', 'Kode', 'Nama', 'Satuan', 'Satuan besar', 'Konversi', 'Harga kecil'],
                $list->take(15)->map(fn ($r) => [
                    $r->id, $r->code, $r->name, $r->satuan ?? '-', $r->satuan_besar ?? '-', $r->konversi_qty ?? '-',
                    number_format((float) $r->harga_beli, 2, ',', '.'),
                ])->all()
            );
            if ($noKonversiCount > 15) {
                $this->line('... dan ' . ($noKonversiCount - 15) . ' produk lain (lihat CSV).');
            }
        }

        if ($inconsistentCount > 0) {
            $this->line('Contoh yang harga besarnya tidak sesuai:');
            $this->table(
                ['ID', 'Nama', 'Harga kecil', 'Konversi', 'Besar tersimpan', 'Besar seharusnya'],
                (clone $inconsistent)->orderBy('name')->limit(10)->get(['id', 'name', 'harga_beli', 'konversi_qty', 'harga_beli_besar'])
                    ->map(fn ($r) => [
                        $r->id, $r->name, number_format((float) $r->harga_beli, 2, ',', '.'), (float) $r->konversi_qty,
                        number_format((float) $r->harga_beli_besar, 2, ',', '.'),
                        number_format(round((float) $r->harga_beli * (float) $r->konversi_qty, 2), 2, ',', '.'),
                    ])->all()
            );
        }

        $willChange = $toFillCount + ($overwrite ? $inconsistentCount : 0);
        if ($willChange === 0) {
            $this->info('Tidak ada yang perlu diisi.');

            return self::SUCCESS;
        }

        if (! $apply) {
            $this->line("{$willChange} produk akan diisi/dihitung ulang. Jalankan ulang dengan --apply untuk menulis.");

            return self::SUCCESS;
        }

        if ($overwrite && $inconsistentCount > 0) {
            $old = (clone $inconsistent)->get(['id', 'name', 'harga_beli', 'konversi_qty', 'harga_beli_besar']);
            $backup = $this->writeCsv(
                'overwrite-backup',
                ['id', 'name', 'harga_beli', 'konversi_qty', 'harga_beli_besar'],
                $old->map(fn ($r) => (array) $r)->all()
            );
            $this->line("Backup nilai lama harga besar: storage/app/{$backup}");
        }

        $filled = DB::transaction(function () use ($toFill, $inconsistent, $overwrite, $hitung) {
            $filled = (clone $toFill)->update(['harga_beli_besar' => DB::raw($hitung)]);
            if ($overwrite) {
                $filled += (clone $inconsistent)->update(['harga_beli_besar' => DB::raw($hitung)]);
            }

            return $filled;
        });

        $this->info("{$filled} produk berhasil diisi harga satuan besarnya.");

        return self::SUCCESS;
    }

    private function writeCsv(string $label, array $header, array $rows): string
    {
        $name = self::DIR . '/' . now()->format('Ymd_His') . '_' . $label . '.csv';

        $csv = fopen('php://temp', 'w+');
        fputcsv($csv, $header);
        foreach ($rows as $row) {
            fputcsv($csv, array_map(fn ($key) => $row[$key] ?? '', $header));
        }
        rewind($csv);
        Storage::disk('local')->put($name, stream_get_contents($csv));
        fclose($csv);

        return $name;
    }
}