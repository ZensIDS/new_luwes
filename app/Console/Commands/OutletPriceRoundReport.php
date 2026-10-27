<?php

namespace App\Console\Commands;

use App\Models\OutletPrice;
use App\Services\LatestHpp;
use App\Services\PriceCalculator;
use Illuminate\Console\Command;

/**
 * Laporan pembulatan harga jual untuk SELURUH aturan harga (master harga jual).
 *
 * Harga netto POS tidak disimpan di tabel outlet_prices: ia dihitung setiap kali dari aturan + HPP terbaru
 * (PriceCalculator::calculateItem). Karena pembulatan ke atas kelipatan Rp100 sudah menjadi langkah terakhir
 * di kalkulator itu, SEMUA aturan lama otomatis ikut dibulatkan begitu kode baru dipakai; tidak ada data aturan
 * yang perlu ditulis ulang. Command ini hanya MEMBACA data dan menampilkan hasilnya (harga sebelum -> sesudah
 * pembulatan) supaya bisa dicek sebelum/sesudah deploy, atau diekspor ke CSV.
 *
 *   php artisan outlet-prices:round-report
 *   php artisan outlet-prices:round-report --outlet=2
 *   php artisan outlet-prices:round-report --detail
 *   php artisan outlet-prices:round-report --csv=pembulatan_harga.csv      (disimpan di storage/app)
 */
class OutletPriceRoundReport extends Command
{
    protected $signature = 'outlet-prices:round-report
        {--outlet= : Hanya outlet dengan ID ini (default: semua outlet)}
        {--chunk=500 : Jumlah aturan per batch}
        {--detail : Tampilkan semua aturan yang terkena pembulatan, bukan hanya 20 pertama}
        {--csv= : Nama file CSV di storage/app untuk menyimpan seluruh hasil}';

    protected $description = 'Laporan pembulatan harga jual (kelipatan Rp100, ke atas) untuk semua aturan harga. Hanya membaca data.';

    public function handle(PriceCalculator $calculator, LatestHpp $latestHpp): int
    {
        $chunk = max(50, min(2000, (int) $this->option('chunk')));
        $outletId = $this->option('outlet') !== null && $this->option('outlet') !== ''
            ? (int) $this->option('outlet')
            : null;

        $csv = null;
        if ($this->option('csv')) {
            $name = basename((string) $this->option('csv'));
            $path = storage_path('app/'.$name);
            $csv = fopen($path, 'w');
            if ($csv === false) {
                $this->error("Tidak bisa menulis file {$path}");

                return self::FAILURE;
            }
            fputcsv($csv, ['rule_id', 'outlet_id', 'barcode', 'nama_produk', 'aktif', 'hpp', 'harga_sebelum', 'pembulatan', 'harga_netto_pos']);
        }

        $total = 0;
        $skipped = 0;
        $changed = 0;
        $roundingSum = 0.0;
        $maxRounding = 0.0;
        $samples = [];
        $detail = (bool) $this->option('detail');

        $query = OutletPrice::query()
            ->with('product:id,code,name,harga_jual,harga_beli')
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));

        $query->chunkById($chunk, function ($rules) use (
            $calculator, $latestHpp, $csv, $detail,
            &$total, &$skipped, &$changed, &$roundingSum, &$maxRounding, &$samples
        ) {
            // HPP terbaru diambil per outlet dalam satu batch (bukan satu query per aturan).
            $hppByOutlet = [];
            foreach ($rules->groupBy('outlet_id') as $oid => $group) {
                $hppByOutlet[(int) $oid] = $latestHpp->forProducts((int) $oid, $group->pluck('product_id'));
            }

            foreach ($rules as $rule) {
                $product = $rule->product;
                if (! $product) {
                    $skipped++;

                    continue;
                }

                $hpp = (float) ($hppByOutlet[(int) $rule->outlet_id][$rule->product_id] ?? 0);
                $price = $calculator->calculateItem($hpp, $rule, $product);
                $before = (float) $price['price_before_rounding'];
                $rounding = (float) $price['price_rounding'];
                $after = (float) $price['price'];

                $total++;
                if ($rounding > 0) {
                    $changed++;
                    $roundingSum += $rounding;
                    $maxRounding = max($maxRounding, $rounding);
                    if ($detail || count($samples) < 20) {
                        $samples[] = [
                            $rule->outlet_id,
                            $product->code,
                            mb_strimwidth((string) $product->name, 0, 40, '…'),
                            number_format($before, 0, ',', '.'),
                            '+'.number_format($rounding, 0, ',', '.'),
                            number_format($after, 0, ',', '.'),
                        ];
                    }
                }

                if ($csv) {
                    fputcsv($csv, [
                        $rule->id, $rule->outlet_id, $product->code, $product->name,
                        $rule->is_active ? 1 : 0, $hpp, $before, $rounding, $after,
                    ]);
                }
            }
        });

        if ($csv) {
            fclose($csv);
            $this->info('CSV disimpan di storage/app/'.basename((string) $this->option('csv')));
        }

        $this->newLine();
        $this->info('Laporan pembulatan harga jual (kelipatan Rp'.PriceCalculator::PRICE_ROUNDING_STEP.', ke atas)');
        $this->table(['Keterangan', 'Jumlah'], [
            ['Aturan harga diperiksa', number_format($total, 0, ',', '.')],
            ['Terkena pembulatan (harga naik)', number_format($changed, 0, ',', '.')],
            ['Sudah bulat (tidak berubah)', number_format($total - $changed, 0, ',', '.')],
            ['Dilewati (produk sudah dihapus)', number_format($skipped, 0, ',', '.')],
            ['Pembulatan terbesar per produk', 'Rp'.number_format($maxRounding, 0, ',', '.')],
            ['Rata-rata pembulatan (yang berubah)', 'Rp'.number_format($changed > 0 ? $roundingSum / $changed : 0, 0, ',', '.')],
        ]);

        if ($samples) {
            $this->line($detail ? 'Semua aturan yang terkena pembulatan:' : 'Contoh 20 aturan pertama yang terkena pembulatan (pakai --detail untuk semua):');
            $this->table(['Outlet', 'Barcode', 'Nama', 'Sebelum', 'Pembulatan', 'Netto POS'], $samples);
        }

        $this->comment('Command ini hanya membaca data. Harga netto dihitung otomatis oleh kalkulator, jadi tidak ada aturan yang diubah.');

        return self::SUCCESS;
    }
}