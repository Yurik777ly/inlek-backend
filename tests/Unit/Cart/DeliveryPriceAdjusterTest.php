<?php

namespace Tests\Unit\Cart;

use App\Services\Cart\DeliveryPriceAdjuster;
use PHPUnit\Framework\TestCase;

class DeliveryPriceAdjusterTest extends TestCase
{
    public function test_offer_old_price_is_not_a_discount_and_marketing_gap_remains(): void
    {
        $cart = [
            'cart' => [
                'products' => [[
                    'product_id' => 10,
                    'quantity' => 2,
                    'prices' => [
                        'price' => 70.0,
                        'price_old' => 100.0,
                        'discount_only_promos' => 7.0,
                    ],
                    'product_info' => [
                        'product_price_from' => 50,
                        'product_price_from_old' => 90,
                        'product_price_from_percent' => 20,
                    ],
                    'product_totals' => [],
                ]],
                'totals' => [
                    'delivery_sum' => 8,
                ],
            ],
        ];

        $result = (new DeliveryPriceAdjuster())->apply($cart, [10 => 80.0]);
        $product = $result['cart']['products'][0];

        $this->assertSame(70.0, $product['prices']['price']);
        $this->assertSame(80.0, $product['prices']['price_old']);
        $this->assertSame(10.0, $product['prices']['discount_without_promos']);
        $this->assertSame(17.0, $product['prices']['discount_with_promos']);
        $this->assertSame(70.0, $product['product_info']['product_price_from']);
        $this->assertSame(80.0, $product['product_info']['product_price_from_old']);
        $this->assertNull($product['product_info']['product_price_from_percent']);
        $this->assertSame(160.0, $result['cart']['totals']['total_price_old']);
        $this->assertSame(140.0, $result['cart']['totals']['total_final_price']);
        $this->assertSame(14.0, $result['cart']['totals']['discount_only_promos']);
        $this->assertSame(8, $result['cart']['totals']['delivery_sum']);
    }

    public function test_missing_delivery_offer_stays_in_cart_with_zero_quantity(): void
    {
        $cart = [
            'cart' => [
                'products' => [[
                    'product_id' => 7,
                    'quantity' => 2,
                    'requested_quantity' => 2,
                    'stock_count' => 0,
                    'availability' => 'full',
                    'prices' => [
                        'price' => 0,
                        'price_old' => 0,
                        'final_price' => 0,
                    ],
                    'product_info' => [
                        'product_price_from' => 15.5,
                        'product_price_from_old' => 20,
                    ],
                ]],
            ],
        ];

        $result = (new DeliveryPriceAdjuster())->keepAddedProducts($cart);
        $product = $result['cart']['products'][0];

        $this->assertSame(7, $product['product_id']);
        $this->assertSame(0, $product['quantity']);
        $this->assertSame(2, $product['requested_quantity']);
        $this->assertSame(0, $product['stock_count']);
        $this->assertSame('absent', $product['availability']);
        $this->assertSame(0, $product['prices']['price']);
    }

    public function test_delivery_quantity_is_limited_by_virtual_pharmacy_stock(): void
    {
        $adjuster = new DeliveryPriceAdjuster();

        $this->assertSame(
            ['quantity' => 0, 'requested_quantity' => 1, 'stock_count' => 0, 'availability' => 'absent'],
            $adjuster->limitToVirtualStock(1, 0),
        );
        $this->assertSame(
            ['quantity' => 1, 'requested_quantity' => 3, 'stock_count' => 1, 'availability' => 'part'],
            $adjuster->limitToVirtualStock(3, 1),
        );
        $this->assertSame(
            ['quantity' => 2, 'requested_quantity' => 2, 'stock_count' => 5, 'availability' => 'full'],
            $adjuster->limitToVirtualStock(2, 5),
        );
    }

    public function test_without_marketing_price_equals_virtual_pharmacy_price(): void
    {
        $cart = [
            'cart' => [
                'products' => [[
                    'product_id' => 5,
                    'quantity' => 1,
                    'prices' => [
                        'price' => 80.0,
                        'price_old' => 100.0,
                        'discount_only_promos' => 0,
                    ],
                    'product_info' => [],
                ]],
                'totals' => [],
            ],
        ];

        $result = (new DeliveryPriceAdjuster())->apply($cart, [5 => 80.0]);
        $prices = $result['cart']['products'][0]['prices'];

        $this->assertSame(80.0, $prices['price']);
        $this->assertSame(80.0, $prices['price_old']);
        $this->assertSame(0.0, $prices['discount_without_promos']);
    }
}
