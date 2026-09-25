<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\Category;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Kas;
use App\Models\Outlet;
use App\Models\OutletPrice;
use App\Models\OutletPurchase;
use App\Models\OutletPurchaseItem;
use App\Models\OwnerStock;
use App\Models\PaymentMethod;
use App\Models\Pembelian;
use App\Models\PembelianProduct;
use App\Models\PembelianTransaction;
use App\Models\Pengeluaran;
use App\Models\Penjualan;
use App\Models\PenjualanItem;
use App\Models\PickingList;
use App\Models\PickingListItem;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductImportFailure;
use App\Models\ProductMinimumAdjustment;
use App\Models\Promotion;
use App\Models\RequestOrder;
use App\Models\RequestOrderItem;
use App\Models\RequestOrderNote;
use App\Models\Review;
use App\Models\Salesman;
use App\Models\Slider;
use App\Models\Stock;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\StockPembelian;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Complete, repeatable demo dataset for the warehouse and multi-outlet POS.
 *
 * The linked outlet-report fixtures are kept in OutletReportSeeder so this
 * seeder remains readable while a normal `db:seed` still creates a complete
 * warehouse and outlet reporting dataset.
 */
class DemoDataSeeder extends Seeder
{
    private array $outlets = [];

    private array $users = [];

    private array $categories = [];

    private array $suppliers = [];

    private array $products = [];

    private array $kas = [];

    private array $paymentMethods = [];

    private array $stocks = [];

    private array $ownerStocks = [];

    public function run(): void
    {
        $this->seedSettings();
        $this->seedMasterData();
        $this->seedWarehouseData();
        $this->seedOutletOperations();
        $this->seedSalesAndCustomerData();
        $this->call(OutletReportSeeder::class);
    }

    private function seedSettings(): void
    {
        Storage::disk('public')->put('settings.json', json_encode([
            'name' => 'Luwes Retail Demo',
            'email' => 'demo@luwes.test',
            'telp' => '+62 293 000 000',
            'address' => 'Jl. Pemuda No. 1, Magelang',
            'website' => 'https://luwes.test',
            'logo' => null,
            // MarketplaceController reads this key directly.
            'origin' => 'http://localhost:8000',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function seedMasterData(): void
    {
        $this->outlets = [
            'one' => $this->upsert(Outlet::class, ['name' => 'Outlet Demo 1'], [
                'logo' => null,
                'jenis_outlet' => 'toko',
                'alamat' => 'Jl. Demo No. 1, Magelang',
                'npwp' => '09.111.222.3-555.000',
                'slogan' => 'Belanja mudah setiap hari',
                'desc' => 'Outlet demo utama untuk pengujian kasir.',
                'footer' => 'Terima kasih telah berbelanja.',
            ]),
            'two' => $this->upsert(Outlet::class, ['name' => 'Outlet Demo 2'], [
                'logo' => null,
                'jenis_outlet' => 'toko',
                'alamat' => 'Jl. Demo No. 2, Magelang',
                'npwp' => '09.111.222.3-555.001',
                'slogan' => 'Pilihan lengkap harga bersahabat',
                'desc' => 'Outlet demo kedua untuk pengujian akses outlet.',
                'footer' => 'Simpan struk untuk kebutuhan Anda.',
            ]),
            'three' => $this->upsert(Outlet::class, ['name' => 'Outlet Demo 3'], [
                'logo' => null,
                'jenis_outlet' => 'toko',
                'alamat' => 'Jl. Demo No. 3, Magelang',
                'npwp' => '09.111.222.3-555.002',
                'slogan' => 'Dekat dan terpercaya',
                'desc' => 'Outlet demo ketiga untuk data multi-outlet.',
                'footer' => 'Terima kasih.',
            ]),
        ];

        $this->users = [
            'superadmin' => $this->seedUser('superadmin@mailinator.com', [
                'name' => 'Super Admin',
                'username' => 'superadmin@mailinator.com',
                'role' => 'superadmin',
                'outlet_id' => null,
                'limit_discount' => 100,
            ]),
            'owner' => $this->seedUser('demo.owner@example.test', [
                'name' => 'Demo Owner',
                'username' => 'demo-owner',
                'role' => 'owner',
                'outlet_id' => null,
                'limit_discount' => 50,
            ]),
            'warehouse' => $this->seedUser('demo.warehouse@example.test', [
                'name' => 'Admin Gudang Demo',
                'username' => 'demo-admin-gudang',
                'role' => 'admin-gudang',
                'outlet_id' => null,
                'limit_discount' => 25,
            ]),
            'staffOne' => $this->seedUser('demo.staff1@example.test', [
                'name' => 'Staff Outlet Demo 1',
                'username' => 'demo-staff-1',
                'role' => 'staff-outlet',
                'outlet_id' => $this->outlets['one']->id,
                'limit_discount' => 10,
            ]),
            'staffTwo' => $this->seedUser('demo.staff2@example.test', [
                'name' => 'Staff Outlet Demo 2',
                'username' => 'demo-staff-2',
                'role' => 'staff-outlet',
                'outlet_id' => $this->outlets['two']->id,
                'limit_discount' => 10,
            ]),
            'cashierOne' => $this->seedUser('demo.kasir1@example.test', [
                'name' => 'Kasir Outlet Demo 1',
                'username' => 'demo-kasir-1',
                'role' => 'kasir',
                'outlet_id' => $this->outlets['one']->id,
                'limit_discount' => 5,
            ]),
            'cashierTwo' => $this->seedUser('demo.kasir2@example.test', [
                'name' => 'Kasir Outlet Demo 2',
                'username' => 'demo-kasir-2',
                'role' => 'kasir',
                'outlet_id' => $this->outlets['two']->id,
                'limit_discount' => 5,
            ]),
            'customer' => $this->seedUser('demo.customer@example.test', [
                'name' => 'Pelanggan Demo',
                'username' => 'demo-customer',
                'role' => 'customer',
                'outlet_id' => null,
                'limit_discount' => 0,
            ]),
        ];

        foreach ([
            ['BCA', 'Bank Central Asia', '1234567890'],
            ['Mandiri', 'Bank Mandiri', '9876543210'],
            ['BRI', 'Bank Rakyat Indonesia', '1122334455'],
        ] as [$name, $accountName, $accountNumber]) {
            $this->upsert(Bank::class, ['name' => $name], [
                'name_rek' => $accountName,
                'no_rek' => $accountNumber,
                'pic' => 'demo-bank.png',
            ]);
        }

        foreach ([
            ['S00001', 'Supplier Demo Utama', 'Jl. Industri No. 10', '081234560001', [1, 4], 1],
            ['S00002', 'Supplier Demo Elektronik', 'Jl. Niaga No. 20', '081234560002', [2], 2],
            ['S00003', 'Supplier Demo FMCG', 'Jl. Pasar No. 30', '081234560003', [3, 6], 1],
            ['S00004', 'Supplier Demo Umum', 'Jl. Raya No. 40', '081234560004', null, null],
        ] as [$code, $name, $address, $phone, $deadlineDays, $intervalWeeks]) {
            $this->suppliers[$code] = $this->upsert(Supplier::class, ['kode_supplier' => $code], [
                'name' => $name,
                'alamat' => $address,
                'no_telp' => $phone,
                'deadline_days' => $deadlineDays,
                'deadline_interval_weeks' => $intervalWeeks,
                'deadline_reference_date' => today()->startOfWeek()->toDateString(),
            ]);
        }

        $productCategories = [
            'cosmetics' => $this->seedCategory('Kosmetik', 'product', $this->outlets['one']->id),
            'household' => $this->seedCategory('Kebutuhan Rumah Tangga', 'product', null),
            'fashion' => $this->seedCategory('Fashion', 'product', null),
            'electronics' => $this->seedCategory('Elektronik', 'product', null),
            'food' => $this->seedCategory('Makanan dan Minuman', 'product', null),
        ];
        $this->categories['expenses'] = $this->seedCategory('Operasional', 'pengeluaran', null);
        $this->categories['utilities'] = $this->seedCategory('Utilitas', 'pengeluaran', null);

        $productData = [
            'A' => [
                'code' => 'DEMO-001', 'name' => 'Shampoo Daily Care 250 ml', 'category' => $productCategories['cosmetics'],
                'brand' => 'Luwes Beauty', 'model' => 'Series A', 'warna' => 'Putih', 'ukuran' => '250 ml',
                'cost' => 100000, 'sale' => 106875, 'minimum' => 20, 'location' => 'Rak A-01',
                'unit' => 'pcs', 'large_unit' => 'dus', 'conversion' => 12, 'status' => 'sudah', 'supplier_keys' => ['S00001'],
            ],
            'B' => [
                'code' => 'DEMO-002', 'name' => 'Sabun Cuci Piring 800 ml', 'category' => $productCategories['household'],
                'brand' => 'Luwes Home', 'model' => 'Series B', 'warna' => 'Hijau', 'ukuran' => '800 ml',
                'cost' => 50000, 'sale' => 53820, 'minimum' => 15, 'location' => 'Rak B-01',
                'unit' => 'botol', 'large_unit' => 'dus', 'conversion' => 24, 'status' => 'sudah', 'supplier_keys' => ['S00001'],
            ],
            'C' => [
                'code' => 'DEMO-003', 'name' => 'Kaos Basic Unisex', 'category' => $productCategories['fashion'],
                'brand' => 'Luwes Wear', 'model' => 'Series C', 'warna' => 'Biru', 'ukuran' => 'M',
                'cost' => 75000, 'sale' => 90000, 'minimum' => 10, 'location' => 'Rak C-01',
                'unit' => 'pcs', 'large_unit' => 'dus', 'conversion' => 12, 'status' => 'tambahan_diskon', 'supplier_keys' => ['S00003'],
            ],
            'D' => [
                'code' => 'DEMO-004', 'name' => 'Kopi Bubuk Arabika 200 g', 'category' => $productCategories['food'],
                'brand' => 'Kopi Magelang', 'model' => 'Series D', 'warna' => 'Cokelat', 'ukuran' => '200 g',
                'cost' => 30000, 'sale' => 45000, 'minimum' => 25, 'location' => 'Rak D-01',
                'unit' => 'pack', 'large_unit' => 'karton', 'conversion' => 24, 'status' => 'sudah', 'supplier_keys' => ['S00003'],
            ],
            'E' => [
                'code' => 'DEMO-005', 'name' => 'Headset Bluetooth Basic', 'category' => $productCategories['electronics'],
                'brand' => 'SonicPro', 'model' => 'Model E', 'warna' => 'Hitam', 'ukuran' => 'Standard',
                'cost' => 350000, 'sale' => 450000, 'minimum' => 5, 'location' => 'Rak E-01',
                'unit' => 'pcs', 'large_unit' => 'box', 'conversion' => 10, 'status' => 'lunas', 'supplier_keys' => ['S00002'],
            ],
            'F' => [
                'code' => 'DEMO-006', 'name' => 'Celana Jogger Basic', 'category' => $productCategories['fashion'],
                'brand' => 'Luwes Wear', 'model' => 'Series F', 'warna' => 'Abu-abu', 'ukuran' => 'L',
                'cost' => 15000, 'sale' => 25000, 'minimum' => 20, 'location' => 'Rak C-02',
                'unit' => 'pcs', 'large_unit' => 'lusin', 'conversion' => 12, 'status' => 'belum_lunas', 'supplier_keys' => ['S00004'],
            ],
            'G' => [
                'code' => 'DEMO-007', 'name' => 'Lip Tint Velvet 3.5 ml', 'category' => $productCategories['cosmetics'],
                'brand' => 'Luwes Beauty', 'model' => 'LT-03', 'warna' => 'Rose', 'ukuran' => '3.5 ml',
                'cost' => 28000, 'sale' => 42000, 'minimum' => 10, 'location' => 'Rak A-02',
                'unit' => 'pcs', 'large_unit' => 'dus', 'conversion' => 12, 'status' => 'tambahan_diskon', 'supplier_keys' => ['S00001'],
            ],
            'H' => [
                'code' => 'DEMO-008', 'name' => 'Kaos Oversize Cotton', 'category' => $productCategories['fashion'],
                'brand' => 'Luwes Wear', 'model' => 'OS-01', 'warna' => 'Hitam', 'ukuran' => 'XL',
                'cost' => 45000, 'sale' => 65000, 'minimum' => 8, 'location' => 'Rak C-03',
                'unit' => 'pcs', 'large_unit' => 'lusin', 'conversion' => 12, 'status' => 'sudah', 'supplier_keys' => ['S00003'],
            ],
            'I' => [
                'code' => 'DEMO-009', 'name' => 'Beras Premium 5 kg', 'category' => $productCategories['food'],
                'brand' => 'Mitra Pangan', 'model' => 'BP-05', 'warna' => 'Putih', 'ukuran' => '5 kg',
                'cost' => 68000, 'sale' => 82000, 'minimum' => 8, 'location' => 'Rak D-02',
                'unit' => 'karung', 'large_unit' => 'pallet', 'conversion' => 20, 'status' => 'sudah', 'supplier_keys' => ['S00003'],
            ],
            'J' => [
                'code' => 'DEMO-010', 'name' => 'Lampu LED 12 Watt', 'category' => $productCategories['electronics'],
                'brand' => 'Terang Jaya', 'model' => 'LED-12W', 'warna' => 'Putih', 'ukuran' => '12W',
                'cost' => 20000, 'sale' => 32000, 'minimum' => 12, 'location' => 'Rak E-02',
                'unit' => 'pcs', 'large_unit' => 'box', 'conversion' => 20, 'status' => 'sudah', 'supplier_keys' => ['S00002'],
            ],
            'K' => [
                'code' => 'DEMO-011', 'name' => 'Organizer Plastik Serbaguna', 'category' => $productCategories['household'],
                'brand' => 'Luwes Home', 'model' => 'ORG-01', 'warna' => 'Bening', 'ukuran' => '30 x 20 cm',
                'cost' => 18000, 'sale' => 29000, 'minimum' => 10, 'location' => 'Rak B-02',
                'unit' => 'pcs', 'large_unit' => 'karton', 'conversion' => 24, 'status' => 'sudah', 'supplier_keys' => ['S00001'],
            ],
            'L' => [
                'code' => 'DEMO-012', 'name' => 'Tumbler Stainless 500 ml', 'category' => $productCategories['household'],
                'brand' => 'Luwes Home', 'model' => 'TMB-500', 'warna' => 'Biru', 'ukuran' => '500 ml',
                'cost' => 55000, 'sale' => 79000, 'minimum' => 6, 'location' => 'Rak B-03',
                'unit' => 'pcs', 'large_unit' => 'box', 'conversion' => 12, 'status' => 'sudah', 'supplier_keys' => ['S00004'],
            ],
        ];

        foreach ($productData as $key => $data) {
            $this->products[$key] = $this->upsert(Product::class, ['code' => $data['code']], [
                'pic' => 'products/'.strtolower($data['code']).'.png',
                'name' => $data['name'],
                'category_id' => $data['category']->id,
                'desc' => 'Data demo untuk '.$data['name'].'.',
                'warna' => $data['warna'],
                'ukuran' => $data['ukuran'],
                'brand' => $data['brand'],
                'model' => $data['model'],
                'is_serialized' => false,
                'harga_beli' => $data['cost'],
                'harga_jual' => $data['sale'],
                'diskon' => 0,
                'satuan' => $data['unit'],
                'min_stock' => $data['minimum'],
                'lokasi' => $data['location'],
                'status_produk' => $data['status'],
                'status_produk_note' => $data['status'] === 'tambahan_diskon' ? 'Program diskon toko demo.' : null,
                'satuan_besar' => $data['large_unit'],
                'konversi_qty' => $data['conversion'],
            ]);

            $supplierKeys = $data['supplier_keys'];
            $this->products[$key]->suppliers()->sync(array_map(fn ($supplierKey) => $this->suppliers[$supplierKey]->id, $supplierKeys));
        }

        foreach ([
            ['Tunai', null, 'Pembayaran cash di kasir.'],
            ['Transfer BCA', '1234567890', 'Pembayaran melalui rekening demo.'],
            ['QRIS', null, 'Pembayaran QRIS demo.'],
            ['Debit Mandiri', '9876543210', 'Pembayaran kartu debit demo.'],
        ] as [$name, $bankNumber, $description]) {
            $this->paymentMethods[$name] = $this->upsert(PaymentMethod::class, ['name' => $name], [
                'bank_number' => $bankNumber,
                'desc' => $description,
            ]);
        }

        foreach ([
            ['Sales Demo 1', 'Jl. Sales No. 1', '082200000001'],
            ['Sales Demo 2', 'Jl. Sales No. 2', '082200000002'],
        ] as [$name, $address, $phone]) {
            $this->upsert(Salesman::class, ['name' => $name], [
                'alamat' => $address,
                'no_telp' => $phone,
            ]);
        }

        foreach ([
            ['Banner Promo Demo', 'active', 'default', 'Promo demo Luwes', 'sliders/promo-demo.png'],
            ['Banner Informasi Demo', 'active', 'link', 'Informasi toko demo', 'sliders/info-demo.png'],
            ['Banner Lama Demo', 'non-active', 'default', 'Banner arsip demo', 'sliders/archive-demo.png'],
        ] as [$description, $status, $type, $text, $picture]) {
            $this->upsert(Slider::class, ['pic' => $picture], [
                'status' => $status,
                'type' => $type,
                'desc' => $description.' - '.$text,
            ]);
        }

        foreach ($this->outlets as $key => $outlet) {
            $this->kas[$key] = $this->upsert(Kas::class, [
                'name' => 'Kas '.$outlet->name,
                'outlet_id' => $outlet->id,
            ], [
                'nominal' => $key === 'one' ? '5000000' : '3000000',
            ]);
        }
        $this->kas['warehouse'] = $this->upsert(Kas::class, [
            'name' => 'Kas Gudang Demo',
            'outlet_id' => null,
        ], ['nominal' => '15000000']);

        foreach ($this->outlets as $outletKey => $outlet) {
            foreach ($this->products as $productKey => $product) {
                $isMainOutlet = $outletKey === 'one';
                $isPercentage = in_array($productKey, ['A', 'B'], true);
                // Outlet Demo 3 represents a Beauty-style outlet whose selling
                // price is adjusted by a nominal Rp100 per product.
                $isBeautyExample = $outletKey === 'three';
                $this->upsert(OutletPrice::class, [
                    'outlet_id' => $outlet->id,
                    'product_id' => $product->id,
                ], [
                    'disc_brand_type' => $isPercentage ? 'percentage' : 'nominal',
                    'disc_brand_value' => $isPercentage ? ($productKey === 'A' ? 10 : 8) : 0,
                    'margin_type' => $isPercentage ? 'percentage' : 'nominal',
                    'margin_value' => $isPercentage ? ($productKey === 'A' ? 25 : 30) : ($productKey === 'C' ? 15000 : 5000),
                    'disc_toko_type' => $isPercentage && $isMainOutlet ? 'percentage' : 'nominal',
                    'disc_toko_value' => $isPercentage && $isMainOutlet ? ($productKey === 'A' ? 5 : 10) : ($productKey === 'C' ? 0 : 1000),
                    'outlet_adjustment_type' => 'nominal',
                    'outlet_adjustment_value' => $isBeautyExample ? 100 : 0,
                    'effective_from' => today()->subDays(30)->toDateString(),
                    'effective_until' => today()->addMonths(6)->toDateString(),
                    'is_active' => true,
                    'created_by' => $this->users['owner']->id,
                ]);
            }
        }

        $this->upsert(Voucher::class, ['code' => 'DEMO-PCT-10'], [
            'name' => 'Voucher Demo 10%',
            'type' => 'percentage',
            'jenis' => 'keseluruhan',
            'limit' => 1,
            'value' => 10,
            'min_purchase' => 300000,
            'max_discount_amount' => 50000,
            'outlet_id' => $this->outlets['one']->id,
            'product_id' => null,
            'kasir_id' => (string) $this->users['cashierOne']->id,
            'start_at' => now()->subDays(2),
            'end_at' => now()->addDays(30),
            'desc' => 'Voucher persentase demo untuk transaksi pertama.',
        ]);
        $this->upsert(Voucher::class, ['code' => 'DEMO-RP-25000'], [
            'name' => 'Voucher Demo Rp25.000',
            'type' => 'nominal',
            'jenis' => 'keseluruhan',
            'limit' => 10,
            'value' => 25000,
            'min_purchase' => 100000,
            'max_discount_amount' => null,
            'outlet_id' => $this->outlets['one']->id,
            'product_id' => null,
            'kasir_id' => null,
            'start_at' => now()->subDays(2),
            'end_at' => now()->addDays(30),
            'desc' => 'Voucher nominal demo yang masih aktif.',
        ]);
        $this->upsert(Voucher::class, ['code' => 'DEMO-GLOBAL-5'], [
            'name' => 'Voucher Global Demo 5%',
            'type' => 'percentage',
            'jenis' => 'keseluruhan',
            'limit' => 100,
            'value' => 5,
            'min_purchase' => 50000,
            'max_discount_amount' => null,
            'outlet_id' => null,
            'product_id' => null,
            'kasir_id' => null,
            'start_at' => now()->subDays(2),
            'end_at' => now()->addDays(30),
            'desc' => 'Voucher global untuk pengujian cakupan outlet.',
        ]);
        $this->upsert(Voucher::class, ['code' => 'DEMO-C-PRODUCT'], [
            'name' => 'Voucher Produk C Demo',
            'type' => 'nominal',
            'jenis' => 'satuan',
            'limit' => 5,
            'value' => 5000,
            'min_purchase' => 0,
            'max_discount_amount' => null,
            'outlet_id' => $this->outlets['two']->id,
            'product_id' => $this->products['C']->id,
            'kasir_id' => null,
            'start_at' => now()->subDays(2),
            'end_at' => now()->addDays(30),
            'desc' => 'Voucher terbatas untuk Produk Demo C.',
        ]);

        $promoStart = Carbon::create(2026, 9, 1, 0, 0, 0, config('app.timezone'));
        $promoEnd = Carbon::create(2026, 9, 9, 23, 59, 59, config('app.timezone'));
        foreach ([
            ['TOPED-9.9', 'Voucher Marketplace 9.9', 'percentage', 10, 100000, 50000, null, 'Voucher demo marketplace untuk dikombinasikan dengan voucher toko.'],
            ['TOKO-9.9', 'Voucher Toko 9.9 Rp15.000', 'nominal', 15000, 100000, null, null, 'Voucher demo toko selama event 9.9.'],
            ['PRODUK-A-9.9', 'Voucher Produk A 9.9', 'nominal', 15000, 0, null, $this->products['A']->id, 'Voucher demo yang hanya berlaku untuk Produk Demo A.'],
        ] as [$code, $name, $type, $value, $minPurchase, $maxDiscount, $productId, $description]) {
            $this->upsert(Voucher::class, ['code' => $code], [
                'name' => $name,
                'type' => $type,
                'jenis' => $productId ? 'satuan' : 'keseluruhan',
                'limit' => 100,
                'value' => $value,
                'min_purchase' => $minPurchase,
                'max_discount_amount' => $maxDiscount,
                'outlet_id' => $this->outlets['one']->id,
                'product_id' => $productId,
                'kasir_id' => null,
                'start_at' => $promoStart,
                'end_at' => $promoEnd,
                'desc' => $description,
            ]);
        }

        $this->seedPromotions($promoStart, $promoEnd);

        $this->upsert(ProductMinimumAdjustment::class, [
            'product_id' => $this->products['A']->id,
            'active_from' => today()->subDays(7)->toDateString(),
        ], [
            'adjustment_percentage' => 20,
            'active_until' => today()->addMonths(2)->toDateString(),
            'created_by' => $this->users['warehouse']->id,
        ]);
        $this->upsert(ProductMinimumAdjustment::class, [
            'product_id' => $this->products['D']->id,
            'active_from' => today()->subDays(3)->toDateString(),
        ], [
            'adjustment_percentage' => 10,
            'active_until' => null,
            'created_by' => $this->users['warehouse']->id,
        ]);

        $this->seedExpenses();
    }

    private function seedPromotions(Carbon $startAt, Carbon $endAt): void
    {
        $flashSale = $this->upsert(Promotion::class, ['code' => 'FLASH-9.9-2026'], [
            'name' => 'Flash Sale 9.9 — Produk Pilihan',
            'type' => 'flash_sale',
            'discount_type' => 'percentage',
            'discount_value' => 9.9,
            'bundle_price' => null,
            'max_qty' => 10,
            'quota_qty' => null,
            'min_purchase' => 0,
            'outlet_id' => $this->outlets['one']->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'is_active' => true,
            'stackable' => false,
            'priority' => 50,
            'desc' => 'Flash sale otomatis 9.9, maksimal 10 pcs per produk per transaksi.',
            'created_by' => $this->users['owner']->id,
        ]);
        $this->seedPromotionProducts($flashSale, [
            $this->products['A']->id => 1,
            $this->products['B']->id => 1,
        ]);
        $this->seedPromotionBonuses($flashSale, []);

        $bundleC = $this->upsert(Promotion::class, ['code' => 'BUNDLE-9.9-C-2PCS'], [
            'name' => 'Beli 1 Produk Demo C, Gratis 1 Lagi',
            'type' => 'bundle',
            'discount_type' => 'nominal',
            'discount_value' => 0,
            'bundle_price' => 0,
            'max_qty' => null,
            'quota_qty' => null,
            'min_purchase' => 0,
            'outlet_id' => $this->outlets['one']->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'is_active' => true,
            'stackable' => false,
            'priority' => 20,
            'desc' => 'Beli 1 Produk Demo C, dapat 1 Produk Demo C gratis sebagai bonus.',
            'created_by' => $this->users['owner']->id,
        ]);
        $this->seedPromotionProducts($bundleC, [
            $this->products['C']->id => 1,
        ]);
        $this->seedPromotionBonuses($bundleC, [
            ['name' => 'Produk Demo C', 'qty' => 1],
        ]);

        $bundleAC = $this->upsert(Promotion::class, ['code' => 'BUNDLE-9.9-A-C'], [
            'name' => 'Beli 10 Produk Demo D, Dapat Mug Spesial',
            'type' => 'bundle',
            'discount_type' => 'nominal',
            'discount_value' => 0,
            'bundle_price' => 0,
            'max_qty' => null,
            'quota_qty' => null,
            'min_purchase' => 0,
            'outlet_id' => $this->outlets['one']->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'is_active' => true,
            'stackable' => false,
            'priority' => 10,
            'desc' => 'Beli 10 Produk Demo D, dapat 1 Mug Spesial sebagai bonus.',
            'created_by' => $this->users['owner']->id,
        ]);
        $this->seedPromotionProducts($bundleAC, [
            $this->products['D']->id => 10,
        ]);
        $this->seedPromotionBonuses($bundleAC, [
            ['name' => 'Mug Spesial', 'qty' => 1],
        ]);
    }

    private function seedPromotionProducts(Promotion $promotion, array $products): void
    {
        $promotion->promotionProducts()->delete();

        foreach ($products as $productId => $requiredQty) {
            $promotion->promotionProducts()->create([
                'product_id' => $productId,
                'required_qty' => $requiredQty,
            ]);
        }
    }

    private function seedPromotionBonuses(Promotion $promotion, array $bonuses): void
    {
        $promotion->bonuses()->delete();

        foreach ($bonuses as $bonus) {
            $promotion->bonuses()->create([
                'name' => $bonus['name'],
                'qty' => $bonus['qty'],
            ]);
        }
    }

    private function seedWarehouseData(): void
    {
        $publishedPurchase = $this->upsert(Pembelian::class, ['code' => 'PO-DEMO-001'], [
            'code_gr' => 'GR-DEMO-001',
            'outlet_id' => null,
            'supplier_id' => $this->suppliers['S00001']->id,
            'kas_id' => $this->kas['warehouse']->id,
            'total' => '15599000',
            'is_published' => true,
            'owner_approval_status' => 'approved',
            'owner_approved_by' => $this->users['owner']->id,
            'owner_approved_at' => now()->subDays(12),
            'owner_approval_note' => 'PO demo disetujui owner.',
            'receipt_date' => now()->subDays(10),
            'receipt_pic' => $this->users['warehouse']->name,
            'receipt_status' => 'completed',
            'receipt_photo' => null,
        ]);

        $publishedLines = [
            ['A', 50, 100000, 'WH-DEMO-A-001', 37],
            ['B', 40, 50000, 'WH-DEMO-B-001', 32],
            ['C', 25, 75000, 'WH-DEMO-C-001', 25],
            ['D', 24, 30000, 'WH-DEMO-D-001', 24],
            ['E', 4, 350000, 'WH-DEMO-E-001', 4],
            ['G', 24, 28000, 'WH-DEMO-G-001', 24],
            ['H', 24, 45000, 'WH-DEMO-H-001', 24],
            ['I', 20, 68000, 'WH-DEMO-I-001', 20],
            ['J', 20, 20000, 'WH-DEMO-J-001', 20],
            ['K', 24, 18000, 'WH-DEMO-K-001', 24],
            ['L', 12, 55000, 'WH-DEMO-L-001', 12],
        ];
        foreach ($publishedLines as [$productKey, $qty, $cost, $sku, $remainingQty]) {
            $product = $this->products[$productKey];
            // Remove serial-based rows from older demo-seeder versions before
            // recreating this product as ordinary quantity-based stock.
            StockPembelian::query()
                ->where('pembelian_id', $publishedPurchase->id)
                ->where('product_id', $product->id)
                ->whereNotNull('serial_number')
                ->delete();
            $this->upsert(StockPembelian::class, [
                'pembelian_id' => $publishedPurchase->id,
                'product_id' => $product->id,
            ], [
                'sku' => $sku,
                'qty' => 0,
                'harga_beli' => $cost,
                'subtotal' => $qty * $cost,
                'expired_at' => today()->addYear()->toDateString(),
                'condition' => 'new',
                'status' => 'sent_to_outlet',
                'serial_number' => null,
            ]);
            $this->seedStock($publishedPurchase, $product, $sku, $cost, $remainingQty, $product->lokasi);

            $this->upsert(PembelianProduct::class, [
                'pembelian_id' => $publishedPurchase->id,
                'product_id' => $product->id,
            ], [
                'harga_beli' => $cost,
                'qty' => $qty,
                'qty_diterima' => $qty,
                'subtotal' => $qty * $cost,
                'expired_at' => today()->addYear()->toDateString(),
                'serial_numbers' => null,
            ]);
        }

        $paidAmount = 15599000;
        $this->upsert(PembelianTransaction::class, ['pembelian_id' => $publishedPurchase->id], [
            'payment_date' => now()->subDays(9),
            'payment_method' => 'Transfer BCA',
            'payment_reference' => 'TRX-PO-DEMO-001',
            'payment_history' => [[
                'date' => now()->subDays(9)->toDateTimeString(),
                'amount' => $paidAmount,
                'method' => 'Transfer BCA',
            ]],
            'status' => 'paid',
            'amount' => $paidAmount,
            'bukti_transfer' => null,
            'notes' => 'Pembayaran penuh PO demo.',
        ]);

        $draftPurchase = $this->upsert(Pembelian::class, ['code' => 'PO-DEMO-002'], [
            'code_gr' => null,
            'outlet_id' => $this->outlets['two']->id,
            'supplier_id' => $this->suppliers['S00004']->id,
            'kas_id' => $this->kas['two']->id,
            'total' => '750000',
            'is_published' => false,
            'owner_approval_status' => 'pending',
            'owner_approved_by' => null,
            'owner_approved_at' => null,
            'owner_approval_note' => null,
            'receipt_date' => null,
            'receipt_pic' => null,
            'receipt_status' => 'draft',
            'receipt_photo' => null,
        ]);
        foreach ([['D', 20, 30000], ['F', 10, 15000]] as [$productKey, $qty, $cost]) {
            $product = $this->products[$productKey];
            $this->upsert(PembelianProduct::class, [
                'pembelian_id' => $draftPurchase->id,
                'product_id' => $product->id,
            ], [
                'harga_beli' => $cost,
                'qty' => $qty,
                'qty_diterima' => 0,
                'subtotal' => $qty * $cost,
                'expired_at' => today()->addMonths(9)->toDateString(),
                'serial_numbers' => null,
            ]);
            $this->upsert(StockPembelian::class, [
                'pembelian_id' => $draftPurchase->id,
                'product_id' => $product->id,
            ], [
                'sku' => 'STAGE-'.$product->code,
                'harga_beli' => $cost,
                'qty' => $qty,
                'subtotal' => $qty * $cost,
                'expired_at' => today()->addMonths(9)->toDateString(),
                'condition' => 'new',
                'status' => 'available',
            ]);
        }
        $this->upsert(PembelianTransaction::class, ['pembelian_id' => $draftPurchase->id], [
            'payment_date' => null,
            'payment_method' => null,
            'payment_reference' => null,
            'payment_history' => [],
            'status' => 'unpaid',
            'amount' => 0,
            'bukti_transfer' => null,
            'notes' => 'Menunggu approval owner.',
        ]);

        $this->seedWarehouseMovement('A', $publishedPurchase->id, 50, 0, 50, 'Penerimaan PO-DEMO-001.');
        $this->seedWarehouseMovement('B', $publishedPurchase->id, 40, 0, 40, 'Penerimaan PO-DEMO-001.');
        $this->seedWarehouseMovement('C', $publishedPurchase->id, 25, 0, 25, 'Penerimaan PO-DEMO-001.');
        $this->seedWarehouseMovement('D', $publishedPurchase->id, 24, 0, 24, 'Penerimaan PO-DEMO-001.');
        $this->seedWarehouseMovement('E', $publishedPurchase->id, 4, 0, 4, 'Penerimaan PO-DEMO-001.');
        $this->seedWarehouseMovement('G', $publishedPurchase->id, 24, 0, 24, 'Penerimaan PO-DEMO-001.');
        $this->seedWarehouseMovement('H', $publishedPurchase->id, 24, 0, 24, 'Penerimaan PO-DEMO-001.');
        $this->seedWarehouseMovement('I', $publishedPurchase->id, 20, 0, 20, 'Penerimaan PO-DEMO-001.');
        $this->seedWarehouseMovement('J', $publishedPurchase->id, 20, 0, 20, 'Penerimaan PO-DEMO-001.');
        $this->seedWarehouseMovement('K', $publishedPurchase->id, 24, 0, 24, 'Penerimaan PO-DEMO-001.');
        $this->seedWarehouseMovement('L', $publishedPurchase->id, 12, 0, 12, 'Penerimaan PO-DEMO-001.');

        foreach ([
            ['D', 6], ['E', 2], ['G', 12], ['H', 10], ['I', 8], ['J', 10], ['K', 12], ['L', 6],
        ] as [$productKey, $qty]) {
            $product = $this->products[$productKey];
            $this->ownerStocks['one'.$productKey] = $this->seedOwnerStock(
                $this->outlets['one'],
                $product,
                'DEMO-OUTLET-'.$productKey.'-001',
                $this->stocks[$product->code],
                $qty,
                Pembelian::class,
                $publishedPurchase->id,
                'Penerimaan stok demo dari PO-DEMO-001.'
            );
        }
    }

    private function seedOutletOperations(): void
    {
        $requestOrder = $this->upsert(RequestOrder::class, ['code' => 'REQ-DEMO-00001'], [
            'owner_id' => $this->outlets['one']->id,
            'requested_by' => $this->users['staffOne']->id,
            'verified_by' => $this->users['warehouse']->id,
            'request_date' => today()->subDays(8)->toDateString(),
            'verified_date' => today()->subDays(7)->toDateString(),
            'status' => 'approved',
            'notes' => 'Permintaan stok rutin Outlet Demo 1.',
            'verification_notes' => 'Stok tersedia dan disetujui admin gudang.',
        ]);

        foreach ([['A', 12, 12], ['B', 8, 8]] as [$productKey, $requested, $approved]) {
            $product = $this->products[$productKey];
            $this->upsert(RequestOrderItem::class, [
                'request_order_id' => $requestOrder->id,
                'product_id' => $product->id,
            ], [
                'stock_id' => $this->stocks[$productKey]->id,
                'qty_requested' => $requested,
                'qty_approved' => $approved,
                'item_status' => 'approved',
                'notes' => 'Item demo siap diproses.',
            ]);
        }
        $this->upsert(RequestOrderNote::class, [
            'request_order_id' => $requestOrder->id,
            'kategori' => 'Tester toko',
        ], [
            'qty' => 2,
            'nama_pj' => 'Penanggung Jawab Outlet Demo 1',
        ]);

        $pickingList = $this->upsert(PickingList::class, ['code' => 'PICK-DEMO-00001'], [
            'request_order_id' => $requestOrder->id,
            'picker_id' => $this->users['warehouse']->id,
            'picker_name' => $this->users['warehouse']->name,
            'status' => 'completed',
            'started_at' => now()->subDays(6),
            'completed_at' => now()->subDays(6)->addHours(2),
            'notes' => 'Picking demo selesai.',
        ]);
        foreach ([['A', 12], ['B', 8]] as [$productKey, $qty]) {
            $product = $this->products[$productKey];
            $stock = $this->stocks[$productKey];
            $this->upsert(PickingListItem::class, [
                'picking_list_id' => $pickingList->id,
                'product_id' => $product->id,
            ], [
                'stock_id' => $stock->id,
                'qty_to_pick' => $qty,
                'qty_picked' => $qty,
                'location' => $product->lokasi,
                'sku' => $stock->sku,
                'is_picked' => true,
            ]);
        }

        $deliveryOrder = $this->upsert(DeliveryOrder::class, ['code' => 'DO-DEMO-001'], [
            'request_order_id' => $requestOrder->id,
            'picking_list_id' => $pickingList->id,
            'owner_id' => $this->outlets['one']->id,
            'prepared_by' => $this->users['warehouse']->id,
            'received_by' => $this->users['staffOne']->id,
            'delivery_date' => today()->subDays(5)->toDateString(),
            'received_date' => today()->subDays(4)->toDateString(),
            'status' => 'delivered',
            'notes' => 'Pengiriman demo dari gudang ke Outlet Demo 1.',
            'photo_path' => null,
        ]);
        foreach ([['A', 12], ['B', 8]] as [$productKey, $qty]) {
            $product = $this->products[$productKey];
            $stock = $this->stocks[$productKey];
            $this->upsert(DeliveryOrderItem::class, [
                'delivery_order_id' => $deliveryOrder->id,
                'product_id' => $product->id,
            ], [
                'stock_id' => $stock->id,
                'qty' => $qty,
                'qty_sent' => $qty,
                'sku' => $stock->sku,
                'expired_at' => $stock->expired_at,
                'harga_beli' => $stock->harga_beli,
            ]);
        }
        $this->seedMovement($this->products['A'], null, null, $this->users['warehouse'], 'out', DeliveryOrder::class, $deliveryOrder->id, 0, 12, 38, 'Pengeluaran stok gudang untuk DO-DEMO-000001.');
        $this->seedMovement($this->products['B'], null, null, $this->users['warehouse'], 'out', DeliveryOrder::class, $deliveryOrder->id, 0, 8, 32, 'Pengeluaran stok gudang untuk DO-DEMO-000001.');

        $this->ownerStocks['oneA'] = $this->seedOwnerStock(
            $this->outlets['one'], $this->products['A'], 'DEMO-DO-A-001', $this->stocks['A'], 9,
            DeliveryOrder::class, $deliveryOrder->id, 'Penerimaan stok dari delivery order demo.'
        );
        $this->ownerStocks['oneB'] = $this->seedOwnerStock(
            $this->outlets['one'], $this->products['B'], 'DEMO-DO-B-001', $this->stocks['B'], 5,
            DeliveryOrder::class, $deliveryOrder->id, 'Penerimaan stok dari delivery order demo.'
        );

        $pendingRequest = $this->upsert(RequestOrder::class, ['code' => 'REQ-DEMO-00002'], [
            'owner_id' => $this->outlets['two']->id,
            'requested_by' => $this->users['staffTwo']->id,
            'verified_by' => null,
            'request_date' => today()->subDay()->toDateString(),
            'verified_date' => null,
            'status' => 'pending',
            'notes' => 'Permintaan stok menunggu verifikasi gudang.',
            'verification_notes' => null,
        ]);
        foreach ([['A', 5], ['D', 4]] as [$productKey, $qty]) {
            $this->upsert(RequestOrderItem::class, [
                'request_order_id' => $pendingRequest->id,
                'product_id' => $this->products[$productKey]->id,
            ], [
                'stock_id' => null,
                'qty_requested' => $qty,
                'qty_approved' => 0,
                'item_status' => 'pending',
                'notes' => null,
            ]);
        }

        $purchaseOne = $this->seedOutletPurchase(
            'POUT-DEMO-001', $this->outlets['one'], $this->users['cashierOne'], $this->suppliers['S00003'],
            'NOTA-OUTLET-00001', 450000, 450000, 'Tunai', 'Belanja langsung Outlet Demo 1.'
        );
        $this->ownerStocks['oneC'] = $this->seedDirectOwnerStock(
            $this->outlets['one'], $this->products['C'], 'DEMO-BELI-C-001', 5, 75000,
            OutletPurchase::class, $purchaseOne->id, $this->users['cashierOne'], 'Penerimaan pembelian langsung outlet.'
        );
        $this->seedOutletPurchaseItem($purchaseOne, $this->products['C'], $this->ownerStocks['oneC'], 6, 75000, 'DEMO-BELI-C-001');

        $purchaseTwo = $this->seedOutletPurchase(
            'POUT-DEMO-002', $this->outlets['two'], $this->users['cashierTwo'], $this->suppliers['S00004'],
            'NOTA-OUTLET-00002', 240000, 120000, 'Transfer BCA', 'Belanja langsung Outlet Demo 2.'
        );
        $this->ownerStocks['twoD'] = $this->seedDirectOwnerStock(
            $this->outlets['two'], $this->products['D'], 'DEMO-POUT-D-001', 8, 30000,
            OutletPurchase::class, $purchaseTwo->id, $this->users['cashierTwo'], 'Penerimaan pembelian langsung outlet.'
        );
        $this->seedOutletPurchaseItem($purchaseTwo, $this->products['D'], $this->ownerStocks['twoD'], 8, 30000, 'DEMO-POUT-D-001');

        $warehouseAdjustment = $this->upsert(StockAdjustment::class, [
            'product_id' => $this->products['A']->id,
            'stock_id' => $this->stocks['A']->id,
            'owner_id' => null,
            'sku' => $this->stocks['A']->sku,
        ], [
            'owner_stock_id' => null,
            'sku' => $this->stocks['A']->sku,
            'adjustment_date' => today()->subDays(3)->toDateString(),
            'quantity' => -1,
            'system_qty' => 38,
            'physical_qty' => 37,
            'reason' => 'Selisih hitung stok gudang demo.',
            'status' => 'Selesai',
            'keterangan' => 'Stock opname gudang demo.',
        ]);
        $this->seedMovement($this->products['A'], null, null, $this->users['warehouse'], 'adjustment', StockAdjustment::class, $warehouseAdjustment->id, 0, 1, 37, 'Penyesuaian stok gudang demo.');

        $outletAdjustment = $this->upsert(StockAdjustment::class, [
            'product_id' => $this->products['A']->id,
            'stock_id' => $this->stocks['A']->id,
            'owner_id' => $this->outlets['one']->id,
            'owner_stock_id' => $this->ownerStocks['oneA']->id,
            'sku' => 'DEMO-DO-A-001',
        ], [
            'sku' => 'DEMO-DO-A-001',
            'adjustment_date' => today()->subDays(2)->toDateString(),
            'quantity' => -1,
            'system_qty' => 12,
            'physical_qty' => 11,
            'reason' => 'Selisih hitung stok toko demo.',
            'status' => 'Selesai',
            'keterangan' => 'Stock opname Outlet Demo 1.',
        ]);
        $this->seedMovement($this->products['A'], $this->outlets['one'], $this->ownerStocks['oneA'], $this->users['staffOne'], 'adjustment', StockAdjustment::class, $outletAdjustment->id, 0, 1, 11, 'Penyesuaian stok toko demo.');

        $this->seedProductImports();
    }

    private function seedSalesAndCustomerData(): void
    {
        $voucher = Voucher::where('code', 'DEMO-PCT-10')->firstOrFail();
        $cashier = $this->users['cashierOne'];
        $customer = $this->users['customer'];
        $salesman = Salesman::where('name', 'Sales Demo 1')->firstOrFail();

        $saleOne = $this->upsert(Penjualan::class, ['code' => 'INV-DEMO-000001'], [
            'customer_id' => (string) $customer->id,
            'outlet_id' => $this->outlets['one']->id,
            'kasir_id' => (string) $cashier->id,
            'kas_id' => $this->kas['one']->id,
            'voucher_id' => $voucher->id,
            'salesman_id' => (string) $salesman->id,
            'discount' => 29190,
            'total' => 337689,
            'subtotal' => 375210,
            'discount_total' => 29190,
            'voucher_total' => 37521,
            'grand_total' => 337689,
            'paid_amount' => 400000,
            'change_amount' => 62311,
            'payment_method_id' => $this->paymentMethods['Tunai']->id,
            'payment_method_name' => 'Tunai',
            'status' => 'paid',
        ]);
        $this->seedSaleItem($saleOne, $this->products['A'], $this->ownerStocks['oneA'], [
            'qty' => 2,
            'hpp' => 100000,
            'harga_akhir' => 90000,
            'disc_brand_type' => 'nominal',
            'disc_brand_value' => 10000,
            'disc_brand_amount' => 10000,
            'margin_type' => 'percentage',
            'margin_value' => 25,
            'margin_amount' => 22500,
            'harga_aktif' => 112500,
            'disc_toko_type' => 'percentage',
            'disc_toko_value' => 5,
            'disc_toko_amount' => 5625,
            'price' => 106875,
            'subtotal' => 213750,
        ]);
        $this->seedSaleItem($saleOne, $this->products['B'], $this->ownerStocks['oneB'], [
            'qty' => 3,
            'hpp' => 50000,
            'harga_akhir' => 46000,
            'disc_brand_type' => 'nominal',
            'disc_brand_value' => 4000,
            'disc_brand_amount' => 4000,
            'margin_type' => 'percentage',
            'margin_value' => 30,
            'margin_amount' => 13800,
            'harga_aktif' => 59800,
            'disc_toko_type' => 'percentage',
            'disc_toko_value' => 10,
            'disc_toko_amount' => 5980,
            'price' => 53820,
            'subtotal' => 161460,
        ]);
        $this->seedMovement($this->products['A'], $this->outlets['one'], $this->ownerStocks['oneA'], $cashier, 'out', Penjualan::class, $saleOne->id, 0, 2, 9, 'Penjualan INV-DEMO-000001.');
        $this->seedMovement($this->products['B'], $this->outlets['one'], $this->ownerStocks['oneB'], $cashier, 'out', Penjualan::class, $saleOne->id, 0, 3, 5, 'Penjualan INV-DEMO-000001.');
        $this->upsert(VoucherRedemption::class, ['voucher_id' => $voucher->id], [
            'penjualan_id' => $saleOne->id,
            'outlet_id' => $this->outlets['one']->id,
            'cashier_id' => $cashier->id,
            'code' => $voucher->code,
            'type' => $voucher->type,
            'value' => $voucher->value,
            'amount' => 37521,
        ]);
        $this->upsert(Transaction::class, ['penjualan_id' => $saleOne->id], [
            'payment_method' => (string) $this->paymentMethods['Tunai']->id,
            'tanggal' => now()->subDay(),
            'status' => 'paid',
            'pic' => $cashier->name,
        ]);

        $saleTwo = $this->upsert(Penjualan::class, ['code' => 'INV-DEMO-000002'], [
            'customer_id' => null,
            'outlet_id' => $this->outlets['one']->id,
            'kasir_id' => (string) $cashier->id,
            'kas_id' => $this->kas['one']->id,
            'voucher_id' => null,
            'salesman_id' => null,
            'discount' => 0,
            'total' => 90000,
            'subtotal' => 90000,
            'discount_total' => 0,
            'voucher_total' => 0,
            'grand_total' => 90000,
            'paid_amount' => 100000,
            'change_amount' => 10000,
            'payment_method_id' => $this->paymentMethods['QRIS']->id,
            'payment_method_name' => 'QRIS',
            'status' => 'paid',
        ]);
        $this->seedSaleItem($saleTwo, $this->products['C'], $this->ownerStocks['oneC'], [
            'qty' => 1,
            'hpp' => 75000,
            'harga_akhir' => 75000,
            'disc_brand_type' => 'nominal',
            'disc_brand_value' => 0,
            'disc_brand_amount' => 0,
            'margin_type' => 'nominal',
            'margin_value' => 15000,
            'margin_amount' => 15000,
            'harga_aktif' => 90000,
            'disc_toko_type' => 'nominal',
            'disc_toko_value' => 0,
            'disc_toko_amount' => 0,
            'price' => 90000,
            'subtotal' => 90000,
        ]);
        $this->seedMovement($this->products['C'], $this->outlets['one'], $this->ownerStocks['oneC'], $cashier, 'out', Penjualan::class, $saleTwo->id, 0, 1, 5, 'Penjualan INV-DEMO-000002.');
        $this->upsert(Transaction::class, ['penjualan_id' => $saleTwo->id], [
            'payment_method' => (string) $this->paymentMethods['QRIS']->id,
            'tanggal' => now(),
            'status' => 'paid',
            'pic' => $cashier->name,
        ]);

        DB::table('user_cart')->updateOrInsert([
            'user_id' => $cashier->id,
            'product_id' => $this->products['A']->id,
            'outlet_id' => (string) $this->outlets['one']->id,
        ], [
            'qty' => 1,
            'serial_number' => null,
            'stock_id' => $this->stocks['A']->id,
            'owner_stock_id' => $this->ownerStocks['oneA']->id,
            'updated_at' => now(),
            'created_at' => now(),
        ]);
        DB::table('user_cart')->updateOrInsert([
            'user_id' => $this->users['cashierTwo']->id,
            'product_id' => $this->products['D']->id,
            'outlet_id' => (string) $this->outlets['two']->id,
        ], [
            'qty' => 2,
            'serial_number' => null,
            'stock_id' => null,
            'owner_stock_id' => $this->ownerStocks['twoD']->id,
            'updated_at' => now(),
            'created_at' => now(),
        ]);
        DB::table('user_wishlist')->updateOrInsert([
            'user_id' => $customer->id,
            'product_id' => $this->products['C']->id,
            'outlet_id' => (string) $this->outlets['one']->id,
        ], [
            'customer_id' => (string) $customer->id,
            'name' => $this->products['C']->name,
            'qty' => 1,
            'stock_id' => $this->stocks['C']->id,
            'owner_stock_id' => $this->ownerStocks['oneC']->id,
            'updated_at' => now(),
            'created_at' => now(),
        ]);
        DB::table('cart_storage')->updateOrInsert(['id' => 'demo-hold-cart'], [
            'cart_data' => serialize([]),
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        foreach ([
            [$this->products['A'], 5, 'Produk A sesuai deskripsi dan pengiriman.'],
            [$this->products['C'], 4, 'Produk C memiliki kualitas baik.'],
        ] as [$product, $rating, $comment]) {
            $this->upsert(Review::class, [
                'product_id' => $product->id,
                'user_id' => $customer->id,
            ], [
                'rating' => $rating,
                'comment' => $comment,
            ]);
        }
    }

    private function seedExpenses(): void
    {
        foreach ([
            [$this->categories['expenses'], $this->kas['one'], 250000, 'Biaya listrik outlet demo', today()->subDays(5)],
            [$this->categories['utilities'], $this->kas['two'], 175000, 'Biaya internet outlet demo', today()->subDays(3)],
            [$this->categories['expenses'], $this->kas['warehouse'], 300000, 'Biaya operasional gudang demo', today()->subDay()],
        ] as [$category, $kas, $amount, $description, $date]) {
            $this->upsert(Pengeluaran::class, [
                'category_id' => $category->id,
                'kas_id' => $kas->id,
                'tanggal' => $date->toDateTimeString(),
                'desc' => $description,
            ], [
                'biaya' => (string) $amount,
                'jumlah' => '1',
            ]);
        }
    }

    private function seedStock(Pembelian $purchase, Product $product, string $sku, int $cost, int $qty, string $location): Stock
    {
        $stock = $this->upsert(Stock::class, ['sku' => $sku], [
            'pembelian_id' => $purchase->id,
            'product_id' => $product->id,
            'harga_beli' => $cost,
            'qty' => $qty,
            'qty_reserved' => 0,
            'expired_at' => today()->addYear()->toDateString(),
            'serial_number' => null,
            'imei' => null,
            'condition' => 'new',
            'location' => $location,
            'batch_number' => $sku,
            'status' => 'available',
        ]);
        $stock->forceFill(['stock_status' => 'available'])->save();
        $key = match ($product->code) {
            'DEMO-001' => 'A',
            'DEMO-002' => 'B',
            'DEMO-003' => 'C',
            'DEMO-005' => 'E',
            default => null,
        };
        $this->stocks[$product->code] = $stock->fresh();
        if ($key) {
            $this->stocks[$key] = $stock->fresh();
        }

        return $stock->fresh();
    }

    private function seedWarehouseMovement(string $productKey, int $purchaseId, int $qtyIn, int $qtyOut, int $balance, string $notes): void
    {
        $this->seedMovement($this->products[$productKey], null, null, $this->users['warehouse'], 'in', Pembelian::class, $purchaseId, $qtyIn, $qtyOut, $balance, $notes);
    }

    private function seedOwnerStock(Outlet $outlet, Product $product, string $batch, Stock $stock, int $qty, string $sourceType, int $sourceId, string $notes): OwnerStock
    {
        $ownerStock = $this->upsert(OwnerStock::class, [
            'owner_id' => $outlet->id,
            'product_id' => $product->id,
            'batch_number' => $batch,
        ], [
            'stock_id' => $stock->id,
            'qty' => $qty,
            'sku' => $batch,
            'expired_at' => today()->addYear()->toDateString(),
            'hpp' => $stock->harga_beli,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'created_by' => $this->users['warehouse']->id,
        ]);
        $this->seedMovement($product, $outlet, $ownerStock, $this->users['warehouse'], 'in', $sourceType, $sourceId, $qty, 0, $qty, $notes);

        return $ownerStock->fresh();
    }

    private function seedDirectOwnerStock(Outlet $outlet, Product $product, string $batch, int $qty, int $hpp, string $sourceType, int $sourceId, User $user, string $notes): OwnerStock
    {
        $ownerStock = $this->upsert(OwnerStock::class, [
            'owner_id' => $outlet->id,
            'product_id' => $product->id,
            'batch_number' => $batch,
        ], [
            'stock_id' => null,
            'qty' => $qty,
            'sku' => $batch,
            'expired_at' => today()->addYear()->toDateString(),
            'hpp' => $hpp,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'created_by' => $user->id,
        ]);
        $this->seedMovement($product, $outlet, $ownerStock, $user, 'in', $sourceType, $sourceId, $qty, 0, $qty, $notes);

        return $ownerStock->fresh();
    }

    private function seedOutletPurchase(string $code, Outlet $outlet, User $cashier, Supplier $supplier, string $invoiceNumber, int $subtotal, int $paidAmount, string $paymentMethod, string $notes): OutletPurchase
    {
        return $this->upsert(OutletPurchase::class, ['code' => $code], [
            'outlet_id' => $outlet->id,
            'supplier_id' => $supplier->id,
            'created_by' => $cashier->id,
            'purchase_date' => today()->subDays($outlet->id === $this->outlets['one']->id ? 3 : 1)->toDateString(),
            'invoice_number' => $invoiceNumber,
            'subtotal' => $subtotal,
            'paid_amount' => $paidAmount,
            'payment_method' => $paymentMethod,
            'status' => 'received',
            'notes' => $notes,
        ]);
    }

    private function seedOutletPurchaseItem(OutletPurchase $purchase, Product $product, OwnerStock $ownerStock, int $qty, int $cost, string $batch): void
    {
        $this->upsert(OutletPurchaseItem::class, [
            'outlet_purchase_id' => $purchase->id,
            'product_id' => $product->id,
        ], [
            'owner_stock_id' => $ownerStock->id,
            'qty' => $qty,
            'harga_beli' => $cost,
            'subtotal' => $qty * $cost,
            'batch_number' => $batch,
            'expired_at' => today()->addYear()->toDateString(),
        ]);
    }

    private function seedSaleItem(Penjualan $sale, Product $product, OwnerStock $ownerStock, array $data): void
    {
        $this->upsert(PenjualanItem::class, [
            'penjualan_id' => $sale->id,
            'product_id' => $product->id,
            'owner_stock_id' => $ownerStock->id,
        ], [
            'stock_id' => $ownerStock->stock_id,
            'serial_number' => null,
            ...$data,
        ]);
    }

    private function seedMovement(Product $product, ?Outlet $outlet, ?OwnerStock $ownerStock, ?User $user, string $type, string $referenceType, ?int $referenceId, float $qtyIn, float $qtyOut, float $balance, string $notes): StockMovement
    {
        return $this->upsert(StockMovement::class, [
            'product_id' => $product->id,
            'owner_id' => $outlet?->id,
            'owner_stock_id' => $ownerStock?->id,
            'type' => $type,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ], [
            'user_id' => $user?->id,
            'qty_in' => $qtyIn,
            'qty_out' => $qtyOut,
            'balance' => $balance,
            'notes' => $notes,
        ]);
    }

    private function seedProductImports(): void
    {
        $import = $this->upsert(ProductImport::class, ['batch_id' => 'demo-product-import-001'], [
            'original_file_name' => 'produk-demo.xlsx',
            'stored_file_path' => 'imports/demo-product-import-001.xlsx',
            'status' => ProductImport::STATUS_COMPLETED_WITH_ERRORS,
            'total_rows' => 3,
            'chunk_size' => 100,
            'total_chunks' => 1,
            'processed_chunks' => 1,
            'processed_rows' => 3,
            'successful_rows' => 2,
            'failed_rows' => 1,
            'error_message' => 'Satu baris produk demo gagal divalidasi.',
            'started_at' => now()->subDays(4),
            'finished_at' => now()->subDays(4)->addMinutes(2),
            'requested_by' => $this->users['warehouse']->id,
        ]);
        $this->upsert(ProductImportFailure::class, [
            'product_import_id' => $import->id,
            'row_number' => 3,
        ], [
            'product_code' => 'DEMO-INVALID',
            'message' => 'Kode kategori produk tidak ditemukan.',
            'row_data' => ['code' => 'DEMO-INVALID', 'name' => 'Produk Invalid Demo'],
        ]);
    }

    private function seedUser(string $email, array $data): User
    {
        return $this->upsert(User::class, ['email' => $email], [
            ...$data,
            'status' => 'active',
            'alamat' => 'Magelang',
            'no_telp' => '+628120000000',
            'password' => Hash::make('password'),
            'remember_token' => null,
        ]);
    }

    private function seedCategory(string $name, string $type, ?int $outletId): Category
    {
        $category = $this->upsert(Category::class, ['name' => $name], []);
        $category->forceFill([
            'type' => $type,
            'outlet_id' => $outletId,
        ])->save();

        return $category->fresh();
    }

    private function upsert(string $class, array $identity, array $values): Model
    {
        $query = $class::query();
        if (in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
            $query->withTrashed();
        }

        $record = $query->updateOrCreate($identity, $values);
        if (method_exists($record, 'trashed') && $record->trashed()) {
            $record->restore();
        }

        return $record->fresh();
    }
}
