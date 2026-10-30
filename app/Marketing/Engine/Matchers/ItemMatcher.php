<?php

namespace App\Marketing\Engine\Matchers;

use App\Marketing\Repositories\Contracts\SegmentRepositoryInterface;
use App\Marketing\DTO\MatchedItem;
use App\Marketing\Engine\Context\MarketingContext;
use App\Marketing\Models\MarketingEvent;
use App\Marketing\Models\MarketingItem;
use Illuminate\Support\Collection;
use App\Marketing\DTO\CartItem;

class ItemMatcher
{
    /** @var SegmentRepositoryInterface */
    protected $segments;

    /** @var PharmacyMatcher */
    protected $pharmacyMatcher;

    public function __construct(
        SegmentRepositoryInterface $segments,
        PharmacyMatcher $pharmacyMatcher
    ) {
        $this->segments = $segments;
        $this->pharmacyMatcher = $pharmacyMatcher;
    }

    /**
     * @param Collection<int, MarketingEvent> $events
     *
     * @return Collection<int, MatchedItem>
     */
    public function match(
        Collection $events,
        MarketingContext $context
    ): Collection {

        $matched = collect();

        foreach ($events as $event) {

            foreach ($event->items as $item) {

                foreach ($context->items as $cartItem) {

                    if (!$this->pharmacyMatcher->eventMatchesCartItem(
                        $event,
                        $cartItem,
                        $context
                    )) {
                        continue;
                    }

                    if (!$this->matchesCartItem($item, $cartItem)) {
                        continue;
                    }

                    $matched->push(
                        new MatchedItem(
                            $event,
                            $item,
                            $item->condition,
                            $cartItem
                        )
                    );
                }
            }
        }

        return $matched;
    }

    protected function matchesCartItem(
        MarketingItem $item,
        CartItem $cartItem
    ): bool {

        if ($item->isSegment()) {
            return $this->matchSegment($item, $cartItem);
        }

        return $this->matchProduct($item, $cartItem);
    }

    protected function matchProduct(
        MarketingItem $item,
        CartItem $cartItem
    ): bool {

        return (int)$cartItem->productId === (int)$item->target_id;
    }

    protected function matchSegment(
        MarketingItem $item,
        CartItem $cartItem
    ): bool {

        return $this->segments->containsProductById(
            (int)$item->target_id,
            (int)$cartItem->productId
        );
    }
}
