<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Outlet;
use App\Models\OutletPrice;
use App\Models\OutletPurchase;
use App\Models\OutletPurchaseItem;
use App\Models\OwnerStock;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CashierStockSeeder extends Seeder
{
    public function run(): void
    {
        $outletOne = Outlet::updateOrCreate(
            ['name' => 'Outlet Demo 1'],
            [
                'alamat' => 'Jl. Demo No. 1',
                'slogan' => 'Demo POS Outlet 1',
            ]
        );
        $outletTwo = Outlet::updateOrCreate(
            ['name' => 'Outlet Demo 2'],
            [
                'alamat' => 'Jl. Demo No. 2',
                'slogan' => 'Demo POS Outlet 2',
            ]
        );

        $admin = User::updateOrCreate(
            ['email' => 'demo.admin@example.test'],
            [
                'name' => 'Demo Admin',
                'username' => 'demo-admin',
                'role' => 'owner',
                'status' => 'active',
                'password' => Hash::make('password'),
                'outlet_id' => null,
            ]
        );
        $cashier = User::updateOrCreate(
            ['email' => 'demo.kasir1@example.test'],
            [
                'name' => 'Demo Kasir Outlet 1',
                'username' => 'demo-kasir-1',
                'role' => 'kasir',
                'status' => 'active',
                'password' => Hash::make('password'),
                'outlet_id' => $outletOne->id,
            ]
        );

        $category = Category::firstOrCreate(['name' => 'Demo Barang']);
        $supplier = Supplier::firstOrCreate(
            ['name' => 'Supplier Demo'],
            [
                'kode_supplier' => Supplier::generateNextKode(),
                'alamat' => 'Jl. Supplier Demo No. 1',
                'no_telp' => '081234567890',
            ]
        );

        $products = [
            'A' => Product::updateOrCreate(
                ['code' => 'DEMO-001'],
                [
                    'name' => 'Produk Demo A',
                    'category_id' => $category->id,
                    'desc' => 'Produk demo untuk pengujian kalkulasi POS.',
                    'harga_beli' => 100000,
                    'harga_jual' => 106875,
                    'is_serialized' => false,
                    'satuan' => 'pcs',
                ]
            ),
            'B' => Product::updateOrCreate(
                ['code' => 'DEMO-002'],
                [
                    'name' => 'Produk Demo B',
                    'category_id' => $category->id,
                    'desc' => 'Produk demo dengan harga beli berbeda.',
                    'harga_beli' => 50000,
                    'harga_jual' => 53820,
                    'is_serialized' => false,
                    'satuan' => 'pcs',
                ]
            ),
            'C' => Product::updateOrCreate(
                ['code' => 'DEMO-003'],
                [
                    'name' => 'Produk Demo C',
                    'category_id' => $category->id,
                    'desc' => 'Produk demo untuk skenario pembelian langsung toko.',
                    'harga_beli' => 75000,
                    'harga_jual' => 90000,
                    'is_serialized' => false,
                    'satuan' => 'pcs',
                ]
            ),
        ];

        foreach ([
            [$products['A'], $outletOne, 'nominal', 10000, 'percentage', 25, 'percentage', 5],
            [$products['B'], $outletOne, 'nominal', 4000, 'percentage', 30, 'percentage', 10],
            [$products['C'], $outletOne, 'nominal', 0, 'nominal', 15000, 'nominal', 0],
            [$products['A'], $outletTwo, 'nominal', 5000, 'percentage', 20, 'nominal', 0],
        ] as [$product, $outlet, $brandType, $brandValue, $marginType, $marginValue, $storeDiscType, $storeDiscValue]) {
            $price = OutletPrice::withTrashed()->updateOrCreate(
                ['outlet_id' => $outlet->id, 'product_id' => $product->id],
                [
                    'disc_brand_type' => $brandType,
                    'disc_brand_value' => $brandValue,
                    'margin_type' => $marginType,
                    'margin_value' => $marginValue,
                    'disc_toko_type' => $storeDiscType,
                    'disc_toko_value' => $storeDiscValue,
                    'effective_from' => now()->subDay()->toDateString(),
                    'effective_until' => now()->addYear()->toDateString(),
                    'is_active' => true,
                    'created_by' => $admin->id,
                ]
            );
            if ($price->trashed()) {
                $price->restore();
            }
        }

        foreach ([
            // Current warehouse balance after the demo DO sent 12 A and 8 B.
            [$products['A'], 'DEMO-WH-A-001', 100000, 28, 'G-01'],
            [$products['B'], 'DEMO-WH-B-001', 50000, 22, 'G-02'],
        ] as [$product, $sku, $hpp, $qty, $location]) {
            Stock::updateOrCreate(
                ['sku' => $sku],
                [
                    'product_id' => $product->id,
                    'subtotal' => $hpp * $qty,
                    'harga_beli' => $hpp,
                    'qty' => $qty,
                    'qty_reserved' => 0,
                    'expired_at' => now()->addMonths(12)->toDateString(),
                    'location' => $location,
                    'batch_number' => $sku,
                    'status' => 'available',
                ]
            );
        }

        $warehouseStockA = Stock::where('sku', 'DEMO-WH-A-001')->firstOrFail();
        $warehouseStockB = Stock::where('sku', 'DEMO-WH-B-001')->firstOrFail();

        $deliveryOrder = DeliveryOrder::withTrashed()->updateOrCreate(
            ['code' => 'DO-DEMO-001'],
            [
                'owner_id' => $outletOne->id,
                'prepared_by' => $admin->id,
                'received_by' => $cashier->id,
                'delivery_date' => now()->subDays(3)->toDateString(),
                'received_date' => now()->subDays(2)->toDateString(),
                'status' => 'delivered',
                'notes' => 'Demo outbound Gudang → Outlet Demo 1.',
            ]
        );
        if ($deliveryOrder->trashed()) {
            $deliveryOrder->restore();
        }

        foreach ([
            [$products['A'], $warehouseStockA, 12],
            [$products['B'], $warehouseStockB, 8],
        ] as [$product, $warehouseStock, $qty]) {
            DeliveryOrderItem::updateOrCreate(
                ['delivery_order_id' => $deliveryOrder->id, 'product_id' => $product->id],
                [
                    'stock_id' => $warehouseStock->id,
                    'qty' => $qty,
                    'qty_sent' => $qty,
                    'sku' => $warehouseStock->sku,
                    'expired_at' => $warehouseStock->expired_at,
                    'harga_beli' => $warehouseStock->harga_beli,
                ]
            );
        }

        $this->seedOwnerStock(
            $outletOne,
            $products['A'],
            'DEMO-DO-A-001',
            'DEMO-DO-A-001',
            $warehouseStockA->id,
            100000,
            12,
            DeliveryOrder::class,
            $deliveryOrder->id,
            $cashier,
            'Demo stock toko dari Delivery Order outbound'
        );
        $this->seedOwnerStock(
            $outletOne,
            $products['B'],
            'DEMO-DO-B-001',
            'DEMO-DO-B-001',
            $warehouseStockB->id,
            50000,
            8,
            DeliveryOrder::class,
            $deliveryOrder->id,
            $cashier,
            'Demo stock toko dari Delivery Order outbound'
        );

        $paymentMethods = [
            ['name' => 'Tunai', 'bank_number' => null, 'desc' => 'Pembayaran cash di kasir.'],
            ['name' => 'Transfer BCA', 'bank_number' => '1234567890', 'desc' => 'Rekening demo.'],
            ['name' => 'QRIS', 'bank_number' => null, 'desc' => 'Pembayaran QRIS demo.'],
        ];
        foreach ($paymentMethods as $paymentMethod) {
            PaymentMethod::updateOrCreate(['name' => $paymentMethod['name']], $paymentMethod);
        }

        $purchase = OutletPurchase::withTrashed()->updateOrCreate(
            ['code' => 'POUT-DEMO-001'],
            [
                'outlet_id' => $outletOne->id,
                'supplier_id' => $supplier->id,
                'created_by' => $cashier->id,
                'purchase_date' => now()->subDays(2)->toDateString(),
                'invoice_number' => 'NOTA-DEMO-001',
                'subtotal' => 450000,
                'paid_amount' => 450000,
                'payment_method' => 'Tunai',
                'status' => 'received',
                'notes' => 'Contoh jalur alternatif: outlet membeli langsung dari supplier.',
            ]
        );
        if ($purchase->trashed()) {
            $purchase->restore();
        }

        $directOwnerStock = $this->seedOwnerStock(
            $outletOne,
            $products['C'],
            'DEMO-BELI-C-001',
            'DEMO-BELI-C-001',
            null,
            75000,
            6,
            OutletPurchase::class,
            $purchase->id,
            $cashier,
            'Demo stock toko dari pembelian langsung supplier'
        );
        OutletPurchaseItem::updateOrCreate(
            ['outlet_purchase_id' => $purchase->id, 'product_id' => $products['C']->id],
            [
                'owner_stock_id' => $directOwnerStock->id,
                'qty' => 6,
                'harga_beli' => 75000,
                'subtotal' => 450000,
                'batch_number' => 'DEMO-BELI-C-001',
                'expired_at' => now()->addMonths(12)->toDateString(),
            ]
        );

        foreach ([
            [
                'name' => 'Voucher Demo 10%',
                'code' => 'DEMO-PCT-10',
                'type' => 'percentage',
                'value' => 10,
                'min_purchase' => 200000,
                'max_discount_amount' => 50000,
            ],
            [
                'name' => 'Voucher Demo Rp25.000',
                'code' => 'DEMO-RP-25000',
                'type' => 'nominal',
                'value' => 25000,
                'min_purchase' => 100000,
                'max_discount_amount' => null,
            ],
        ] as $voucherData) {
            $voucher = Voucher::withTrashed()->updateOrCreate(
                ['code' => $voucherData['code']],
                [
                    ...$voucherData,
                    'jenis' => 'keseluruhan',
                    'limit' => 1,
                    'outlet_id' => $outletOne->id,
                    'product_id' => null,
                    'start_at' => now()->subDay(),
                    'end_at' => now()->addDays(30),
                    'desc' => 'Voucher demo, hanya dapat digunakan satu kali.',
                ]
            );
            if ($voucher->trashed()) {
                $voucher->restore();
            }
        }
    }

    private function seedOwnerStock(
        Outlet $outlet,
        Product $product,
        string $batchNumber,
        string $sku,
        ?int $warehouseStockId,
        float $hpp,
        int $qty,
        string $sourceType,
        ?int $sourceId,
        User $user,
        string $notes
    ): OwnerStock {
        $ownerStock = OwnerStock::withTrashed()->updateOrCreate(
            ['owner_id' => $outlet->id, 'product_id' => $product->id, 'batch_number' => $batchNumber],
            [
                'stock_id' => $warehouseStockId,
                'qty' => $qty,
                'sku' => $sku,
                'expired_at' => now()->addMonths(12)->toDateString(),
                'hpp' => $hpp,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'created_by' => $user->id,
            ]
        );
        if ($ownerStock->trashed()) {
            $ownerStock->restore();
        }

        StockMovement::updateOrCreate(
            [
                'owner_stock_id' => $ownerStock->id,
                'type' => 'in',
                'reference_type' => $sourceType,
                'reference_id' => $sourceId,
            ],
            [
                'product_id' => $product->id,
                'owner_id' => $outlet->id,
                'user_id' => $user->id,
                'qty_in' => $qty,
                'qty_out' => 0,
                'balance' => $qty,
                'notes' => $notes,
            ]
        );

        return $ownerStock;
    }
}
