<?php

namespace Tests\Unit;

use App\Models\Penjualan;
use App\Models\PenjualanItem;
use App\Models\Voucher;
use App\Services\OutletLaporanService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Tests\TestCase;

class OutletLaporanServiceTest extends TestCase
{
    public function test_returns_without_an_invoice_use_the_latest_sale_with_remaining_quantity(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('penjualans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('outlet_id');
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('penjualan_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('penjualan_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('qty');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('refund_penjualans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('penjualan_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('refund_penjualan_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('refund_penjualan_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('penjualan_item_id')->nullable();
            $table->string('type');
            $table->integer('qty');
            $table->timestamps();
        });

        foreach ([1, 2, 3] as $id) {
            DB::table('penjualans')->insert(['id' => $id, 'outlet_id' => 1, 'status' => 'paid', 'created_at' => "2026-09-0{$id} 10:00:00"]);
            DB::table('penjualan_items')->insert(['id' => $id, 'penjualan_id' => $id, 'product_id' => 10, 'qty' => $id === 1 ? 2 : 3]);
        }
        DB::table('penjualans')->insert(['id' => 4, 'outlet_id' => 1, 'status' => 'paid', 'created_at' => '2026-09-10 10:00:00']);
        DB::table('penjualan_items')->insert(['id' => 4, 'penjualan_id' => 4, 'product_id' => 10, 'qty' => 5]);
        foreach ([1, 2, 3] as $id) {
            DB::table('refund_penjualans')->insert([
                'id' => $id, 'outlet_id' => 1, 'penjualan_id' => $id === 3 ? 1 : null,
                'created_at' => "2026-09-0".($id + 3).' 10:00:00',
            ]);
        }
        DB::table('refund_penjualan_items')->insert([
            ['refund_penjualan_id' => 1, 'product_id' => 10, 'penjualan_item_id' => 2, 'type' => 'return', 'qty' => 1],
            ['refund_penjualan_id' => 2, 'product_id' => 10, 'penjualan_item_id' => null, 'type' => 'return', 'qty' => 4],
            ['refund_penjualan_id' => 3, 'product_id' => 10, 'penjualan_item_id' => null, 'type' => 'return', 'qty' => 1],
        ]);

        $service = new class extends OutletLaporanService {
            public function returnsThrough(Carbon $end)
            {
                return $this->returnedQuantities($end, 1);
            }
        };
        $returned = $service->returnsThrough(Carbon::parse('2026-09-07')->endOfDay());

        $this->assertEquals(1, $returned[1]);
        $this->assertEquals(2, $returned[2]);
        $this->assertEquals(3, $returned[3]);
        $this->assertFalse($returned->has(4));
    }

    public function test_product_voucher_is_allocated_only_to_its_product(): void
    {
        $sale = new Penjualan(['voucher_total' => 60]);
        $sale->setRelation('items', PenjualanItem::hydrate([
            ['id' => 1, 'product_id' => 10, 'price' => 100, 'qty' => 2],
            ['id' => 2, 'product_id' => 20, 'price' => 100, 'qty' => 1],
        ]));
        $voucher = new Voucher(['product_id' => 10]);
        $voucher->setRelation('pivot', new Pivot(['amount' => 60]));
        $sale->setRelation('vouchers', new \Illuminate\Database\Eloquent\Collection([$voucher]));
        $service = new class extends OutletLaporanService {
            public function allocatedVouchers(Penjualan $sale): array
            {
                return $this->voucherAllocation($sale);
            }
        };

        $this->assertEquals([1 => 60, 2 => 0], $service->allocatedVouchers($sale));
    }

    public function test_minimum_stock_uses_warehouse_quantity_for_po_shortfall(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->softDeletes();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->integer('min_stock');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->softDeletes();
        });
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->softDeletes();
        });
        Schema::create('owner_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('qty');
            $table->softDeletes();
        });
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->integer('qty');
            $table->softDeletes();
        });
        Schema::create('penjualans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('outlet_id');
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('refund_penjualans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('outlet_id');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('outlet_purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('outlet_id');
            $table->date('purchase_date');
            $table->softDeletes();
        });
        DB::table('outlets')->insert(['id' => 1, 'name' => 'Outlet A']);
        DB::table('products')->insert(['id' => 10, 'name' => 'Product A', 'code' => 'A', 'min_stock' => 5]);
        DB::table('owner_stocks')->insert(['owner_id' => 1, 'product_id' => 10, 'qty' => 10]);
        DB::table('stocks')->insert(['product_id' => 10, 'qty' => 2]);

        $report = app(OutletLaporanService::class)->minimumStock(Request::create('/', 'GET', ['tanggal_selesai' => '2026-09-30']));

        $this->assertEquals(2, $report['rows'][0]['stock_qty']);
        $this->assertEquals(3, $report['rows'][0]['suggested_po_qty']);
        $this->assertSame('out_of_stock', $report['rows'][0]['status']);
    }
}
