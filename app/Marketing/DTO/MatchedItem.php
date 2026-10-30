<?php

namespace App\Marketing\DTO;

use App\Marketing\Models\MarketingCondition;
use App\Marketing\Models\MarketingEvent;
use App\Marketing\Models\MarketingItem;

class MatchedItem
{
    /** @var MarketingEvent */
    public $event;

    /** @var MarketingItem */
    public $item;

    /** @var MarketingCondition|null */
    public $condition;

    /**
     * Элемент корзины, к которому применимо ММ.
     *
     * @var CartItem
     */
    public $cartItem;

    public function __construct(
        MarketingEvent $event,
        MarketingItem $item,
        ?MarketingCondition $condition,
        CartItem $cartItem
    ) {
        $this->event = $event;
        $this->item = $item;
        $this->condition = $condition;
        $this->cartItem = $cartItem;
    }
}
