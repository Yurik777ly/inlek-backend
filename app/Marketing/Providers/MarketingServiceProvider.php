<?php

namespace App\Marketing\Providers;

use App\Marketing\Engine\Context\MarketingContextFactory;
use App\Marketing\Engine\Discounts\DiscountCalculator;
use App\Marketing\Engine\MarketingEngine;
use App\Marketing\Engine\Matchers\ConditionMatcher;
use App\Marketing\Engine\Matchers\EventMatcher;
use App\Marketing\Engine\Matchers\ItemMatcher;
use App\Marketing\Engine\Matchers\PharmacyMatcher;
use App\Marketing\Repositories\Contracts\SegmentRepositoryInterface;
use App\Marketing\Repositories\SegmentRepository;
use Illuminate\Support\ServiceProvider;

class MarketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            SegmentRepositoryInterface::class,
            SegmentRepository::class
        );

        $this->app->singleton(DiscountCalculator::class);
        $this->app->singleton(EventMatcher::class);
        $this->app->singleton(PharmacyMatcher::class);
        $this->app->singleton(ConditionMatcher::class);
        $this->app->singleton(ItemMatcher::class);
        $this->app->singleton(MarketingEngine::class);
        $this->app->singleton(MarketingContextFactory::class);
    }

    public function boot(): void
    {
    }
}
