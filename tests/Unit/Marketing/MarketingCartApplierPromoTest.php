<?php

namespace Tests\Unit\Marketing;

use App\Marketing\Engine\Context\MarketingContextFactory;
use App\Marketing\Engine\MarketingEngine;
use App\Services\Marketing\MarketingCartApplier;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class MarketingCartApplierPromoTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_promocode_multiplier_applies_after_base_price(): void
    {
        $engine = Mockery::mock(MarketingEngine::class);
        $factory = Mockery::mock(MarketingContextFactory::class);
        $applier = new MarketingCartApplier($engine, $factory);

        $method = new ReflectionMethod(MarketingCartApplier::class, 'applyPromocodeMultiplier');
        $method->setAccessible(true);

        $this->assertSame(
            13.5,
            $method->invoke($applier, 15.0, [['discount' => 10]])
        );

        $this->assertSame(
            12.15,
            $method->invoke($applier, 15.0, [
                ['discount' => 10],
                ['discount' => 10],
            ])
        );

        $this->assertSame(
            15.0,
            $method->invoke($applier, 15.0, [])
        );
    }
}
