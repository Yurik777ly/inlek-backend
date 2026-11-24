<?php

namespace Tests\Unit\Marketing;

use App\Marketing\DTO\CartItem;
use App\Marketing\Engine\Context\MarketingContext;
use App\Marketing\Engine\Matchers\ConditionMatcher;
use App\Marketing\Models\MarketingCondition;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ConditionMatcherTest extends TestCase
{
    private ConditionMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new ConditionMatcher();
    }

    public function test_null_and_empty_condition_pass(): void
    {
        $context = $this->makeContext();
        $cartItem = new CartItem(1, 10.0, 9.0, 1);

        $this->assertTrue($this->matcher->passes(null, $context, $cartItem));

        $empty = $this->makeCondition([]);
        $this->assertTrue($empty->isEmpty());
        $this->assertTrue($this->matcher->passes($empty, $context, $cartItem));
    }

    public function test_amount_condition(): void
    {
        $context = $this->makeContext();

        $condition = $this->makeCondition([
            'condition_amount' => true,
            'amount_from' => 2,
            'amount_to' => 5,
        ]);

        $tooFew = new CartItem(1, 10.0, 9.0, 1);
        $ok = new CartItem(1, 10.0, 9.0, 3);
        $tooMany = new CartItem(1, 10.0, 9.0, 6);

        $this->assertFalse($this->matcher->passes($condition, $context, $tooFew));
        $this->assertTrue($this->matcher->passes($condition, $context, $ok));
        $this->assertFalse($this->matcher->passes($condition, $context, $tooMany));
    }

    public function test_time_condition(): void
    {
        $context = $this->makeContext(Carbon::parse('2026-10-06 12:00:00'));

        $condition = $this->makeCondition([
            'condition_time' => true,
            'time_from' => '10:00:00',
            'time_to' => '14:00:00',
        ]);

        $cartItem = new CartItem(1, 10.0, 9.0, 1);

        $this->assertTrue($this->matcher->passes($condition, $context, $cartItem));

        $outside = $this->makeContext(Carbon::parse('2026-10-06 18:00:00'));
        $this->assertFalse($this->matcher->passes($condition, $outside, $cartItem));
    }

    public function test_zero_time_range_is_ignored(): void
    {
        $context = $this->makeContext(Carbon::parse('2026-10-06 23:00:00'));

        $condition = $this->makeCondition([
            'condition_time' => true,
            'time_from' => '00:00:00',
            'time_to' => '00:00:00',
        ]);

        $cartItem = new CartItem(1, 10.0, 9.0, 1);

        $this->assertTrue($this->matcher->passes($condition, $context, $cartItem));
    }

    /**
     * Stub without Eloquent datetime casts (no DB connection in pure unit tests).
     *
     * @param array<string, mixed> $attrs
     */
    private function makeCondition(array $attrs): MarketingCondition
    {
        $defaults = [
            'condition_time' => false,
            'condition_day' => false,
            'condition_amount' => false,
            'condition_goods' => false,
            'condition_expiration_date' => false,
            'condition_summa' => false,
            'condition_summa_sale' => false,
            'condition_summa_check' => false,
            'condition_price' => false,
        ];

        $condition = new class extends MarketingCondition {
            protected $casts = [];
        };

        $condition->forceFill(array_merge($defaults, $attrs));

        return $condition;
    }

    private function makeContext(?Carbon $dateTime = null): MarketingContext
    {
        $context = new MarketingContext();
        $context->pharmacyId = 1;
        $context->cartSum = 100.0;
        $context->items = new Collection();

        if ($dateTime !== null) {
            $context->dateTime = $dateTime;
        }

        return $context;
    }
}
