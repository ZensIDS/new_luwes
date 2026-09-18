<?php

namespace Tests\Unit;

use App\Models\OutletPrice;
use App\Models\Product;
use App\Models\Voucher;
use App\Services\PriceCalculator;
use Tests\TestCase;

class PriceCalculatorTest extends TestCase
{
    public function test_percentage_price_matches_the_approved_example(): void
    {
        $rule = new OutletPrice([
            'disc_brand_type' => 'percentage',
            'disc_brand_value' => 10,
            'margin_type' => 'percentage',
            'margin_value' => 25,
            'disc_toko_type' => 'percentage',
            'disc_toko_value' => 5,
        ]);

        $price = app(PriceCalculator::class)->calculateItem(100000, $rule);

        $this->assertSame(100000.0, $price['hpp']);
        $this->assertSame(10000.0, $price['disc_brand_amount']);
        $this->assertSame(90000.0, $price['harga_akhir']);
        $this->assertSame(22500.0, $price['margin_amount']);
        $this->assertSame(112500.0, $price['harga_aktif']);
        $this->assertSame(5625.0, $price['disc_toko_amount']);
        $this->assertSame(106875.0, $price['price']);
    }

    public function test_nominal_price_matches_the_approved_example(): void
    {
        $rule = new OutletPrice([
            'disc_brand_type' => 'nominal',
            'disc_brand_value' => 10000,
            'margin_type' => 'nominal',
            'margin_value' => 25000,
            'disc_toko_type' => 'nominal',
            'disc_toko_value' => 5000,
        ]);

        $price = app(PriceCalculator::class)->calculateItem(100000, $rule);

        $this->assertSame(90000.0, $price['harga_akhir']);
        $this->assertSame(25000.0, $price['margin_amount']);
        $this->assertSame(115000.0, $price['harga_aktif']);
        $this->assertSame(5000.0, $price['disc_toko_amount']);
        $this->assertSame(110000.0, $price['price']);
    }

    public function test_percentage_price_applies_tax_before_the_regular_discount(): void
    {
        $rule = new OutletPrice([
            'pajak_type' => 'percentage',
            'pajak_value' => 11,
            'disc_brand_type' => 'percentage',
            'disc_brand_value' => 10,
            'disc_tambahan_type' => 'nominal',
            'disc_tambahan_value' => 0,
            'margin_type' => 'percentage',
            'margin_value' => 25,
            'disc_toko_type' => 'percentage',
            'disc_toko_value' => 5,
        ]);

        $price = app(PriceCalculator::class)->calculateItem(100000, $rule);

        $this->assertSame(11000.0, $price['pajak_amount']);
        $this->assertSame(111000.0, $price['hpp_setelah_pajak']);
        $this->assertSame(11100.0, $price['disc_brand_amount']);
        $this->assertSame(99900.0, $price['harga_akhir']);
        $this->assertSame(24975.0, $price['margin_amount']);
        $this->assertSame(124875.0, $price['harga_aktif']);
        $this->assertSame(6244.0, $price['disc_toko_amount']);
        $this->assertSame(118631.0, $price['price']);
    }

    public function test_nominal_price_applies_nominal_tax_before_the_regular_discount(): void
    {
        $rule = new OutletPrice([
            'pajak_type' => 'nominal',
            'pajak_value' => 11000,
            'disc_brand_type' => 'nominal',
            'disc_brand_value' => 10000,
            'disc_tambahan_type' => 'nominal',
            'disc_tambahan_value' => 0,
            'margin_type' => 'nominal',
            'margin_value' => 25000,
            'disc_toko_type' => 'nominal',
            'disc_toko_value' => 5000,
        ]);

        $price = app(PriceCalculator::class)->calculateItem(100000, $rule);

        $this->assertSame(111000.0, $price['hpp_setelah_pajak']);
        $this->assertSame(101000.0, $price['harga_akhir']);
        $this->assertSame(126000.0, $price['harga_aktif']);
        $this->assertSame(121000.0, $price['price']);
    }

    public function test_outlet_adjustment_is_applied_after_store_discount(): void
    {
        $rule = new OutletPrice([
            'disc_brand_type' => 'nominal',
            'disc_brand_value' => 10000,
            'margin_type' => 'nominal',
            'margin_value' => 25000,
            'disc_toko_type' => 'nominal',
            'disc_toko_value' => 5000,
            'outlet_adjustment_type' => 'nominal',
            'outlet_adjustment_value' => 100,
        ]);

        $price = app(PriceCalculator::class)->calculateItem(100000, $rule);

        $this->assertSame(100.0, $price['outlet_surcharge']);
        $this->assertSame(110100.0, $price['price']);
    }

    public function test_percentage_and_nominal_vouchers_can_be_applied_sequentially(): void
    {
        $calculator = app(PriceCalculator::class);
        $percentage = new Voucher(['type' => 'percentage', 'value' => 10, 'min_purchase' => 300000]);
        $nominal = new Voucher(['type' => 'nominal', 'value' => 50000, 'min_purchase' => 300000]);

        $first = $calculator->voucherAmount($percentage, 375210);
        $second = $calculator->voucherAmount($nominal, 375210 - $first);

        $this->assertSame(37521.0, $first);
        $this->assertSame(50000.0, $second);
        $this->assertSame(287689.0, 375210 - $first - $second);
    }
}
