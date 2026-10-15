<?php

namespace App\Console\Commands;

use App\Models\PembelianProduct;
use App\Models\Product;
use App\Models\Stock;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

/**
 * Koreksi massal produk yang harga belinya SEHARUSNYA per satuan besar, tetapi waktu input terlanjur
 * dipilih "per satuan kecil" (harga satu dus tersimpan sebagai harga 1 pcs).
 *
 * Yang dilakukan pada produk yang dikoreksi:
 *   harga_beli_besar (baru) = harga_beli (lama)                    -> nilai asli dari nota
 *   harga_beli       (baru) = harga_beli (lama) / konversi_qty     -> per satuan kecil, acuan hitung laba
 *
 * Cara membedakan produk yang salah dan yang sudah benar (database tidak bisa "tahu" satuan yang dipilih user,
 * jadi dipakai beberapa petunjuk):
 *   0. BATAS WAKTU --before="2026-10-14 16:00": harga beli yang TERAKHIR diisi/diubah sebelum waktu itu dianggap
 *      salah (satuan besar tersimpan sebagai kecil), sesudahnya dianggap benar. Waktu diambil dari activity log
 *      (perubahan harga_beli terakhir), bukan sekadar tanggal buat produk. Bila dipakai, bukti batch hanya informasi.
 *   1. KAPAN & SIAPA yang membuat produk (dari activity log)  -> --created-from, --created-to, --by
 *   2. BUKTI HARGA di batch stok / pembelian (selalu per satuan kecil), dibandingkan dengan harga produk:
 *        rasio ~ konversi_qty  => SALAH      (harga produk ~ konversi kali lebih besar dari harga batch)
 *        rasio ~ 1             => BENAR?     (kemungkinan sudah benar, dilewati)
 *        tidak ada batch       => TANPA BUKTI (hanya diproses bila dibatasi filter tanggal/user atau --only)
 *   3. Cek manual daftar hasil simulasi, lalu --exclude / --only.
 *
 * Contoh:
 *   php artisan product:fix-harga-beli GLAD2GLOW --before="2026-10-14 16:00"       -> simulasi dengan batas waktu
 *   php artisan product:fix-harga-beli GLAD2GLOW --before="2026-10-14 16:00" --apply
 *   php artisan product:fix-harga-beli GLAD2GLOW                                   -> simulasi
 *   php artisan product:fix-harga-beli GLAD2GLOW --created-from=2026-10-14 --by=5  -> simulasi, dibatasi tanggal & user
 *   php artisan product:fix-harga-beli GLAD2GLOW --created-from=2026-10-14 --apply
 *   php artisan product:fix-harga-beli GLAD2GLOW --exclude=33,34 --apply
 *   php artisan product:fix-harga-beli GLAD2GLOW --only=12,15 --apply              -> paksa produk ini saja
 *   php artisan product:fix-harga-beli --restore=storage/app/fix-harga-beli/xxx.csv --apply   -> batalkan
 *
 * Pengaman: default simulasi; backup CSV tiap --apply; produk yang sudah ada di backup dilewati; hanya mengubah
 * products.harga_beli & harga_beli_besar (update_count tidak naik, HPP batch / pembelian tidak disentuh).
 */
class FixHargaBeliSatuanBesar extends Command
{
    protected $signature = 'product:fix-harga-beli
        {prefix? : Awalan nama produk, mis. GLAD2GLOW}
        {--apply : Tulis perubahan ke database (default: hanya simulasi)}
        {--before= : Harga beli yang terakhir diisi/diubah SEBELUM waktu ini dianggap salah (mis. "2026-10-14 16:00")}
        {--created-from= : Hanya produk yang dibuat pada/setelah tanggal ini (YYYY-MM-DD)}
        {--created-to= : Hanya produk yang dibuat pada/sebelum tanggal ini (YYYY-MM-DD)}
        {--by= : Hanya produk yang dibuat oleh user dengan ID ini (dari activity log)}
        {--only= : Paksa hanya id produk ini (pisahkan koma), mengabaikan bukti & filter}
        {--exclude= : Lewati id produk ini (pisahkan koma)}
        {--restore= : Path file backup CSV untuk mengembalikan harga lama}';

    protected $description = 'Koreksi harga beli produk yang harga satuan besarnya salah tersimpan sebagai satuan kecil';

    private const DIR = 'fix-harga-beli';

    public function handle(): int
    {
        if (! Schema::hasColumn('products', 'harga_beli_besar')) {
            $this->error('Kolom products.harga_beli_besar belum ada. Jalankan "php artisan migrate" dulu.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $this->warn($apply
            ? 'MODE APPLY: data akan diubah.'
            : 'MODE SIMULASI: tidak ada data yang diubah (tambahkan --apply untuk menulis).');

        if ($this->option('restore')) {
            return $this->restore((string) $this->option('restore'), $apply);
        }

        $prefix = trim((string) $this->argument('prefix'));
        if ($prefix === '') {
            $this->error('Isi awalan nama produk, mis. php artisan product:fix-harga-beli GLAD2GLOW');

            return self::FAILURE;
        }

        $only = $this->idList($this->option('only'));
        $exclude = $this->idList($this->option('exclude'));
        $from = $this->option('created-from') ? Carbon::parse($this->option('created-from'))->startOfDay() : null;
        $to = $this->option('created-to') ? Carbon::parse($this->option('created-to'))->endOfDay() : null;
        $by = $this->option('by') ? (int) $this->option('by') : null;
        $before = $this->option('before') ? Carbon::parse($this->option('before')) : null;
        $hasFilter = $from || $to || $by || $before;
        $alreadyFixed = $this->alreadyFixedIds();

        $query = Product::query()->where('name', 'like', addcslashes($prefix, '%_\\') . '%')->orderBy('name');
        if ($only) {
            $query->whereIn('id', $only);
        }
        $products = $query->get();

        if ($products->isEmpty()) {
            $this->info("Tidak ada produk dengan nama diawali \"{$prefix}\".");

            return self::SUCCESS;
        }

        $ids = $products->pluck('id')->all();
        $evidence = $this->evidence($ids);
        $creators = Activity::query()
            ->where('subject_type', (new Product)->getMorphClass())
            ->where('event', 'created')
            ->whereIn('subject_id', $ids)
            ->with('causer')
            ->get()
            ->keyBy('subject_id');

        // Waktu terakhir harga_beli produk diisi/diubah (activity log mencatat hanya atribut yang berubah).
        $lastPriceChange = [];
        if ($before) {
            Activity::query()
                ->where('subject_type', (new Product)->getMorphClass())
                ->whereIn('subject_id', $ids)
                ->get(['subject_id', 'created_at', 'properties'])
                ->filter(fn ($a) => isset($a->properties['attributes']['harga_beli']))
                ->each(function ($a) use (&$lastPriceChange) {
                    $current = $lastPriceChange[$a->subject_id] ?? null;
                    if (! $current || $a->created_at->gt($current)) {
                        $lastPriceChange[$a->subject_id] = $a->created_at;
                    }
                });
        }

        $rows = [];
        $skipped = [];

        foreach ($products as $product) {
            $konversi = (float) $product->konversi_qty;
            $lama = (float) $product->harga_beli;
            $ev = $evidence[$product->id] ?? null;
            $creator = $creators->get($product->id);
            $createdAt = $product->created_at;

            [$status, $ratio] = $this->classify($lama, $ev['value'] ?? null, $konversi);

            $info = [
                'id' => $product->id,
                'name' => $product->name,
                'dibuat' => $createdAt?->format('d/m/Y H:i') ?? '-',
                'ubah' => ($lastPriceChange[$product->id] ?? $createdAt)?->format('d/m/Y H:i') ?? '-',
                'oleh' => $creator?->causer ? $creator->causer->name . ' (#' . $creator->causer_id . ')' : '-',
                'konversi' => $konversi,
                'lama' => $lama,
                'bukti' => $ev ? $ev['value'] : null,
                'rasio' => $ratio,
                'status' => $status,
            ];

            $reason = null;
            if (in_array($product->id, $exclude, true)) {
                $reason = 'dikecualikan (--exclude)';
            } elseif (in_array($product->id, $alreadyFixed, true)) {
                $reason = 'sudah pernah dikoreksi (ada di backup)';
            } elseif (! $product->satuan_besar || $konversi < 2) {
                $reason = 'satuan besar / konversi belum diisi (konversi < 2)';
            } elseif ($lama <= 0) {
                $reason = 'harga beli 0';
            } elseif (! $only) {
                $lastChange = $lastPriceChange[$product->id] ?? $createdAt;
                if ($before && (! $lastChange || $lastChange->gte($before))) {
                    $reason = 'harga beli terakhir diisi/diubah ' . ($lastChange?->format('d/m/Y H:i') ?? '-') . ', setelah batas --before (dianggap sudah benar)';
                } elseif ($from && (! $createdAt || $createdAt->lt($from))) {
                    $reason = 'dibuat sebelum --created-from';
                } elseif ($to && (! $createdAt || $createdAt->gt($to))) {
                    $reason = 'dibuat setelah --created-to';
                } elseif ($by && (int) $creator?->causer_id !== $by) {
                    $reason = 'bukan dibuat oleh user #' . $by;
                } elseif (! $before && $status === 'BENAR?') {
                    $reason = 'harga produk sama dengan harga batch (kemungkinan sudah benar)';
                } elseif (! $before && $status === 'ANEH') {
                    $reason = 'rasio harga produk vs batch tidak cocok (cek manual, bisa pakai --only)';
                } elseif ($status === 'TANPA BUKTI' && ! $hasFilter) {
                    $reason = 'belum ada batch/pembelian sebagai bukti: batasi dengan --created-from/--by atau pakai --only';
                }
            }

            if ($reason) {
                $skipped[] = $info + ['alasan' => $reason];

                continue;
            }

            $info['baru_kecil'] = round($lama / $konversi, 2);
            $info['baru_besar'] = $lama;
            $info['besar_lama'] = $product->harga_beli_besar;
            $rows[] = $info;
        }

        if ($rows) {
            $this->info(count($rows) . ' produk akan dikoreksi:');
            $this->table(
                ['ID', 'Nama', 'Dibuat', 'Harga diisi', 'Oleh', 'Isi', 'Harga lama', 'Bukti batch', 'Rasio', 'Status', 'Kecil baru', 'Besar baru'],
                array_map(fn ($r) => [
                    $r['id'], $r['name'], $r['dibuat'], $r['ubah'], $r['oleh'], $this->num($r['konversi']),
                    $this->rp($r['lama']), $r['bukti'] === null ? '-' : $this->rp($r['bukti']),
                    $r['rasio'] === null ? '-' : $this->num($r['rasio']), $r['status'],
                    $this->rp($r['baru_kecil']), $this->rp($r['baru_besar']),
                ], $rows)
            );
        }

        if ($skipped) {
            $this->warn(count($skipped) . ' produk dilewati:');
            $this->table(
                ['ID', 'Nama', 'Dibuat', 'Harga diisi', 'Oleh', 'Harga', 'Bukti batch', 'Status', 'Alasan'],
                array_map(fn ($r) => [
                    $r['id'], $r['name'], $r['dibuat'], $r['ubah'], $r['oleh'], $this->rp($r['lama']),
                    $r['bukti'] === null ? '-' : $this->rp($r['bukti']), $r['status'], $r['alasan'],
                ], $skipped)
            );
        }

        $this->line('Catatan: "bukti batch" = harga satuan kecil terakhir di stok / pembelian. Form pembelian mengisi harga otomatis dari harga produk,');
        $this->line('jadi batch yang dibuat SETELAH produk salah input bisa ikut salah (rasio ~1 tapi sebenarnya salah). Andalkan tanggal & user pembuat.');

        if (! $rows) {
            $this->info('Tidak ada yang perlu dikoreksi.');

            return self::SUCCESS;
        }

        if (! $apply) {
            $this->line('Cek tabel di atas. Kalau sudah benar, jalankan ulang dengan --apply (pakai --exclude=ID,ID untuk melewatkan produk tertentu).');

            return self::SUCCESS;
        }

        $backup = $this->writeBackup($prefix, $rows);

        DB::transaction(function () use ($rows) {
            foreach ($rows as $r) {
                // Lewat model supaya tercatat di activity log (update_count tidak ikut naik).
                Product::whereKey($r['id'])->lockForUpdate()->first()?->update([
                    'harga_beli' => $r['baru_kecil'],
                    'harga_beli_besar' => $r['baru_besar'],
                ]);
            }
        });

        $this->info(count($rows) . ' produk berhasil dikoreksi.');
        $this->line('Backup harga lama: storage/app/' . $backup);
        $this->line('Untuk membatalkan: php artisan product:fix-harga-beli --restore=storage/app/' . $backup . ' --apply');

        return self::SUCCESS;
    }

    /**
     * Harga satuan kecil terakhir yang pernah dipakai di batch stok (stocks), atau bila belum ada, di pembelian.
     *
     * @return array<int, array{value: float, source: string}>
     */
    private function evidence(array $ids): array
    {
        $result = [];

        $stocks = Stock::whereIn('product_id', $ids)->where('harga_beli', '>', 0)
            ->orderByDesc('id')->get(['product_id', 'harga_beli'])->unique('product_id');
        foreach ($stocks as $s) {
            $result[$s->product_id] = ['value' => (float) $s->harga_beli, 'source' => 'stok'];
        }

        $pembelian = PembelianProduct::whereIn('product_id', $ids)->where('harga_beli', '>', 0)
            ->orderByDesc('id')->get(['product_id', 'harga_beli'])->unique('product_id');
        foreach ($pembelian as $p) {
            $result[$p->product_id] ??= ['value' => (float) $p->harga_beli, 'source' => 'pembelian'];
        }

        return $result;
    }

    /** @return array{0: string, 1: float|null} [status, rasio harga produk / harga batch] */
    private function classify(float $lama, ?float $bukti, float $konversi): array
    {
        if ($bukti === null || $bukti <= 0 || $lama <= 0) {
            return ['TANPA BUKTI', null];
        }

        $ratio = $lama / $bukti;
        if ($konversi < 2) {
            return ['ANEH', round($ratio, 2)];
        }

        $dSalah = abs(log($ratio / $konversi));
        $dBenar = abs(log($ratio));
        $toleransi = log(1.3);

        if (min($dSalah, $dBenar) > $toleransi) {
            return ['ANEH', round($ratio, 2)];
        }

        return [$dSalah < $dBenar ? 'SALAH' : 'BENAR?', round($ratio, 2)];
    }

    private function restore(string $path, bool $apply): int
    {
        if (! is_file($path)) {
            $this->error("File backup tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        $items = [];
        while (($line = fgetcsv($handle)) !== false) {
            $items[] = array_combine($header, $line);
        }
        fclose($handle);

        $this->table(
            ['ID', 'Nama', 'Kecil dikembalikan ke', 'Besar dikembalikan ke'],
            array_map(fn ($i) => [
                $i['id'], $i['name'], $this->rp((float) $i['harga_lama']),
                ($i['besar_lama'] ?? '') === '' ? '-' : $this->rp((float) $i['besar_lama']),
            ], $items)
        );

        if (! $apply) {
            $this->line('Tambahkan --apply untuk mengembalikan harga lama.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($items) {
            foreach ($items as $i) {
                Product::whereKey((int) $i['id'])->lockForUpdate()->first()?->update([
                    'harga_beli' => (float) $i['harga_lama'],
                    'harga_beli_besar' => ($i['besar_lama'] ?? '') === '' ? null : (float) $i['besar_lama'],
                ]);
            }
        });

        // Ubah nama file supaya produk ini boleh dikoreksi lagi di kemudian hari.
        rename($path, $path . '.restored');
        $this->info(count($items) . ' produk dikembalikan ke harga lama.');

        return self::SUCCESS;
    }

    private function writeBackup(string $prefix, array $rows): string
    {
        $name = self::DIR . '/' . now()->format('Ymd_His') . '_' . preg_replace('/[^A-Za-z0-9]+/', '-', $prefix) . '.csv';

        $csv = fopen('php://temp', 'w+');
        fputcsv($csv, ['id', 'name', 'konversi_qty', 'harga_lama', 'besar_lama', 'harga_baru', 'besar_baru']);
        foreach ($rows as $r) {
            fputcsv($csv, [$r['id'], $r['name'], $r['konversi'], $r['lama'], $r['besar_lama'], $r['baru_kecil'], $r['baru_besar']]);
        }
        rewind($csv);
        Storage::disk('local')->put($name, stream_get_contents($csv));
        fclose($csv);

        return $name;
    }

    /** @return int[] id produk yang sudah tercatat di file backup (tidak termasuk yang sudah di-restore). */
    private function alreadyFixedIds(): array
    {
        $dir = Storage::disk('local')->path(self::DIR);
        if (! is_dir($dir)) {
            return [];
        }

        $ids = [];
        foreach (glob($dir . '/*.csv') as $file) {
            $handle = fopen($file, 'r');
            fgetcsv($handle);
            while (($line = fgetcsv($handle)) !== false) {
                $ids[] = (int) $line[0];
            }
            fclose($handle);
        }

        return array_values(array_unique($ids));
    }

    /** @return int[] */
    private function idList(?string $value): array
    {
        return collect(explode(',', (string) $value))
            ->map(fn ($v) => (int) trim($v))
            ->filter()
            ->values()
            ->all();
    }

    private function rp(float $n): string
    {
        return number_format($n, 2, ',', '.');
    }

    private function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
    }
}