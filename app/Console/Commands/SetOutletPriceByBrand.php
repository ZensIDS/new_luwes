<?php

namespace App\Console\Commands;

use App\Models\Outlet;
use App\Models\OutletPrice;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Input massal Master Harga Jual (outlet_prices) berdasarkan AWALAN nama produk.
 *
 * Semua nilai di bawah bertipe PERSEN (disc reguler, disc tambahan, margin), sedangkan
 * Penyesuaian Harga Outlet bertipe NOMINAL (Rp). Kolom yang tidak ada di tabel (pajak, disc toko,
 * tanggal berlaku, catatan) TIDAK disentuh pada aturan yang sudah ada; aturan baru memakai default form.
 *
 * Contoh:
 *   php artisan outlet-price:set-by-brand            -> simulasi (outlet 2), tidak ada data yang diubah
 *   php artisan outlet-price:set-by-brand --apply    -> tulis ke database
 *   php artisan outlet-price:set-by-brand --outlet=2 --adjustment=100 --apply
 */
class SetOutletPriceByBrand extends Command
{
    protected $signature = 'outlet-price:set-by-brand
        {--outlet=2 : ID outlet tujuan}
        {--adjustment=100 : Penyesuaian harga outlet (nominal Rp)}
        {--apply : Tulis perubahan ke database (default: hanya simulasi)}';

    protected $description = 'Isi master harga jual outlet berdasarkan awalan nama produk (brand): disc reguler, disc tambahan, margin';

    /**
     * Brand => [awalan nama yang dicocokkan, disc reguler %, disc tambahan %, margin %].
     * Awalan dicocokkan dengan LIKE "awalan%" (tidak peka huruf besar/kecil di MySQL).
     */
    private const RULES = [
        ['brand' => 'Wardah',       'prefixes' => ['Wardah'],                    'disc' => 20, 'tambahan' => 0, 'margin' => 5],
        ['brand' => 'Kahf',         'prefixes' => ['Kahf'],                      'disc' => 20, 'tambahan' => 0, 'margin' => 5],
        ['brand' => 'Light Plus',   'prefixes' => ['Light Plus'],                'disc' => 20, 'tambahan' => 0, 'margin' => 5],
        ['brand' => 'Bright Now',   'prefixes' => ['Bright Now'],                'disc' => 20, 'tambahan' => 0, 'margin' => 5],
        ['brand' => 'Tavi',         'prefixes' => ['Tavi'],                      'disc' => 20, 'tambahan' => 0, 'margin' => 5],
        ['brand' => 'Labore',       'prefixes' => ['Labore'],                    'disc' => 20, 'tambahan' => 0, 'margin' => 5],
        ['brand' => 'Putri',        'prefixes' => ['Putri'],                     'disc' => 15, 'tambahan' => 0, 'margin' => 5],
        ['brand' => 'Earth Love',   'prefixes' => ['Earth Love'],                'disc' => 20, 'tambahan' => 2, 'margin' => 5],
        ['brand' => 'Instaperfect', 'prefixes' => ['Instaperfect'],              'disc' => 20, 'tambahan' => 0, 'margin' => 5],
        ['brand' => 'Make Over',    'prefixes' => ['Make Over', 'Makeover'],     'disc' => 20, 'tambahan' => 0, 'margin' => 5],
        ['brand' => 'OMG',          'prefixes' => ['OMG'],                       'disc' => 15, 'tambahan' => 0, 'margin' => 5],
        ['brand' => 'Somethinc',    'prefixes' => ['Somethinc'],                 'disc' => 25, 'tambahan' => 0, 'margin' => 10],
        ['brand' => 'Glad2Glow',    'prefixes' => ['Glad2Glow', 'Glad 2 Glow'],  'disc' => 10, 'tambahan' => 0, 'margin' => 15],
    ];

    public function handle(): int
    {
        $outletId = (int) $this->option('outlet');
        $adjustment = (float) $this->option('adjustment');
        $apply = (bool) $this->option('apply');

        $outlet = Outlet::find($outletId);
        if (! $outlet) {
            $this->error("Outlet dengan ID {$outletId} tidak ditemukan.");

            return self::FAILURE;
        }

        $this->warn($apply
            ? "MODE APPLY: master harga outlet #{$outletId} ({$outlet->name}) akan diubah."
            : 'MODE SIMULASI: tidak ada data yang diubah (tambahkan --apply untuk menulis).');
        $this->line('Penyesuaian harga outlet: Rp ' . number_format($adjustment, 0, ',', '.'));

        // 1) Kumpulkan produk per brand. Bila satu produk cocok ke >1 brand, awalan terpanjang menang.
        $assigned = []; // product_id => ['rule' => index, 'len' => panjang awalan, 'name' => nama]
        $conflicts = [];

        foreach (self::RULES as $index => $rule) {
            foreach ($rule['prefixes'] as $prefix) {
                $like = addcslashes($prefix, '\\%_') . '%';

                Product::query()->where('name', 'like', $like)->get(['id', 'name'])
                    ->each(function (Product $product) use (&$assigned, &$conflicts, $index, $prefix) {
                        $len = mb_strlen($prefix);
                        $current = $assigned[$product->id] ?? null;

                        if ($current && $current['rule'] !== $index) {
                            $conflicts[$product->id] = $product->name;
                        }

                        if (! $current || $len > $current['len']) {
                            $assigned[$product->id] = ['rule' => $index, 'len' => $len, 'name' => $product->name];
                        }
                    });
            }
        }

        // 2) Hitung dampak per brand (baru / diubah / sama) tanpa menulis apa pun.
        $existing = OutletPrice::withTrashed()
            ->where('outlet_id', $outletId)
            ->whereIn('product_id', array_keys($assigned))
            ->get()
            ->keyBy('product_id');

        $summary = [];
        $plan = []; // product_id => nilai yang akan ditulis
        foreach (self::RULES as $index => $rule) {
            $summary[$index] = ['brand' => $rule['brand'], 'found' => 0, 'new' => 0, 'update' => 0, 'same' => 0];
        }

        foreach ($assigned as $productId => $info) {
            $rule = self::RULES[$info['rule']];
            $values = [
                'disc_brand_type' => 'percentage',
                'disc_brand_value' => (float) $rule['disc'],
                'disc_tambahan_type' => 'percentage',
                'disc_tambahan_value' => (float) $rule['tambahan'],
                'margin_type' => 'percentage',
                'margin_value' => (float) $rule['margin'],
                'outlet_adjustment_type' => 'nominal',
                'outlet_adjustment_value' => $adjustment,
                'is_active' => true,
            ];

            $summary[$info['rule']]['found']++;
            $row = $existing->get($productId);

            if (! $row) {
                $summary[$info['rule']]['new']++;
            } elseif ($this->isSame($row, $values)) {
                $summary[$info['rule']]['same']++;
            } else {
                $summary[$info['rule']]['update']++;
            }

            $plan[$productId] = $values;
        }

        $this->table(
            ['Brand', 'Produk cocok', 'Baru', 'Diubah', 'Sudah sama'],
            array_map(fn ($row) => [$row['brand'], $row['found'], $row['new'], $row['update'], $row['same']], $summary)
        );

        foreach ($summary as $row) {
            if ($row['found'] === 0) {
                $this->warn("Brand \"{$row['brand']}\" tidak menemukan produk dengan awalan nama tersebut.");
            }
        }

        if ($conflicts) {
            $this->warn('Produk yang cocok ke lebih dari satu brand (dipakai awalan terpanjang):');
            foreach ($conflicts as $id => $name) {
                $this->line("  #{$id} {$name} -> " . self::RULES[$assigned[$id]['rule']]['brand']);
            }
        }

        if (! $plan) {
            $this->warn('Tidak ada produk yang cocok. Tidak ada yang dikerjakan.');

            return self::SUCCESS;
        }

        if (! $apply) {
            $this->info('Simulasi selesai. Jalankan ulang dengan --apply bila sudah sesuai.');

            return self::SUCCESS;
        }

        // 3) Tulis. Hanya kolom yang diatur yang diubah; pajak, disc toko, tanggal & catatan tidak disentuh.
        DB::transaction(function () use ($plan, $existing, $outletId) {
            foreach ($plan as $productId => $values) {
                $price = $existing->get($productId) ?? new OutletPrice([
                    'outlet_id' => $outletId,
                    'product_id' => $productId,
                    'pajak_type' => 'percentage',
                    'pajak_value' => 0,
                    'disc_toko_type' => 'nominal',
                    'disc_toko_value' => 0,
                    'created_by' => null,
                ]);

                if ($price->exists && $price->trashed()) {
                    $price->restore();
                }

                $price->fill($values);
                $price->setUpdatedAt(now());
                $price->save();
            }
        });

        $this->info(count($plan) . " aturan harga outlet #{$outletId} berhasil disimpan.");

        return self::SUCCESS;
    }

    private function isSame(OutletPrice $row, array $values): bool
    {
        if ($row->trashed()) {
            return false;
        }

        foreach ($values as $key => $value) {
            $current = $row->{$key};
            if (is_float($value) || is_int($value)) {
                if (abs((float) $current - (float) $value) > 0.004) {
                    return false;
                }
            } elseif ($current !== $value) {
                return false;
            }
        }

        return true;
    }
}