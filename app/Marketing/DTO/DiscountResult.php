<?php

namespace App\Marketing\DTO;

use Illuminate\Support\Collection;
use App\Marketing\Models\MarketingEvent;

class DiscountResult
{
    /**
     * Общая сумма скидки.
     */
    public float $discount = 0.0;

    /**
     * Итоговая сумма.
     */
    public float $total = 0.0;

    /**
     * Применённые маркетинговые мероприятия.
     *
     * @var Collection<int, MarketingEvent>
     */
    public Collection $events;

    /**
     * Итоговые цены товаров.
     *
     * @var Collection<int, DiscountedCartItem>
     */
    public Collection $items;

    public function __construct()
    {
        $this->events = new Collection();

        $this->items = new Collection();
    }
}
