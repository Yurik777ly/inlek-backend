<?php

namespace Tests\Unit\Marketing;

use App\Marketing\DTO\CartItem;
use App\Marketing\DTO\MatchedItem;
use App\Marketing\Engine\Context\MarketingContext;
use App\Marketing\Engine\Discounts\DiscountCalculator;
use App\Marketing\Models\MarketingEvent;
use App\Marketing\Models\MarketingItem;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class DiscountCalculatorTest extends TestCase
{
    public function test_fixed_price_discount(): void
    {
        $cartItem = new CartItem(
            productId: 10,
            priceOld: 20.0,
            priceNew: 18.0,
            quantity: 2,
            pharmacyId: 100,
        );

        $event = new MarketingEvent();
        $event->id = 1;
        $event->type_of_discount = MarketingItem::DISCOUNT_FIXED_PRICE;

        $item = new MarketingItem();
        $item->discount_type = MarketingItem::DISCOUNT_FIXED_PRICE;
        $item->value = 15.0;

        $matched = new Collection([
            new MatchedItem($event, $item, null, $cartItem),
        ]);

        $context = new MarketingContext();
        $context->items = new Collection([$cartItem]);
        $context->pharmacyId = 100;
        $context->cartSum = 36.0;

        $result = (new DiscountCalculator())->calculate($matched, $context);

        $this->assertCount(1, $result->items);
        $discounted = $result->items->first();
        $this->assertSame(15.0, $discounted->newPrice);
        $this->assertEqualsWithDelta(3.0, $discounted->discount, 0.0001);
        $this->assertSame(30.0, $result->total);
        $this->assertEqualsWithDelta(6.0, $result->discount, 0.0001);
        $this->assertNotNull($discounted->event);
    }

    public function test_percent_discount_from_price_old(): void
    {
        $cartItem = new CartItem(
            productId: 11,
            priceOld: 100.0,
            priceNew: 95.0,
            quantity: 1,
            pharmacyId: 100,
        );

        $event = new MarketingEvent();
        $event->id = 2;
        $event->type_of_discount = MarketingItem::DISCOUNT_PERCENT;

        $item = new MarketingItem();
        $item->discount_type = MarketingItem::DISCOUNT_PERCENT;
        $item->value = 20.0;

        $matched = new Collection([
            new MatchedItem($event, $item, null, $cartItem),
        ]);

        $context = new MarketingContext();
        $context->items = new Collection([$cartItem]);
        $context->pharmacyId = 100;
        $context->cartSum = 95.0;

        $result = (new DiscountCalculator())->calculate($matched, $context);

        $discounted = $result->items->first();
        $this->assertEqualsWithDelta(80.0, $discounted->newPrice, 0.0001);
        $this->assertEqualsWithDelta(15.0, $discounted->discount, 0.0001);
        $this->assertEqualsWithDelta(80.0, $result->total, 0.0001);
    }

    public function test_keeps_price_new_when_marketing_is_higher(): void
    {
        $cartItem = new CartItem(
            productId: 12,
            priceOld: 50.0,
            priceNew: 30.0,
            quantity: 1,
            pharmacyId: 100,
        );

        $event = new MarketingEvent();
        $event->id = 3;
        $event->type_of_discount = MarketingItem::DISCOUNT_FIXED_PRICE;

        $item = new MarketingItem();
        $item->discount_type = MarketingItem::DISCOUNT_FIXED_PRICE;
        $item->value = 40.0;

        $matched = new Collection([
            new MatchedItem($event, $item, null, $cartItem),
        ]);

        $context = new MarketingContext();
        $context->items = new Collection([$cartItem]);
        $context->pharmacyId = 100;
        $context->cartSum = 30.0;

        $result = (new DiscountCalculator())->calculate($matched, $context);

        $discounted = $result->items->first();
        $this->assertSame(30.0, $discounted->newPrice);
        $this->assertSame(0.0, $discounted->discount);
        $this->assertNull($discounted->event);
    }
}
