<?php

namespace App\Console\Commands;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

/**
 * Fase 11: ukur jumlah query & waktu per halaman utama, sebelum/sesudah optimasi.
 *
 * Hanya mengirim request GET (tidak mengubah data). Jalankan di LOKAL / staging:
 *
 *   php artisan perf:baseline --save=baseline.json
 *   (setelah perubahan)
 *   php artisan perf:baseline --compare=baseline.json
 *
 * Opsi lain:
 *   --user=EMAIL|ID   user yang dipakai (default: superadmin pertama)
 *   --outlet=ID       outlet untuk POS/keranjang (default: outlet pertama)
 *   --search=TEKS     kata pencarian POS (default: 3 huruf pertama nama produk)
 *   --barcode=KODE    kode untuk price checker (default: kode produk pertama)
 *   --runs=N          ulangi tiap halaman N kali dan ambil rata-rata (default 1)
 *   --detail          tampilkan query yang berulang (indikasi N+1)
 *
 * Catatan: request dijalankan di dalam proses artisan, jadi cache (mis. price
 * checker 60 detik) ikut berlaku. Gunakan --runs=2 untuk melihat efek cache.
 */
class PerfBaseline extends Command
{
    protected $signature = 'perf:baseline
        {--user= : Email atau ID user (default: superadmin pertama)}
        {--outlet= : ID outlet untuk POS/keranjang}
        {--search= : Kata pencarian POS}
        {--barcode= : Kode produk untuk price checker}
        {--runs=1 : Jumlah pengulangan per halaman}
        {--save= : Simpan hasil ke file JSON}
        {--compare= : Bandingkan dengan file JSON hasil sebelumnya}
        {--detail : Tampilkan query yang berulang}';

    protected $description = 'Ukur jumlah query dan waktu respon halaman utama (Fase 11, read-only).';

    /** @var array<int, array{sql: string, time: float}> */
    private array $queries = [];

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Perintah ini hanya untuk lokal/staging (APP_ENV=production terdeteksi).');

            return self::FAILURE;
        }

        $user = $this->resolveUser();
        if (! $user) {
            $this->error('User tidak ditemukan. Pakai --user=EMAIL atau ID.');

            return self::FAILURE;
        }

        $outletId = $this->option('outlet') ?: Outlet::query()->value('id');
        $search = $this->option('search') ?: mb_substr((string) Product::query()->value('name'), 0, 3);
        $barcode = $this->option('barcode') ?: (string) Product::query()->whereNotNull('code')->value('code');
        $runs = max(1, (int) $this->option('runs'));

        $this->info("User: {$user->email} ({$user->role}) | Outlet: ".($outletId ?: '-')." | Search: '{$search}' | Barcode: '{$barcode}' | Runs: {$runs}");

        $pages = [
            'dashboard' => '/dashboard',
            'produk' => '/product',
            'stok gudang' => '/stock',
            'laporan' => '/laporan',
            'keranjang (GET /cart)' => '/cart',
        ];

        if ($outletId) {
            $pages['pencarian POS'] = '/outlet/'.$outletId.'/products?search='.urlencode($search);
        }

        if ($barcode !== '') {
            $pages['price checker'] = '/price-checker/lookup?barcode='.urlencode($barcode).($outletId ? '&outlet_id='.$outletId : '');
        }

        Event::listen(QueryExecuted::class, function (QueryExecuted $query) {
            $this->queries[] = ['sql' => $query->sql, 'time' => (float) $query->time];
        });

        $results = [];
        $details = [];

        foreach ($pages as $label => $uri) {
            $sumQueries = $sumDb = $sumTotal = 0;
            $status = 0;
            $repeated = [];

            for ($i = 0; $i < $runs; $i++) {
                [$status, $count, $dbMs, $totalMs, $repeated] = $this->measure($user, $uri);
                $sumQueries += $count;
                $sumDb += $dbMs;
                $sumTotal += $totalMs;
            }

            $results[$label] = [
                'uri' => $uri,
                'status' => $status,
                'queries' => (int) round($sumQueries / $runs),
                'db_ms' => round($sumDb / $runs, 1),
                'total_ms' => round($sumTotal / $runs, 1),
            ];
            $details[$label] = $repeated;
        }

        $this->renderTable($results);

        if ($this->option('detail')) {
            foreach ($details as $label => $repeated) {
                if ($repeated === []) {
                    continue;
                }
                $this->line('');
                $this->warn("Query berulang di: {$label}");
                foreach ($repeated as $sql => $times) {
                    $this->line(sprintf('  %3dx  %s', $times, mb_substr($sql, 0, 160)));
                }
            }
        }

        if ($path = $this->option('compare')) {
            $this->renderComparison($results, $path);
        }

        if ($path = $this->option('save')) {
            file_put_contents($path, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info("Hasil disimpan ke {$path}");
        }

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $opt = $this->option('user');

        if ($opt) {
            return is_numeric($opt)
                ? User::find((int) $opt)
                : User::where('email', $opt)->first();
        }

        return User::where('role', 'superadmin')->first();
    }

    /**
     * @return array{0:int,1:int,2:float,3:float,4:array<string,int>}
     */
    private function measure(User $user, string $uri): array
    {
        $this->queries = [];

        $kernel = app(HttpKernel::class);
        $request = Request::create($uri, 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html,application/json']);
        app()->instance('request', $request);
        Auth::guard('web')->setUser($user);

        $start = microtime(true);
        $response = $kernel->handle($request);
        $totalMs = (microtime(true) - $start) * 1000;
        $kernel->terminate($request, $response);

        $dbMs = array_sum(array_column($this->queries, 'time'));

        $counts = array_count_values(array_column($this->queries, 'sql'));
        $repeated = array_filter($counts, fn ($n) => $n >= 3);
        arsort($repeated);

        return [
            $response->getStatusCode(),
            count($this->queries),
            (float) $dbMs,
            (float) $totalMs,
            array_slice($repeated, 0, 5, true),
        ];
    }

    private function renderTable(array $results): void
    {
        $rows = [];
        foreach ($results as $label => $r) {
            $rows[] = [$label, $r['status'], $r['queries'], $r['db_ms'], $r['total_ms']];
        }

        $this->table(['Halaman', 'HTTP', 'Query', 'Waktu DB (ms)', 'Total (ms)'], $rows);
        $this->line('HTTP 302/403/404 berarti halaman tidak terukur dengan benar (cek --user / --outlet).');
    }

    private function renderComparison(array $now, string $path): void
    {
        if (! is_file($path)) {
            $this->error("File pembanding tidak ditemukan: {$path}");

            return;
        }

        $before = json_decode((string) file_get_contents($path), true) ?: [];
        $rows = [];

        foreach ($now as $label => $r) {
            if (! isset($before[$label])) {
                continue;
            }
            $b = $before[$label];
            $rows[] = [
                $label,
                $b['queries'].' → '.$r['queries'],
                sprintf('%+d', $r['queries'] - $b['queries']),
                $b['total_ms'].' → '.$r['total_ms'],
            ];
        }

        $this->line('');
        $this->info('Perbandingan dengan '.$path);
        $this->table(['Halaman', 'Query (sebelum → sesudah)', 'Selisih', 'Total ms (sebelum → sesudah)'], $rows);
    }
}