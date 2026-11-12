<?php

namespace Tests\Feature;

use App\Exceptions\HargaBeliGuardException;
use App\Models\OwnerStock;
use App\Models\Product;
use App\Support\HargaBeliGuard;
use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Tests\TestCase;

/**
 * Tes pagar harga beli / HPP (HargaBeliGuard).
 *
 * Bagian 1 memeriksa perilaku guard tanpa database (model dibuat di memori).
 * Bagian 2 memindai kode: gagal bila ada penulisan harga beli / HPP baru di luar daftar jalur.
 */
class HargaBeliGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'harga_beli.mode' => 'enforce',
            'harga_beli.log_channel' => 'null',
        ]);
    }

    // ------------------------------------------------------------------ helpers

    private function existingStock(float $hpp = 1000): OwnerStock
    {
        return (new OwnerStock)->newFromBuilder(['id' => 1, 'owner_id' => 1, 'product_id' => 1, 'qty' => 5, 'hpp' => $hpp]);
    }

    private function existingProduct(float $kecil = 1000, ?float $besar = 12000): Product
    {
        return (new Product)->newFromBuilder([
            'id' => 1, 'name' => 'Produk Tes', 'konversi_qty' => 12, 'harga_beli' => $kecil, 'harga_beli_besar' => $besar,
        ]);
    }

    // ------------------------------------------------------------------ bagian 1: perilaku guard

    public function test_hpp_batch_berubah_di_luar_jalur_ditolak(): void
    {
        $stock = $this->existingStock();
        $stock->hpp = 0;

        $this->expectException(HargaBeliGuardException::class);
        HargaBeliGuard::checkModel($stock);
    }

    public function test_harga_beli_produk_berubah_di_luar_jalur_ditolak(): void
    {
        $product = $this->existingProduct();
        $product->harga_beli = 2000;

        $this->expectException(HargaBeliGuardException::class);
        HargaBeliGuard::checkModel($product);
    }

    public function test_harga_beli_besar_berubah_di_luar_jalur_ditolak(): void
    {
        $product = $this->existingProduct();
        $product->harga_beli_besar = 99999;

        $this->expectException(HargaBeliGuardException::class);
        HargaBeliGuard::checkModel($product);
    }

    public function test_produk_baru_dengan_harga_beli_di_luar_jalur_ditolak(): void
    {
        $product = new Product(['name' => 'Baru', 'harga_beli' => 500]);

        $this->expectException(HargaBeliGuardException::class);
        HargaBeliGuard::checkModel($product);
    }

    public function test_batch_baru_dengan_hpp_di_luar_jalur_ditolak(): void
    {
        $this->expectException(HargaBeliGuardException::class);
        HargaBeliGuard::checkModel(new OwnerStock(['owner_id' => 1, 'product_id' => 1, 'qty' => 0, 'hpp' => 750]));
    }

    public function test_semua_jalur_resmi_dan_turunan_lolos(): void
    {
        foreach (HargaBeliGuard::SOURCES as $source) {
            HargaBeliGuard::allow($source, function () {
                $stock = $this->existingStock();
                $stock->hpp = 2500;
                HargaBeliGuard::checkModel($stock);

                $product = $this->existingProduct();
                $product->harga_beli = 2000;
                $product->harga_beli_besar = 24000;
                HargaBeliGuard::checkModel($product);
            });
        }

        $this->assertFalse(HargaBeliGuard::isAllowed());
    }

    public function test_kolom_lain_boleh_berubah_di_luar_jalur(): void
    {
        $stock = $this->existingStock();
        $stock->qty = 99;
        HargaBeliGuard::checkModel($stock);

        $product = $this->existingProduct();
        $product->name = 'Nama Baru';
        HargaBeliGuard::checkModel($product);

        // Record baru tanpa harga (atau 0) bukan penulisan harga beli.
        HargaBeliGuard::checkModel(new Product(['name' => 'Tanpa harga']));
        HargaBeliGuard::checkModel(new OwnerStock(['owner_id' => 1, 'product_id' => 1, 'qty' => 0, 'hpp' => 0]));

        $this->addToAssertionCount(1);
    }

    public function test_assert_allowed_untuk_query_builder(): void
    {
        HargaBeliGuard::allow('maintenance', function () {
            HargaBeliGuard::assertAllowed('tes');
        });

        $this->expectException(HargaBeliGuardException::class);
        HargaBeliGuard::assertAllowed('tes');
    }

    public function test_izin_dicabut_setelah_allow_selesai_termasuk_bila_error(): void
    {
        try {
            HargaBeliGuard::allow('edit-product', function () {
                throw new RuntimeException('gagal di tengah');
            });
        } catch (RuntimeException) {
            // diharapkan
        }

        $this->assertFalse(HargaBeliGuard::isAllowed());
        $this->assertNull(HargaBeliGuard::current());
    }

    public function test_allow_bersarang_memakai_jalur_terdalam(): void
    {
        HargaBeliGuard::allow('outlet-purchase', function () {
            HargaBeliGuard::allow('turunan', function () {
                $this->assertSame('turunan', HargaBeliGuard::current());
            });
            $this->assertSame('outlet-purchase', HargaBeliGuard::current());
        });
    }

    public function test_jalur_tidak_dikenal_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        HargaBeliGuard::allow('jalur-ngawur', fn () => null);
    }

    public function test_mode_log_hanya_mencatat(): void
    {
        config(['harga_beli.mode' => 'log']);

        $stock = $this->existingStock();
        $stock->hpp = 0;
        HargaBeliGuard::checkModel($stock);

        $this->assertSame('log', HargaBeliGuard::mode());
    }

    public function test_config_default_adalah_enforce(): void
    {
        $config = require base_path('config/harga_beli.php');

        $this->assertStringContainsString("'enforce'", file_get_contents(base_path('config/harga_beli.php')));
        $this->assertArrayHasKey('mode', $config);
    }

    public function test_seeder_demo_ditolak_di_production_kecuali_diizinkan(): void
    {
        $this->app['env'] = 'production';
        config(['harga_beli.allow_demo_seeder' => false]);
        $this->assertFalse(HargaBeliGuard::seederAllowed());

        config(['harga_beli.allow_demo_seeder' => true]);
        $this->assertTrue(HargaBeliGuard::seederAllowed());

        $this->app['env'] = 'testing';
        config(['harga_beli.allow_demo_seeder' => false]);
        $this->assertTrue(HargaBeliGuard::seederAllowed());
    }

    // ------------------------------------------------------------------ bagian 2: pemindai kode

    /**
     * File yang boleh memuat penanda tiap jalur. Menambah jalur baru = keputusan sadar: ubah daftar ini
     * (dan todolist_hpp.md), jangan sekadar membungkus kode baru dengan allow().
     */
    private const PATH_OWNERS = [
        'edit-product'    => [
            'app/Http/Controllers/ProductController.php',
            'app/Imports/ProductsImport.php', // hanya insert produk BARU (harga awal)
        ],
        'master-harga'    => ['app/Http/Controllers/OutletPriceController.php'],
        'outlet-purchase' => ['app/Http/Controllers/OutletPurchaseController.php'],
        'turunan'         => [
            'app/Http/Controllers/DeliveryOrderController.php',
            'app/Http/Controllers/RefundPenjualanController.php',
            'app/Services/OutletStockService.php',
        ],
        'maintenance'     => [
            'app/Console/Commands/BackfillHargaBeliBesar.php',
            'app/Console/Commands/BackfillOwnerStockLedger.php',
            'app/Console/Commands/FixHargaBeliSatuanBesar.php',
            'app/Console/Commands/RepairHargaBeli.php',
            'app/Console/Commands/RestoreZeroHpp.php',
        ],
        'seeder'          => [
            'database/seeders/DemoDataSeeder.php',
            'database/seeders/OutletReportSeeder.php',
        ],
    ];

    /**
     * File yang boleh menulis products / owner_stocks lewat query builder atau upsert tanpa HargaBeliGuard.
     * Kosong sejak Tahap 8 (ProductsImport sudah lewat guard). Jangan diisi lagi tanpa keputusan sadar.
     */
    private const RAW_WRITE_PENDING = [];

    /** @return array<string, string> path relatif => isi file */
    private function phpFiles(array $roots): array
    {
        $files = [];
        foreach ($roots as $root) {
            $dir = base_path($root);
            if (! is_dir($dir)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
                    $files[$relative] = file_get_contents($file->getPathname());
                }
            }
        }
        ksort($files);

        return $files;
    }

    public function test_penanda_jalur_hanya_ada_di_file_yang_berhak(): void
    {
        $found = [];
        $pattern = "/['\"](edit-product|outlet-purchase|master-harga|turunan|maintenance|seeder)['\"]/";

        foreach ($this->phpFiles(['app', 'database/seeders']) as $path => $code) {
            if ($path === 'app/Support/HargaBeliGuard.php') {
                continue;
            }
            if (preg_match_all($pattern, $code, $m)) {
                foreach (array_unique($m[1]) as $source) {
                    $found[$source][] = $path;
                }
            }
        }

        foreach ($found as $source => $paths) {
            $unexpected = array_diff($paths, self::PATH_OWNERS[$source] ?? []);
            $this->assertSame(
                [],
                array_values($unexpected),
                "Jalur '{$source}' dipakai di file yang tidak terdaftar: " . implode(', ', $unexpected)
                . '. Harga beli / HPP hanya boleh berubah lewat jalur resmi (lihat todolist_hpp.md).'
            );
        }
    }

    public function test_tidak_ada_penulisan_mentah_baru_ke_harga_beli_atau_hpp(): void
    {
        $patterns = [
            // DB::table('products'|'owner_stocks'[ as x])...->update/upsert/insert/increment/decrement
            "/(?:DB::table|->table)\\(\\s*['\"](?:products|owner_stocks)(?:\\s+as\\s+\\w+)?['\"]\\s*\\)[^;]*?->(?:update|upsert|insert|insertOrIgnore|updateOrInsert|increment|decrement)\\(/s",
            // Product::upsert / OwnerStock::insert ...
            "/\\b(?:Product|OwnerStock)::(?:upsert|insert|insertOrIgnore|insertGetId)\\(/",
            // Product::whereKey(..)->update(..), OwnerStock::where(..)->increment(..)
            "/\\b(?:Product|OwnerStock)::[^;]*?->(?:update|increment|decrement)\\(/s",
        ];

        $violations = [];
        foreach ($this->phpFiles(['app', 'database/seeders']) as $path => $code) {
            if (in_array($path, self::RAW_WRITE_PENDING, true)) {
                continue;
            }
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $code) && ! str_contains($code, 'HargaBeliGuard')) {
                    $violations[] = $path;
                    break;
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            'Penulisan langsung ke products / owner_stocks tanpa HargaBeliGuard di: ' . implode(', ', $violations)
            . '. Bungkus dengan HargaBeliGuard::allow() di jalur yang sah, atau jangan menulis kolom harga beli / HPP.'
        );
    }

    public function test_import_produk_tidak_menimpa_harga_beli_produk_lama(): void
    {
        $code = file_get_contents(base_path('app/Imports/ProductsImport.php'));

        // Daftar kolom yang di-update oleh upsert produk lama tidak boleh memuat harga_beli.
        $this->assertSame(1, preg_match('/Product::upsert\((.*?)\n\s+\);/s', $code, $m), 'Blok Product::upsert tidak ditemukan.');
        $this->assertStringNotContainsString("'harga_beli'", substr($m[1], strpos($m[1], "['id']")));
    }

    public function test_tidak_ada_pengecualian_penulisan_mentah_tersisa(): void
    {
        $this->assertSame([], self::RAW_WRITE_PENDING);
    }
}
