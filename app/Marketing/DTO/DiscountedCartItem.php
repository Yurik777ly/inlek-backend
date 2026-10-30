<?php

namespace App\Marketing\DTO;

use App\Marketing\Models\MarketingEvent;

class DiscountedCartItem
{
    /** @var int */
    public $productId;

    /** @var float */
    public $oldPrice;

    /** @var float */
    public $newPrice;

    /** @var float */
    public $discount;

    /** @var int */
    public $quantity;

    /** @var int */
    public $pharmacyId;

    /** @var MarketingEvent|null */
    public $event;

    public function __construct(
        int $productId,
        float $oldPrice,
        float $newPrice,
        float $discount,
        int $quantity,
        int $pharmacyId = 0,
        ?MarketingEvent $event = null
    ) {
        $this->productId = $productId;
        $this->oldPrice = $oldPrice;
        $this->newPrice = $newPrice;
        $this->discount = $discount;
        $this->quantity = $quantity;
        $this->pharmacyId = $pharmacyId;
        $this->event = $event;
    }
}
