<?php

namespace App\Console\Commands;

use App\Models\Outlet;
use App\Services\MinimumStockCalculator;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Tahap C4 todolist_autsave_pout.md: uji MinimumStockCalculator::forOutlet() terhadap rumus gudang (forWarehouse).
 *
 * Min dasar gudang = JUMLAH min dasar tiap outlet. Jadi untuk produk yang semua outlet-penjualnya eligible,
 * jumlah forOutlet(min_dasar) di semua outlet HARUS sama dengan forWarehouse(min_dasar).
 * Bila ada outlet yang belum eligible, selisih wajar (forOutlet = 0 untuk outlet itu, gudang tetap menghitungnya)
 * dan dilaporkan terpisah. Hanya MEMBACA data (tidak mengubah apa pun).
 *
 *   php artisan outlet-purchases:verify-min-stock
 *   php artisan outlet-purchases:verify-min-stock --as-of=2026-10-15 --detail
 *
 * Catatan: min_final (setelah adjustment) sengaja tidak dibandingkan, karena pembulatan ke atas dilakukan per
 * outlet di toko dan sekali pada total di gudang, jadi boleh berbeda 1-2 unit.
 */
class VerifyOutletMinStock extends Command
{
    protected $signature = 'outlet-purchases:verify-min-stock
        {--as-of= : Tanggal acuan (default: hari ini)}
        {--detail : Tampilkan semua produk yang tidak sama, bukan hanya 20 pertama}';

    protected $description = 'Bandingkan min stok per outlet (forOutlet) dengan min stok gudang (forWarehouse), hanya membaca data.';

    public function handle(MinimumStockCalculator $calculator): int
    {
        $asOf = $this->option('as-of') ? Carbon::parse($this->option('as-of'))->endOfDay() : now();
        [$windowStart, $windowEnd] = $calculator->window($asOf);
        $this->info('Jendela: '.$windowStart->toDateString().' s/d '.$windowEnd->toDateString());

        $aggregate = $calculator->salesAggregates($windowStart, $windowEnd);
        $warehouse = $calculator->forWarehouse(null, $asOf);

        // outlet_id => [product_id, ...] untuk semua pasangan yang punya penjualan sampai akhir jendela.
        $pairs = [];
        foreach ($aggregate['first_sales']->keys() as $key) {
            [$outletId, $productId] = array_map('intval', explode('-', $key));
            $pairs[$outletId][] = $productId;
        }

        $sumOutlet = [];          // product_id => jumlah min_dasar semua outlet
        $notEligible = [];        // product_id => true bila ada outlet bersales yang belum eligible
        foreach ($pairs as $outletId => $productIds) {
            foreach (array_chunk($productIds, 500) as $chunk) {
                foreach ($calculator->forOutlet($outletId, $chunk, $asOf) as $productId => $row) {
                    $sumOutlet[$productId] = ($sumOutlet[$productId] ?? 0) + $row['min_dasar'];
                    if ($row['source'] !== 'auto') {
                        $notEligible[$productId] = true;
                    }
                }
            }
        }

        $same = 0;
        $expectedDiff = [];
        $unexpected = [];
        foreach ($warehouse as $productId => $w) {
            if ($w['source'] !== 'auto') {
                continue; // gudang memakai min manual, tidak relevan untuk toko
            }
            $outletSum = (int) ($sumOutlet[$productId] ?? 0);
            if ($outletSum === (int) $w['min_dasar']) {
                $same++;
                continue;
            }
            $row = [$productId, $w['min_dasar'], $outletSum];
            if (isset($notEligible[$productId])) {
                $expectedDiff[] = $row;
            } else {
                $unexpected[] = $row;
            }
        }

        $this->line('Outlet dengan data penjualan: '.count($pairs).' dari '.Outlet::count());
        $this->line("Sama dengan gudang            : {$same}");
        $this->line('Beda, wajar (ada outlet belum eligible): '.count($expectedDiff));
        $this->line('Beda, TIDAK wajar (harus 0)   : '.count($unexpected));

        if ($unexpected) {
            $this->error('Produk yang seharusnya sama tetapi berbeda:');
            $this->table(['product_id', 'min_dasar gudang', 'jumlah min_dasar outlet'], $this->option('detail') ? $unexpected : array_slice($unexpected, 0, 20));
        }
        if ($expectedDiff && $this->option('detail')) {
            $this->table(['product_id', 'min_dasar gudang', 'jumlah min_dasar outlet'], $expectedDiff);
        }

        return $unexpected ? self::FAILURE : self::SUCCESS;
    }
}