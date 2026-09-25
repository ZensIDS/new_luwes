<?php

namespace Database\Seeders;

use App\Models\CashierDrawerEntry;
use App\Models\CashierSession;
use App\Models\CashierShift;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Kas;
use App\Models\Outlet;
use App\Models\OutletPurchase;
use App\Models\OutletPurchaseItem;
use App\Models\OwnerStock;
use App\Models\PaymentMethod;
use App\Models\Pembelian;
use App\Models\PembelianProduct;
use App\Models\PembelianTransaction;
use App\Models\Penjualan;
use App\Models\PenjualanItem;
use App\Models\PickingList;
use App\Models\PickingListItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionApplication;
use App\Models\RefundPenjualan;
use App\Models\RefundPenjualanItem;
use App\Models\RequestOrder;
use App\Models\RequestOrderItem;
use App\Models\RequestOrderNote;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockPembelian;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Linked 2026 outlet-report fixtures.
 *
 * The records use a dedicated RPT26 prefix so the seeder is safe to rerun and
 * never removes normal application or warehouse demo data.
 */
class OutletReportSeeder extends Seeder
{
    private const PREFIX = 'RPT26-';

    private array $outlets = [];

    private array $products = [];

    private array $suppliers = [];

    private array $paymentMethods = [];

    private array $users = [];

    private ?Kas $warehouseKas = null;

    private array $stocks = [];

    private array $ownerStocks = [];

    private array $sessions = [];

    private array $shifts = [];

    private array $sales = [];

    public function run(): void
    {
        $this->loadMasterData();

        DB::transaction(function (): void {
            $this->cleanupGeneratedData();
            $this->seedCashierUserForOutletThree();
            $this->seedStocks();
            $this->seedCashierSessionsAndShifts();
            $this->linkLegacyDemoSalesToCashierHistory();
            $promotions = $this->seedRafaksiPromotions();
            $this->seedWarehousePurchasesAndRequests();
            $this->ensureSeededPurchaseProductsAreSelectable();
            $this->seedOutletDeliveryChain();
            $this->seedOutletPurchases();
            $this->seedSales($promotions);
            $this->seedReturnsAndReplacements();
            $this->refreshCashierSessionTotals();
        });

        $this->command?->info('Outlet report fixtures seeded: 10 PO, 10 sales, 10 rafaksi, and 10 returns/replacements.');
    }

    private function loadMasterData(): void
    {
        foreach ([
            'one' => 'Outlet Demo 1',
            'two' => 'Outlet Demo 2',
            'three' => 'Outlet Demo 3',
        ] as $key => $name) {
            $this->outlets[$key] = Outlet::where('name', $name)->first();
        }

        $productCodes = collect(range(1, 12))
            ->map(fn (int $number): string => 'DEMO-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT));
        $this->products = Product::whereIn('code', $productCodes)->get()->keyBy('code')->all();
        $this->suppliers = Supplier::whereIn('kode_supplier', ['S00001', 'S00002', 'S00003', 'S00004'])
            ->get()->keyBy('kode_supplier')->all();
        $this->paymentMethods = PaymentMethod::whereIn('name', ['Tunai', 'Transfer BCA', 'QRIS', 'Debit Mandiri'])
            ->get()->keyBy('name')->all();

        foreach ([
            'owner' => 'demo.owner@example.test',
            'warehouse' => 'demo.warehouse@example.test',
            'staffOne' => 'demo.staff1@example.test',
            'staffTwo' => 'demo.staff2@example.test',
            'cashierOne' => 'demo.kasir1@example.test',
            'cashierTwo' => 'demo.kasir2@example.test',
        ] as $key => $email) {
            $this->users[$key] = User::where('email', $email)->first();
        }

        if (collect($this->outlets)->contains(fn (?Outlet $outlet): bool => ! $outlet)
            || count($this->products) !== 12
            || collect($this->suppliers)->count() !== 4
            || collect($this->paymentMethods)->count() !== 4
            || collect($this->users)->contains(fn (?User $user): bool => ! $user)) {
            throw new RuntimeException('Run DemoDataSeeder first so the outlet report fixtures have master data to reference.');
        }

        $this->warehouseKas = Kas::where('name', 'Kas Gudang Demo')->first();
    }

    private function seedCashierUserForOutletThree(): void
    {
        $this->users['cashierThree'] = $this->upsert(User::class, [
            'email' => 'demo.kasir3@example.test',
        ], [
            'name' => 'Kasir Outlet Demo 3',
            'username' => 'demo-kasir-3',
            'role' => 'kasir',
            'status' => 'active',
            'outlet_id' => $this->outlets['three']->id,
            'limit_discount' => 5,
            'alamat' => 'Magelang',
            'no_telp' => '+628120000003',
            'password' => Hash::make('password'),
            'remember_token' => null,
        ]);
    }

    private function seedStocks(): void
    {
        $quantities = [
            'DEMO-001' => ['one' => 2, 'two' => 18, 'three' => 8],
            'DEMO-002' => ['one' => 30, 'two' => 2, 'three' => 12],
            'DEMO-003' => ['one' => 18, 'two' => 3, 'three' => 2],
            'DEMO-004' => ['one' => 20, 'two' => 16, 'three' => 10],
            'DEMO-005' => ['one' => 6, 'two' => 4, 'three' => 1],
            'DEMO-006' => ['one' => 14, 'two' => 12, 'three' => 5],
            'DEMO-007' => ['one' => 20, 'two' => 18, 'three' => 4],
            'DEMO-008' => ['one' => 16, 'two' => 14, 'three' => 5],
            'DEMO-009' => ['one' => 4, 'two' => 9, 'three' => 6],
            'DEMO-010' => ['one' => 8, 'two' => 8, 'three' => 6],
            'DEMO-011' => ['one' => 12, 'two' => 3, 'three' => 8],
            'DEMO-012' => ['one' => 10, 'two' => 4, 'three' => 7],
        ];

        foreach ($this->products as $product) {
            $stockSku = self::PREFIX.'WH-'.$product->code;
            $stock = $this->upsert(Stock::class, ['sku' => $stockSku], [
                'pembelian_id' => null,
                'product_id' => $product->id,
                'harga_beli' => $product->harga_beli,
                'qty' => 1000,
                'qty_reserved' => 0,
                'expired_at' => $this->date(8, 31)->addYear()->toDateString(),
                'serial_number' => null,
                'imei' => null,
                'condition' => 'new',
                'location' => 'Rak Outlet Report',
                'batch_number' => $stockSku,
                'status' => 'available',
            ]);
            $this->stocks[$product->code] = $stock;

            foreach (array_keys($this->outlets) as $outletKey) {
                $batch = self::PREFIX.'OS-'.$outletKey.'-'.$product->code;
                $this->ownerStocks[$this->ownerStockKey($outletKey, $product->code)] = $this->upsert(
                    OwnerStock::class,
                    [
                        'owner_id' => $this->outlets[$outletKey]->id,
                        'product_id' => $product->id,
                        'batch_number' => $batch,
                    ],
                    [
                        'stock_id' => $stock->id,
                        'qty' => $quantities[$product->code][$outletKey],
                        'sku' => null,
                        'expired_at' => $this->date(8, 31)->addYear()->toDateString(),
                        'hpp' => $product->harga_beli,
                        'source_type' => self::class,
                        'source_id' => null,
                        'created_by' => $this->users['warehouse']->id,
                    ]
                );
            }
        }
    }

    private function seedRafaksiPromotions(): array
    {
        $promotions = [];
        foreach (range(1, 10) as $number) {
            $date = $this->saleDate($number);
            $product = $this->productByNumber(($number % 6) + 1);
            $promotion = $this->upsert(Promotion::class, [
                'code' => self::PREFIX.'RAF-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            ], [
                'name' => 'Rafaksi Outlet Report '.$number,
                'type' => 'flash_sale',
                'discount_type' => 'nominal',
                'discount_value' => 2500 + ($number * 500),
                'bundle_price' => null,
                'max_qty' => 10,
                'quota_qty' => 100,
                'used_qty' => 1,
                'min_purchase' => 0,
                'outlet_id' => $this->outletForSale($number)->id,
                'start_at' => $date->copy()->startOfDay(),
                'end_at' => $date->copy()->addDays(5)->endOfDay(),
                'is_active' => true,
                'stackable' => false,
                'priority' => 20,
                'desc' => 'Promo rafaksi untuk fixture Laporan Outlet.',
                'created_by' => $this->users['owner']->id,
            ]);

            DB::table('promotion_products')->where('promotion_id', $promotion->id)->delete();
            DB::table('promotion_products')->insert([
                'promotion_id' => $promotion->id,
                'product_id' => $product->id,
                'required_qty' => 1,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
            $promotions[$number] = $promotion;
        }

        return $promotions;
    }

    private function seedCashierSessionsAndShifts(): void
    {
        $definitions = [
            's1' => ['one', 'cashierOne', 6, 3, ['Adithya', 'Budi'], 300000],
            's2' => ['one', 'cashierOne', 8, 3, ['Adithya', 'Citra'], 350000],
            's3' => ['two', 'cashierTwo', 6, 22, ['Dewi', 'Eka'], 280000],
            's4' => ['two', 'cashierTwo', 8, 12, ['Dewi', 'Fani'], 320000],
            's5' => ['three', 'cashierThree', 7, 15, ['Fajar', 'Gita'], 250000],
            's6' => ['three', 'cashierThree', 8, 19, ['Fajar', 'Hana'], 275000],
        ];

        foreach ($definitions as $key => [$outletKey, $cashierKey, $month, $day, $shiftNames, $cashRemoved]) {
            $openedAt = $this->date($month, $day, 8);
            $closedAt = $openedAt->copy()->setTime(22, 0);
            $cashier = $this->users[$cashierKey];
            $session = $this->upsert(CashierSession::class, [
                'outlet_id' => $this->outlets[$outletKey]->id,
                'opening_note' => self::PREFIX.'SESSION-'.$key,
            ], [
                'cashier_id' => $cashier->id,
                'status' => 'closed',
                'opening_cash' => 100000,
                'closing_cash' => 100000,
                'expected_cash' => 0,
                'cash_sales' => 0,
                'cash_in' => 0,
                'cash_out' => 0,
                'cash_removed' => $cashRemoved,
                'carry_over_cash' => 100000,
                'discrepancy' => 0,
                'closing_note' => 'Setoran demo RPT26.',
                'opened_at' => $openedAt,
                'closed_at' => $closedAt,
            ]);
            $session->forceFill([
                'opening_cashier_id' => $cashier->id,
                'next_cashier_id' => $cashier->id,
                'closing_cashier_id' => $cashier->id,
            ])->saveQuietly();
            $this->sessions[$key] = $session->fresh();

            foreach ($shiftNames as $shiftNumber => $shiftName) {
                $startedAt = $openedAt->copy()->setTime($shiftNumber === 0 ? 8 : 15, 0);
                $endedAt = $openedAt->copy()->setTime($shiftNumber === 0 ? 15 : 22, 0);
                $shift = CashierShift::firstOrNew([
                    'cashier_session_id' => $session->id,
                    'name' => $shiftName,
                ]);
                $shift->forceFill([
                    'created_by' => $cashier->id,
                    'started_at' => $startedAt,
                    'ended_at' => $endedAt,
                ])->save();
                $this->shifts[$key.'-'.($shiftNumber + 1)] = $shift->fresh();
            }
        }

        foreach (range(1, 10) as $number) {
            $sessionKey = ['s1', 's1', 's3', 's3', 's5', 's5', 's2', 's4', 's6', 's2'][$number - 1];
            $session = $this->sessions[$sessionKey];
            $cashier = $this->users[$this->cashierKeyForSession($sessionKey)];
            $recordedAt = $session->opened_at->copy()->addHours(($number % 5) + 1);
            $this->upsert(CashierDrawerEntry::class, [
                'cashier_session_id' => $session->id,
                'note' => self::PREFIX.'BON-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            ], [
                'outlet_id' => $session->outlet_id,
                'cashier_id' => $cashier->id,
                'type' => 'bon',
                'amount' => 15000 + ($number * 5000),
                'expected_cash' => null,
                'difference' => 0,
                'recorded_at' => $recordedAt,
            ]);
        }
    }

    private function linkLegacyDemoSalesToCashierHistory(): void
    {
        $sales = Penjualan::whereIn('code', ['INV-DEMO-000001', 'INV-DEMO-000002'])
            ->orderBy('code')->get();
        if ($sales->isEmpty()) {
            return;
        }

        $firstSaleDate = $sales->min('created_at')?->copy()->startOfDay() ?? now()->startOfDay();
        $openedAt = $firstSaleDate->copy()->setTime(8, 0);
        $closedAt = $firstSaleDate->copy()->setTime(22, 0);
        $cashier = $this->users['cashierOne'];
        $session = $this->upsert(CashierSession::class, [
            'outlet_id' => $this->outlets['one']->id,
            'opening_note' => self::PREFIX.'SESSION-legacy',
        ], [
            'cashier_id' => $cashier->id,
            'status' => 'closed',
            'opening_cash' => 100000,
            'closing_cash' => 100000,
            'expected_cash' => 0,
            'cash_sales' => 0,
            'cash_in' => 0,
            'cash_out' => 0,
            'cash_removed' => 100000,
            'carry_over_cash' => 100000,
            'discrepancy' => 0,
            'closing_note' => 'History kasir untuk invoice demo lama.',
            'opened_at' => $openedAt,
            'closed_at' => $closedAt,
        ]);
        $session->forceFill([
            'opening_cashier_id' => $cashier->id,
            'next_cashier_id' => $cashier->id,
            'closing_cashier_id' => $cashier->id,
        ])->saveQuietly();

        $shiftNames = ['Adithya', 'Budi'];
        $shifts = [];
        foreach ($shiftNames as $index => $name) {
            $shift = CashierShift::firstOrNew([
                'cashier_session_id' => $session->id,
                'name' => $name,
            ]);
            $shift->forceFill([
                'created_by' => $cashier->id,
                'started_at' => $openedAt->copy()->setTime($index === 0 ? 8 : 15, 0),
                'ended_at' => $closedAt->copy()->setTime($index === 0 ? 15 : 22, 0),
            ])->save();
            $shifts[] = $shift->fresh();
        }

        foreach ($sales->values() as $index => $sale) {
            $sale->forceFill([
                'cashier_session_id' => $session->id,
                'cashier_shift_id' => $shifts[$index % count($shifts)]->id,
            ])->saveQuietly();
        }

        $this->upsert(CashierDrawerEntry::class, [
            'cashier_session_id' => $session->id,
            'note' => self::PREFIX.'LEGACY-BON',
        ], [
            'outlet_id' => $session->outlet_id,
            'cashier_id' => $cashier->id,
            'type' => 'bon',
            'amount' => 25000,
            'expected_cash' => null,
            'difference' => 0,
            'recorded_at' => $closedAt->copy()->subHour(),
        ]);

        $this->sessions['legacy'] = $session->fresh();
    }

    private function seedWarehousePurchasesAndRequests(): void
    {
        $this->cleanupWarehousePurchasesAndRequests();

        $purchaseDefinitions = [
            ['S00001', 6, 2, 'DEMO-001', 25, true, 'approved', 'completed', 'paid'],
            ['S00002', 6, 16, 'DEMO-002', 30, true, 'approved', 'completed', 'paid'],
            ['S00003', 7, 4, 'DEMO-003', 18, false, 'pending', 'draft', 'unpaid'],
            ['S00004', 7, 18, 'DEMO-004', 22, true, 'approved', 'completed', 'partial'],
            ['S00001', 8, 2, 'DEMO-005', 8, false, 'pending', 'draft', 'unpaid'],
            ['S00002', 8, 14, 'DEMO-006', 35, true, 'approved', 'completed', 'paid'],
            ['S00003', 8, 21, 'DEMO-007', 28, true, 'approved', 'completed', 'paid'],
            ['S00004', 8, 27, 'DEMO-008', 20, false, 'rejected', 'draft', 'unpaid'],
        ];

        foreach ($purchaseDefinitions as $index => [$supplierCode, $month, $day, $productCode, $qty, $published, $approvalStatus, $receiptStatus, $paymentStatus]) {
            $number = $index + 3;
            $product = $this->products[$productCode];
            $purchaseDate = $this->date($month, $day, 9);
            $subtotal = $qty * (int) $product->harga_beli;
            $paidAmount = match ($paymentStatus) {
                'paid' => $subtotal,
                'partial' => (int) floor($subtotal / 2),
                default => 0,
            };
            $purchase = $this->upsert(Pembelian::class, [
                'code' => 'PO-RPT26-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            ], [
                'code_gr' => $published ? 'GR-RPT26-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT) : null,
                'outlet_id' => null,
                'supplier_id' => $this->suppliers[$supplierCode]->id,
                'kas_id' => $this->warehouseKas?->id,
                'total' => $subtotal,
                'is_published' => $published,
                'owner_approval_status' => $approvalStatus,
                'owner_approved_by' => $approvalStatus === 'approved' ? $this->users['owner']->id : null,
                'owner_approved_at' => $approvalStatus === 'approved' ? $purchaseDate->copy()->addDay() : null,
                'owner_approval_note' => 'Fixture Pembelian Gudang RPT26.',
                'receipt_date' => $published ? $purchaseDate->copy()->addDay() : null,
                'receipt_pic' => $published ? $this->users['warehouse']->name : null,
                'receipt_status' => $receiptStatus,
                'receipt_photo' => null,
            ]);
            $purchase->forceFill(['created_at' => $purchaseDate, 'updated_at' => $purchaseDate])->saveQuietly();

            $this->upsert(PembelianProduct::class, [
                'pembelian_id' => $purchase->id,
                'product_id' => $product->id,
            ], [
                'harga_beli' => $product->harga_beli,
                'qty' => $qty,
                'qty_diterima' => $published ? $qty : 0,
                'subtotal' => $subtotal,
                'expired_at' => $this->date(8, 31)->addYear(),
                'serial_numbers' => null,
            ]);
            $this->upsert(StockPembelian::class, [
                'pembelian_id' => $purchase->id,
                'product_id' => $product->id,
            ], [
                'sku' => 'RPT26-WH-PO-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                'subtotal' => $subtotal,
                'harga_beli' => $product->harga_beli,
                'qty' => $published ? $qty : 0,
                'expired_at' => $this->date(8, 31)->addYear(),
                'serial_number' => null,
                'imei' => null,
                'condition' => 'new',
                'status' => $published ? 'sent_to_outlet' : 'available',
            ]);
            $this->upsert(PembelianTransaction::class, ['pembelian_id' => $purchase->id], [
                'payment_date' => $paymentStatus === 'unpaid' ? null : $purchaseDate->copy()->addDays(2),
                'payment_method' => $paymentStatus === 'unpaid' ? null : ($number % 2 === 0 ? 'Transfer BCA' : 'Tunai'),
                'payment_reference' => $paymentStatus === 'unpaid' ? null : 'RPT26-PO-PAY-'.$number,
                'payment_history' => $paymentStatus === 'unpaid' ? [] : [[
                    'date' => $purchaseDate->copy()->addDays(2)->toDateTimeString(),
                    'amount' => $paidAmount,
                    'method' => $number % 2 === 0 ? 'Transfer BCA' : 'Tunai',
                ]],
                'status' => $paymentStatus,
                'amount' => $paidAmount,
                'bukti_transfer' => null,
                'notes' => 'Pembayaran fixture Pembelian Gudang RPT26.',
            ]);

            if ($published) {
                $warehouseStock = $this->stocks[$productCode];
                $warehouseStock->forceFill(['pembelian_id' => $purchase->id])->saveQuietly();
                $this->upsert(StockMovement::class, [
                    'product_id' => $product->id,
                    'owner_id' => null,
                    'owner_stock_id' => null,
                    'type' => 'in',
                    'reference_type' => Pembelian::class,
                    'reference_id' => $purchase->id,
                ], [
                    'user_id' => $this->users['warehouse']->id,
                    'qty_in' => $qty,
                    'qty_out' => 0,
                    'balance' => $warehouseStock->qty,
                    'notes' => 'Penerimaan fixture gudang '.$purchase->code.'.',
                ]);
            }
        }

        $baseWarehousePurchase = Pembelian::where('code', 'PO-DEMO-001')->first();
        if ($baseWarehousePurchase) {
            foreach (['DEMO-003', 'DEMO-005', 'DEMO-008', 'DEMO-009', 'DEMO-010', 'DEMO-011', 'DEMO-012'] as $productCode) {
                $this->stocks[$productCode]->forceFill([
                    'pembelian_id' => $baseWarehousePurchase->id,
                ])->saveQuietly();
            }
        }

        $requestDefinitions = [
            ['one', 'staffOne', 6, 5, 'approved', 'DEMO-001', 'DEMO-002', 10, 5],
            ['two', 'staffTwo', 6, 18, 'partial', 'DEMO-003', 'DEMO-004', 8, 4],
            ['three', 'cashierThree', 6, 25, 'pending', 'DEMO-005', 'DEMO-006', 6, 3],
            ['one', 'staffOne', 7, 6, 'approved', 'DEMO-007', 'DEMO-008', 12, 6],
            ['two', 'staffTwo', 7, 19, 'rejected', 'DEMO-009', 'DEMO-010', 7, 4],
            ['three', 'cashierThree', 8, 3, 'partial', 'DEMO-011', 'DEMO-012', 9, 5],
            ['one', 'staffOne', 8, 15, 'pending', 'DEMO-002', 'DEMO-003', 11, 4],
            ['two', 'staffTwo', 8, 26, 'approved', 'DEMO-004', 'DEMO-005', 10, 2],
        ];

        foreach ($requestDefinitions as $index => [$outletKey, $requesterKey, $month, $day, $status, $firstCode, $secondCode, $firstQty, $secondQty]) {
            $number = $index + 3;
            $requestDate = $this->date($month, $day)->toDateString();
            $isVerified = $status !== 'pending';
            $requestOrder = $this->upsert(RequestOrder::class, [
                'code' => 'REQ-RPT26-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            ], [
                'owner_id' => $this->outlets[$outletKey]->id,
                'requested_by' => $this->users[$requesterKey]->id,
                'verified_by' => $isVerified ? $this->users['warehouse']->id : null,
                'request_date' => $requestDate,
                'verified_date' => $isVerified ? $this->date($month, $day + 1)->toDateString() : null,
                'status' => $status,
                'notes' => 'Permintaan stok fixture RPT26 ke gudang.',
                'verification_notes' => $isVerified ? 'Diverifikasi untuk demo outlet.' : null,
            ]);

            foreach ([[$firstCode, $firstQty], [$secondCode, $secondQty]] as [$productCode, $qty]) {
                $approvedQty = match ($status) {
                    'approved' => $qty,
                    'partial' => max(1, $qty - 2),
                    default => 0,
                };
                $this->upsert(RequestOrderItem::class, [
                    'request_order_id' => $requestOrder->id,
                    'product_id' => $this->products[$productCode]->id,
                ], [
                    'stock_id' => $isVerified ? $this->stocks[$productCode]->id : null,
                    'qty_requested' => $qty,
                    'qty_approved' => $approvedQty,
                    'item_status' => $status,
                    'notes' => 'Item fixture '.$productCode.'.',
                ]);
            }
            $this->upsert(RequestOrderNote::class, [
                'request_order_id' => $requestOrder->id,
                'kategori' => 'Tester RPT26',
            ], [
                'qty' => $number % 3 + 1,
                'nama_pj' => 'PJ Outlet '.ucfirst($outletKey),
            ]);
        }
    }

    private function seedOutletDeliveryChain(): void
    {
        $definitions = [
            'one' => ['REQ-RPT26-003', 'RPT26-PICK-ONE', 'RPT26-DO-ONE', 'staffOne'],
            'two' => ['REQ-RPT26-004', 'RPT26-PICK-TWO', 'RPT26-DO-TWO', 'staffTwo'],
            'three' => ['REQ-RPT26-006', 'RPT26-PICK-THREE', 'RPT26-DO-THREE', 'cashierThree'],
        ];

        foreach ($definitions as $outletKey => [$requestCode, $pickingCode, $deliveryCode, $receiverKey]) {
            $requestOrder = RequestOrder::where('code', $requestCode)->firstOrFail();
            $requestDate = Carbon::parse($requestOrder->request_date);
            $approvedQtyByProduct = [];

            foreach ($this->products as $product) {
                $requestedQty = 4 + (($product->id + $this->outlets[$outletKey]->id) % 4);
                $approvedQty = $requestOrder->status === 'partial'
                    ? max(1, $requestedQty - 2)
                    : $requestedQty;
                $item = $this->upsert(RequestOrderItem::class, [
                    'request_order_id' => $requestOrder->id,
                    'product_id' => $product->id,
                ], [
                    'stock_id' => $this->stocks[$product->code]->id,
                    'qty_requested' => $requestedQty,
                    'qty_approved' => $approvedQty,
                    'item_status' => $requestOrder->status,
                    'notes' => 'Alokasi fixture dari stok gudang untuk '.$product->code.'.',
                ]);
                $approvedQtyByProduct[$product->code] = $approvedQty;
            }

            $pickingList = $this->upsert(PickingList::class, ['code' => $pickingCode], [
                'request_order_id' => $requestOrder->id,
                'picker_id' => $this->users['warehouse']->id,
                'picker_name' => $this->users['warehouse']->name,
                'status' => 'completed',
                'started_at' => $requestDate->copy()->addDay()->setTime(8, 0),
                'completed_at' => $requestDate->copy()->addDay()->setTime(12, 0),
                'notes' => 'Picking fixture untuk '.$requestCode.'.',
            ]);

            foreach ($this->products as $product) {
                $qty = $approvedQtyByProduct[$product->code];
                $this->upsert(PickingListItem::class, [
                    'picking_list_id' => $pickingList->id,
                    'product_id' => $product->id,
                ], [
                    'stock_id' => $this->stocks[$product->code]->id,
                    'qty_to_pick' => $qty,
                    'qty_picked' => $qty,
                    'location' => $product->lokasi,
                    'sku' => $this->stocks[$product->code]->sku,
                    'is_picked' => true,
                ]);
            }

            $deliveryOrder = $this->upsert(DeliveryOrder::class, ['code' => $deliveryCode], [
                'request_order_id' => $requestOrder->id,
                'picking_list_id' => $pickingList->id,
                'owner_id' => $this->outlets[$outletKey]->id,
                'prepared_by' => $this->users['warehouse']->id,
                'received_by' => $this->users[$receiverKey]->id,
                'delivery_date' => $requestDate->copy()->addDays(2)->toDateString(),
                'received_date' => $requestDate->copy()->addDays(3)->toDateString(),
                'status' => 'delivered',
                'notes' => 'Pengiriman fixture dari gudang untuk '.$requestCode.'.',
                'photo_path' => null,
            ]);

            foreach ($this->products as $product) {
                $qty = $approvedQtyByProduct[$product->code];
                $stock = $this->stocks[$product->code];
                $this->upsert(DeliveryOrderItem::class, [
                    'delivery_order_id' => $deliveryOrder->id,
                    'product_id' => $product->id,
                ], [
                    'stock_id' => $stock->id,
                    'qty' => $qty,
                    'qty_sent' => $qty,
                    'sku' => $stock->sku,
                    'expired_at' => $stock->expired_at,
                    'harga_beli' => $product->harga_beli,
                ]);

                $ownerStock = $this->ownerStocks[$this->ownerStockKey($outletKey, $product->code)];
                $ownerStock->forceFill([
                    'source_type' => DeliveryOrder::class,
                    'source_id' => $deliveryOrder->id,
                ])->saveQuietly();
                $this->ownerStocks[$this->ownerStockKey($outletKey, $product->code)] = $ownerStock->fresh();

                $this->upsert(StockMovement::class, [
                    'product_id' => $product->id,
                    'owner_id' => $this->outlets[$outletKey]->id,
                    'owner_stock_id' => $ownerStock->id,
                    'type' => 'in',
                    'reference_type' => DeliveryOrder::class,
                    'reference_id' => $deliveryOrder->id,
                ], [
                    'user_id' => $this->users['warehouse']->id,
                    'qty_in' => $ownerStock->qty,
                    'qty_out' => 0,
                    'balance' => $ownerStock->qty,
                    'notes' => 'Penerimaan stok toko dari '.$deliveryCode.'.',
                ]);
            }
        }
    }

    private function ensureSeededPurchaseProductsAreSelectable(): void
    {
        $purchases = Pembelian::with(['supplier', 'pembelianProducts'])
            ->where(function ($query): void {
                $query->where('code', 'like', 'PO-DEMO-%')
                    ->orWhere('code', 'like', 'PO-RPT26-%');
            })->get();

        foreach ($purchases as $purchase) {
            if (! $purchase->supplier) {
                continue;
            }

            foreach ($purchase->pembelianProducts as $line) {
                DB::table('product_supplier')->updateOrInsert(
                    [
                        'product_id' => $line->product_id,
                        'supplier_id' => $purchase->supplier_id,
                    ],
                    [
                        'created_at' => $purchase->created_at ?? now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    private function cleanupWarehousePurchasesAndRequests(): void
    {
        $requestIds = DB::table('request_orders')
            ->where('code', 'like', 'REQ-RPT26-%')->pluck('id');
        $deliveryIds = DB::table('delivery_orders')
            ->where(function ($query) use ($requestIds): void {
                $query->where('code', 'like', 'RPT26-DO-%');
                if ($requestIds->isNotEmpty()) {
                    $query->orWhereIn('request_order_id', $requestIds);
                }
            })->pluck('id');
        if ($deliveryIds->isNotEmpty()) {
            DB::table('delivery_order_items')->whereIn('delivery_order_id', $deliveryIds)->delete();
            DB::table('delivery_orders')->whereIn('id', $deliveryIds)->delete();
        }

        $pickingIds = DB::table('picking_lists')
            ->where(function ($query) use ($requestIds): void {
                $query->where('code', 'like', 'RPT26-PICK-%');
                if ($requestIds->isNotEmpty()) {
                    $query->orWhereIn('request_order_id', $requestIds);
                }
            })->pluck('id');
        if ($pickingIds->isNotEmpty()) {
            DB::table('picking_list_items')->whereIn('picking_list_id', $pickingIds)->delete();
            DB::table('picking_lists')->whereIn('id', $pickingIds)->delete();
        }

        $purchaseIds = DB::table('pembelians')
            ->where('code', 'like', 'PO-RPT26-%')->pluck('id');
        if ($purchaseIds->isNotEmpty()) {
            DB::table('stock_movements')
                ->where('reference_type', Pembelian::class)
                ->whereIn('reference_id', $purchaseIds)->delete();
            DB::table('pembelian_transactions')->whereIn('pembelian_id', $purchaseIds)->delete();
            DB::table('stock_pembelians')->whereIn('pembelian_id', $purchaseIds)->delete();
            DB::table('pembelian_products')->whereIn('pembelian_id', $purchaseIds)->delete();
            DB::table('pembelians')->whereIn('id', $purchaseIds)->delete();
        }

        if ($requestIds->isNotEmpty()) {
            DB::table('request_order_notes')->whereIn('request_order_id', $requestIds)->delete();
            DB::table('request_order_items')->whereIn('request_order_id', $requestIds)->delete();
            DB::table('request_orders')->whereIn('id', $requestIds)->delete();
        }
    }

    private function seedOutletPurchases(): void
    {
        $definitions = [
            ['one', 'S00001', 6, 2, 'DEMO-001', 18],
            ['one', 'S00003', 6, 15, 'DEMO-002', 20],
            ['one', 'S00004', 7, 10, 'DEMO-003', 12],
            ['one', 'S00002', 8, 5, 'DEMO-004', 15],
            ['two', 'S00001', 6, 5, 'DEMO-001', 12],
            ['two', 'S00003', 7, 8, 'DEMO-005', 6],
            ['two', 'S00004', 8, 12, 'DEMO-006', 14],
            ['three', 'S00002', 6, 7, 'DEMO-007', 20],
            ['three', 'S00003', 7, 18, 'DEMO-008', 16],
            ['three', 'S00004', 8, 20, 'DEMO-009', 12],
        ];

        foreach ($definitions as $index => [$outletKey, $supplierCode, $month, $day, $productCode, $qty]) {
            $number = $index + 1;
            $product = $this->products[$productCode];
            $purchaseDate = $this->date($month, $day, 10);
            $subtotal = $qty * (int) $product->harga_beli;
            $purchase = $this->upsert(OutletPurchase::class, [
                'code' => self::PREFIX.'PO-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            ], [
                'outlet_id' => $this->outlets[$outletKey]->id,
                'supplier_id' => $this->suppliers[$supplierCode]->id,
                'created_by' => $this->users['warehouse']->id,
                'purchase_date' => $purchaseDate->toDateString(),
                'invoice_number' => self::PREFIX.'NOTA-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                'subtotal' => $subtotal,
                'paid_amount' => $number % 3 === 0 ? (int) ($subtotal / 2) : $subtotal,
                'payment_method' => $number % 2 === 0 ? 'Transfer BCA' : 'Tunai',
                'status' => 'received',
                'notes' => 'PO outlet report bulan '.str_pad((string) $month, 2, '0', STR_PAD_LEFT).' 2026.',
            ]);
            $purchase->forceFill(['created_at' => $purchaseDate, 'updated_at' => $purchaseDate])->saveQuietly();

            $this->upsert(OutletPurchaseItem::class, [
                'outlet_purchase_id' => $purchase->id,
                'product_id' => $product->id,
            ], [
                'owner_stock_id' => $this->ownerStocks[$this->ownerStockKey($outletKey, $productCode)]->id,
                'qty' => $qty,
                'harga_beli' => $product->harga_beli,
                'subtotal' => $subtotal,
                'batch_number' => self::PREFIX.'PO-BATCH-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                'expired_at' => $this->date(8, 31)->addYear()->toDateString(),
            ]);
        }
    }

    private function seedSales(array $promotions): void
    {
        $definitions = [
            ['s1', 1, 6, 3, 'DEMO-001', 'DEMO-002', 3, 2, 'Tunai'],
            ['s1', 2, 6, 3, 'DEMO-003', 'DEMO-004', 2, 1, 'QRIS'],
            ['s3', 1, 6, 22, 'DEMO-001', 'DEMO-003', 2, 3, 'Transfer BCA'],
            ['s3', 2, 6, 22, 'DEMO-002', 'DEMO-004', 4, 2, 'Debit Mandiri'],
            ['s5', 1, 7, 15, 'DEMO-005', 'DEMO-006', 2, 2, 'Tunai'],
            ['s5', 2, 7, 15, 'DEMO-007', 'DEMO-008', 3, 2, 'QRIS'],
            ['s2', 1, 8, 3, 'DEMO-009', 'DEMO-010', 2, 3, 'Tunai'],
            ['s4', 1, 8, 12, 'DEMO-011', 'DEMO-012', 4, 2, 'Transfer BCA'],
            ['s6', 2, 8, 19, 'DEMO-001', 'DEMO-005', 2, 1, 'Debit Mandiri'],
            ['s2', 2, 8, 3, 'DEMO-002', 'DEMO-003', 3, 2, 'QRIS'],
        ];

        foreach ($definitions as $index => [$sessionKey, $shiftNumber, $month, $day, $firstCode, $secondCode, $firstQty, $secondQty, $paymentMethod]) {
            $number = $index + 1;
            $date = $this->date($month, $day, $shiftNumber === 1 ? 10 : 17);
            $session = $this->sessions[$sessionKey];
            $shift = $this->shifts[$sessionKey.'-'.$shiftNumber];
            $firstProduct = $this->products[$firstCode];
            $secondProduct = $this->products[$secondCode];
            $promotion = $promotions[$number];
            $promotionDiscountPerUnit = 2500 + ($number * 500);
            $firstPrice = max(0, (int) $firstProduct->harga_jual - $promotionDiscountPerUnit);
            $secondPrice = (int) $secondProduct->harga_jual;
            $promotionDiscount = $promotionDiscountPerUnit * $firstQty;
            $subtotal = ($firstPrice * $firstQty) + ($secondPrice * $secondQty);
            $paidAmount = $paymentMethod === 'Tunai'
                ? (int) (ceil($subtotal / 10000) * 10000)
                : $subtotal;

            $sale = $this->upsert(Penjualan::class, [
                'code' => self::PREFIX.'INV-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            ], [
                'customer_id' => null,
                'outlet_id' => $session->outlet_id,
                'kasir_id' => (string) $session->cashier_id,
                'cashier_session_id' => $session->id,
                'cashier_shift_id' => $shift->id,
                'kas_id' => null,
                'voucher_id' => null,
                'salesman_id' => null,
                'discount' => 0,
                'total' => $subtotal,
                'subtotal' => $subtotal,
                'discount_total' => 0,
                'promotion_total' => $promotionDiscount,
                'voucher_total' => 0,
                'grand_total' => $subtotal,
                'paid_amount' => $paidAmount,
                'change_amount' => max(0, $paidAmount - $subtotal),
                'payment_method_id' => $this->paymentMethods[$paymentMethod]->id,
                'payment_method_name' => $paymentMethod,
                'payment_reference' => $paymentMethod === 'Tunai' ? null : self::PREFIX.'PAY-'.$number,
                'status' => 'paid',
            ]);
            $sale->forceFill(['created_at' => $date, 'updated_at' => $date])->saveQuietly();

            $firstItem = $this->upsert(PenjualanItem::class, [
                'penjualan_id' => $sale->id,
                'product_id' => $firstProduct->id,
                'owner_stock_id' => $this->ownerStocks[$this->ownerStockKeyForOutletId($session->outlet_id, $firstCode)]->id,
            ], $this->saleItemValues(
                $firstProduct,
                $this->ownerStocks[$this->ownerStockKeyForOutletId($session->outlet_id, $firstCode)],
                $firstQty,
                $firstPrice,
                $promotionDiscount,
                [[
                    'promotion_id' => $promotion->id,
                    'promotion_name' => $promotion->name,
                    'promotion_code' => $promotion->code,
                    'quantity' => $firstQty,
                    'amount' => $promotionDiscount,
                ]]
            ));
            $firstItem->forceFill(['created_at' => $date, 'updated_at' => $date])->saveQuietly();

            $secondItem = $this->upsert(PenjualanItem::class, [
                'penjualan_id' => $sale->id,
                'product_id' => $secondProduct->id,
                'owner_stock_id' => $this->ownerStocks[$this->ownerStockKeyForOutletId($session->outlet_id, $secondCode)]->id,
            ], $this->saleItemValues(
                $secondProduct,
                $this->ownerStocks[$this->ownerStockKeyForOutletId($session->outlet_id, $secondCode)],
                $secondQty,
                $secondPrice,
                0,
                []
            ));
            $secondItem->forceFill(['created_at' => $date, 'updated_at' => $date])->saveQuietly();

            $this->upsert(PromotionApplication::class, [
                'penjualan_id' => $sale->id,
                'promotion_id' => $promotion->id,
            ], [
                'type' => 'rafaksi',
                'name' => $promotion->name,
                'code' => $promotion->code,
                'basis_amount' => $firstProduct->harga_jual * $firstQty,
                'amount' => $promotionDiscount,
                'quantity' => $firstQty,
                'details' => [[
                    'product_id' => $firstProduct->id,
                    'quantity' => $firstQty,
                    'amount' => $promotionDiscount,
                ]],
            ]);

            $this->upsert(Transaction::class, ['penjualan_id' => $sale->id], [
                'payment_method' => (string) $this->paymentMethods[$paymentMethod]->id,
                'tanggal' => $date,
                'status' => 'paid',
                'pic' => $this->users[$this->cashierKeyForSession($sessionKey)]->name,
            ]);
            $this->sales[$number] = $sale->fresh(['items']);
        }
    }

    private function seedReturnsAndReplacements(): void
    {
        $replacementCodes = [
            1 => 'DEMO-003', 2 => 'DEMO-004', 3 => 'DEMO-002', 4 => 'DEMO-003',
            5 => 'DEMO-006', 6 => 'DEMO-008', 7 => 'DEMO-010', 8 => 'DEMO-012',
            9 => 'DEMO-005', 10 => 'DEMO-004',
        ];

        foreach ($this->sales as $number => $sale) {
            $returnItem = $sale->items->first();
            $replacementProduct = $this->products[$replacementCodes[$number]];
            $outletKey = $this->outletKeyForId($sale->outlet_id);
            $replacementStock = $this->ownerStocks[$this->ownerStockKey($outletKey, $replacementProduct->code)];
            $returnDate = $sale->created_at->copy()->addDay()->setTime(12, 0);
            $returnedTotal = (float) $returnItem->price;
            $replacementTotal = (float) $replacementProduct->harga_jual;
            $difference = max(0, $replacementTotal - $returnedTotal);
            $session = CashierSession::find($sale->cashier_session_id);

            $refund = $this->upsert(RefundPenjualan::class, [
                'code' => self::PREFIX.'RTR-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            ], [
                'penjualan_id' => $sale->id,
                'outlet_id' => $sale->outlet_id,
                'user_id' => $sale->kasir_id,
                'cashier_session_id' => $session?->id,
                'returned_total' => $returnedTotal,
                'replacement_total' => $replacementTotal,
                'difference' => $difference,
                'payment_method_name' => $difference > 0 ? 'Tunai' : null,
                'payment_reference' => $difference > 0 ? self::PREFIX.'REFPAY-'.$number : null,
                'notes' => 'Retur '.$returnItem->product->name.' diganti '.$replacementProduct->name.'.',
            ]);
            $refund->forceFill(['created_at' => $returnDate, 'updated_at' => $returnDate])->saveQuietly();

            $returnLine = $this->upsert(RefundPenjualanItem::class, [
                'refund_penjualan_id' => $refund->id,
                'type' => 'return',
                'product_id' => $returnItem->product_id,
            ], [
                'penjualan_item_id' => $returnItem->id,
                'owner_stock_id' => $returnItem->owner_stock_id,
                'qty' => 1,
                'unit_price' => $returnItem->price,
                'subtotal' => $returnItem->price,
            ]);
            $returnLine->forceFill(['created_at' => $returnDate, 'updated_at' => $returnDate])->saveQuietly();

            $replacementLine = $this->upsert(RefundPenjualanItem::class, [
                'refund_penjualan_id' => $refund->id,
                'type' => 'replacement',
                'product_id' => $replacementProduct->id,
            ], [
                'penjualan_item_id' => null,
                'owner_stock_id' => $replacementStock->id,
                'qty' => 1,
                'unit_price' => $replacementProduct->harga_jual,
                'subtotal' => $replacementProduct->harga_jual,
            ]);
            $replacementLine->forceFill(['created_at' => $returnDate, 'updated_at' => $returnDate])->saveQuietly();

            if ($difference > 0) {
                $this->upsert(CashierDrawerEntry::class, [
                    'cashier_session_id' => $session?->id,
                    'note' => self::PREFIX.'REFUND-CASH-'.$number,
                ], [
                    'outlet_id' => $sale->outlet_id,
                    'cashier_id' => $sale->kasir_id,
                    'type' => 'cash_in',
                    'amount' => $difference,
                    'expected_cash' => null,
                    'difference' => 0,
                    'recorded_at' => $returnDate,
                ]);
            }
        }
    }

    private function refreshCashierSessionTotals(): void
    {
        foreach ($this->sessions as $session) {
            $cashSales = (float) Penjualan::where('cashier_session_id', $session->id)
                ->where('status', 'paid')
                ->where(function ($query): void {
                    $query->whereNull('payment_method_name')
                        ->orWhere('payment_method_name', 'like', '%tunai%')
                        ->orWhere('payment_method_name', 'like', '%cash%');
                })->sum(DB::raw('COALESCE(grand_total, total, 0)'));
            $cashIn = (float) CashierDrawerEntry::where('cashier_session_id', $session->id)
                ->where('type', 'cash_in')->sum('amount');
            $cashOut = (float) CashierDrawerEntry::where('cashier_session_id', $session->id)
                ->whereIn('type', ['bon', 'cash_out'])->sum('amount');
            $expectedCash = $cashSales + $cashIn - $cashOut;
            $closingCash = (float) $session->opening_cash + $expectedCash - (float) $session->cash_removed;

            $session->forceFill([
                'cash_sales' => $cashSales,
                'cash_in' => $cashIn,
                'cash_out' => $cashOut,
                'expected_cash' => $expectedCash,
                'closing_cash' => $closingCash,
                'discrepancy' => 0,
            ])->saveQuietly();
        }
    }

    private function cleanupGeneratedData(): void
    {
        $refundIds = DB::table('refund_penjualans')
            ->where('code', 'like', self::PREFIX.'RTR-%')->pluck('id');
        if ($refundIds->isNotEmpty()) {
            DB::table('refund_penjualan_items')->whereIn('refund_penjualan_id', $refundIds)->delete();
            DB::table('refund_penjualans')->whereIn('id', $refundIds)->delete();
        }

        $saleIds = DB::table('penjualans')
            ->where('code', 'like', self::PREFIX.'INV-%')->pluck('id');
        $promotionIds = DB::table('promotions')
            ->where('code', 'like', self::PREFIX.'RAF-%')->pluck('id');

        if ($saleIds->isNotEmpty()) {
            DB::table('promotion_applications')->whereIn('penjualan_id', $saleIds)->delete();
            DB::table('transactions')->whereIn('penjualan_id', $saleIds)->delete();
            DB::table('penjualan_items')->whereIn('penjualan_id', $saleIds)->delete();
            DB::table('penjualans')->whereIn('id', $saleIds)->delete();
        }
        if ($promotionIds->isNotEmpty()) {
            DB::table('promotion_applications')->whereIn('promotion_id', $promotionIds)->delete();
            DB::table('promotion_products')->whereIn('promotion_id', $promotionIds)->delete();
            DB::table('promotions')->whereIn('id', $promotionIds)->delete();
        }

        $sessionIds = DB::table('cashier_sessions')
            ->where('opening_note', 'like', self::PREFIX.'SESSION-%')->pluck('id');
        if ($sessionIds->isNotEmpty()) {
            DB::table('cashier_drawer_entries')->whereIn('cashier_session_id', $sessionIds)->delete();
            DB::table('cashier_shifts')->whereIn('cashier_session_id', $sessionIds)->delete();
            DB::table('cashier_sessions')->whereIn('id', $sessionIds)->delete();
        }

        $purchaseIds = DB::table('outlet_purchases')
            ->where('code', 'like', self::PREFIX.'PO-%')->pluck('id');
        if ($purchaseIds->isNotEmpty()) {
            DB::table('outlet_purchase_items')->whereIn('outlet_purchase_id', $purchaseIds)->delete();
            DB::table('outlet_purchases')->whereIn('id', $purchaseIds)->delete();
        }

        $ownerStockIds = DB::table('owner_stocks')
            ->where('batch_number', 'like', self::PREFIX.'OS-%')->pluck('id');
        if ($ownerStockIds->isNotEmpty()) {
            DB::table('stock_movements')->whereIn('owner_stock_id', $ownerStockIds)->delete();
            DB::table('owner_stocks')->whereIn('id', $ownerStockIds)->delete();
        }
    }

    private function saleItemValues(Product $product, OwnerStock $ownerStock, int $qty, int $price, int $promotionDiscount, array $promotionDetails): array
    {
        $basePrice = (int) $product->harga_jual;

        return [
            'stock_id' => $ownerStock->stock_id,
            'serial_number' => null,
            'qty' => $qty,
            'price' => $price,
            'subtotal' => $price * $qty,
            'base_price' => $basePrice,
            'base_subtotal' => $basePrice * $qty,
            'promotion_discount' => $promotionDiscount,
            'promotion_details' => $promotionDetails ?: null,
            'hpp' => $product->harga_beli,
            'pajak_type' => 'nominal',
            'pajak_value' => 0,
            'pajak_amount' => 0,
            'hpp_setelah_pajak' => $product->harga_beli,
            'harga_akhir' => $basePrice,
            'disc_brand_type' => 'nominal',
            'disc_brand_value' => 0,
            'disc_brand_amount' => 0,
            'disc_tambahan_type' => 'nominal',
            'disc_tambahan_value' => 0,
            'disc_tambahan_amount' => 0,
            'harga_dasar' => $basePrice,
            'margin_type' => 'nominal',
            'margin_value' => max(0, $basePrice - (int) $product->harga_beli),
            'margin_amount' => max(0, $basePrice - (int) $product->harga_beli),
            'harga_aktif' => $basePrice,
            'disc_toko_type' => 'nominal',
            'disc_toko_value' => 0,
            'disc_toko_amount' => 0,
            'outlet_surcharge' => 0,
            'outlet_adjustment_type' => 'nominal',
            'outlet_adjustment_value' => 0,
        ];
    }

    private function ownerStockKey(string $outletKey, string $productCode): string
    {
        return $outletKey.'|'.$productCode;
    }

    private function ownerStockKeyForOutletId(int $outletId, string $productCode): string
    {
        return $this->ownerStockKey($this->outletKeyForId($outletId), $productCode);
    }

    private function outletKeyForId(int $outletId): string
    {
        foreach ($this->outlets as $key => $outlet) {
            if ($outlet->id === $outletId) {
                return $key;
            }
        }

        throw new RuntimeException('Outlet fixture reference not found.');
    }

    private function outletForSale(int $number): Outlet
    {
        return $this->sessions[$this->sessionKeyForSale($number)]->outlet;
    }

    private function sessionKeyForSale(int $number): string
    {
        return ['s1', 's1', 's3', 's3', 's5', 's5', 's2', 's4', 's6', 's2'][$number - 1];
    }

    private function cashierKeyForSession(string $sessionKey): string
    {
        return [
            's1' => 'cashierOne', 's2' => 'cashierOne',
            's3' => 'cashierTwo', 's4' => 'cashierTwo',
            's5' => 'cashierThree', 's6' => 'cashierThree',
        ][$sessionKey];
    }

    private function productByNumber(int $number): Product
    {
        return $this->products['DEMO-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT)];
    }

    private function saleDate(int $number): Carbon
    {
        $definitions = [
            [6, 3], [6, 3], [6, 22], [6, 22], [7, 15],
            [7, 15], [8, 3], [8, 12], [8, 19], [8, 3],
        ];
        [$month, $day] = $definitions[$number - 1];

        return $this->date($month, $day, 10);
    }

    private function date(int $month, int $day, int $hour = 10): Carbon
    {
        return Carbon::create(2026, $month, $day, $hour, 0, 0, config('app.timezone', 'Asia/Jakarta'));
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
