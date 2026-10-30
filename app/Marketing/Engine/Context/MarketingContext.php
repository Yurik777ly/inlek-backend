<?php

namespace App\Marketing\Engine\Context;

use App\Marketing\DTO\CartItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MarketingContext
{
    /**
     * ID аптеки сайта.
     */
    public int $pharmacyId;

    /**
     * Дата и время расчета.
     */
    public Carbon $dateTime;

    /**
     * Товары корзины.
     *
     * @var Collection<int, CartItem>
     */
    public Collection $items;

    /**
     * Сумма корзины.
     */
    public float $cartSum = 0.0;

    public function __construct()
    {
        $this->dateTime = Carbon::now();

        $this->items = new Collection();
    }
}
