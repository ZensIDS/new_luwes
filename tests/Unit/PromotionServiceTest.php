<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionProduct;
use App\Services\PromotionService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PromotionServiceTest extends TestCase
{
    public function test_calculate_does_not_apply_an_active_promotion_without_a_selected_code(): void
    {
        $product = (new Product)->forceFill(['id' => 10]);

        $result = app(PromotionService::class)->calculate([
            [
                'product' => $product,
                'qty' => 1,
                'price' => ['price' => 100],
                'base_line_total' => 100,
                'line_total' => 100,
            ],
        ], 999);

        $this->assertSame(0, $result['promotion_total']);
        $this->assertSame(100, $result['subtotal']);
        $this->assertSame([], $result['applications']);
    }

    public function test_flash_sale_applies_only_to_the_configured_quantity(): void
    {
        $promotion = new Promotion([
            'id' => 1,
            'name' => 'Flash 9.9',
            'code' => 'FLASH-9.9',
            'type' => 'flash_sale',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'max_qty' => 10,
            'stackable' => false,
        ]);
        $productRule = new PromotionProduct(['product_id' => 10, 'required_qty' => 1]);
        $promotion->setRelation('promotionProducts', new Collection([$productRule]));
        $product = (new Product)->forceFill(['id' => 10]);

        $result = app(PromotionService::class)->apply([
            [
                'product' => $product,
                'qty' => 12,
                'price' => ['price' => 100],
                'base_line_total' => 1200,
                'line_total' => 1200,
            ],
        ], new Collection([$promotion]));

        $this->assertSame(100, $result['promotion_total']);
        $this->assertSame(1100, $result['subtotal']);
        $this->assertSame(10, $result['applications'][0]['quantity']);
        $this->assertSame(1100, $result['allocations'][0]['line_total']);
    }

    public function test_bundle_discount_is_distributed_across_selected_products(): void
    {
        $promotion = new Promotion([
            'id' => 2,
            'name' => 'Bundle A + B',
            'code' => 'BUNDLE-A-B',
            'type' => 'bundle',
            'bundle_price' => 30,
            'stackable' => false,
        ]);
        $promotion->setRelation('promotionProducts', new Collection([
            new PromotionProduct(['product_id' => 10, 'required_qty' => 1]),
            new PromotionProduct(['product_id' => 11, 'required_qty' => 1]),
        ]));
        $productA = (new Product)->forceFill(['id' => 10]);
        $productB = (new Product)->forceFill(['id' => 11]);

        $result = app(PromotionService::class)->apply([
            [
                'product' => $productA,
                'qty' => 1,
                'price' => ['price' => 100],
                'base_line_total' => 100,
                'line_total' => 100,
            ],
            [
                'product' => $productB,
                'qty' => 1,
                'price' => ['price' => 50],
                'base_line_total' => 50,
                'line_total' => 50,
            ],
        ], new Collection([$promotion]));

        $this->assertSame(30, $result['promotion_total']);
        $this->assertSame(120, $result['subtotal']);
        $this->assertSame(80, $result['allocations'][0]['line_total']);
        $this->assertSame(40, $result['allocations'][1]['line_total']);
    }

    public function test_bundle_uses_bundle_price_as_nominal_discount_amount(): void
    {
        $promotion = new Promotion([
            'id' => 3,
            'name' => 'Bundle C 2 pcs',
            'code' => 'BUNDLE-C-2PCS',
            'type' => 'bundle',
            // bundle_price is the nominal discount amount for this bundle.
            'discount_type' => 'nominal',
            'discount_value' => 9500,
            'bundle_price' => 165000,
            'stackable' => false,
        ]);
        $promotion->setRelation('promotionProducts', new Collection([
            new PromotionProduct(['product_id' => 10, 'required_qty' => 2]),
        ]));
        $product = (new Product)->forceFill(['id' => 10]);

        $result = app(PromotionService::class)->apply([
            [
                'product' => $product,
                'qty' => 5,
                'price' => ['price' => 95000],
                'base_line_total' => 475000,
                'line_total' => 475000,
            ],
        ], new Collection([$promotion]));

        // Two bundles: (2 x Rp95.000) - Rp165.000 = Rp25.000 each discount.
        $this->assertSame(330000, $result['promotion_total']);
        $this->assertSame(145000, $result['subtotal']);
        $this->assertSame(2, $result['applications'][0]['quantity']);
        $this->assertSame(165000, $result['applications'][0]['details'][0]['bundle_discount']);
    }

    public function test_bundle_discount_can_make_the_customer_pay_the_remaining_amount(): void
    {
        $promotion = new Promotion([
            'id' => 4,
            'name' => 'Bundle A + C',
            'code' => 'BUNDLE-A-C-185000',
            'type' => 'bundle',
            'bundle_price' => 185000,
            'stackable' => false,
        ]);
        $promotion->setRelation('promotionProducts', new Collection([
            new PromotionProduct(['product_id' => 10, 'required_qty' => 1]),
            new PromotionProduct(['product_id' => 11, 'required_qty' => 1]),
        ]));
        $productA = (new Product)->forceFill(['id' => 10]);
        $productC = (new Product)->forceFill(['id' => 11]);

        $result = app(PromotionService::class)->apply([
            [
                'product' => $productA,
                'qty' => 1,
                'price' => ['price' => 106875],
                'base_line_total' => 106875,
                'line_total' => 106875,
            ],
            [
                'product' => $productC,
                'qty' => 1,
                'price' => ['price' => 90000],
                'base_line_total' => 90000,
                'line_total' => 90000,
            ],
        ], new Collection([$promotion]));

        $this->assertSame(185000, $result['promotion_total']);
        $this->assertSame(11875, $result['subtotal']);
    }
}
