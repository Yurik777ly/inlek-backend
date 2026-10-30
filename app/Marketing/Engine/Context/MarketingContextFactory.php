<?php

namespace App\Marketing\Engine\Context;

use Carbon\Carbon;
use App\Marketing\DTO\CartItem;

class MarketingContextFactory
{
    /**
     * Создать контекст маркетингового движка.
     *
     * @param iterable<CartItem> $items
     * @param array{
     *     cartSum: float,
     *     pharmacyId: int,
     *     dateTime?: Carbon|string|null
     * } $options
     */
    public function make(
        iterable $items,
        array $options
    ): MarketingContext {

        $context = new MarketingContext();

        $context->items = collect($items);

        $context->cartSum = (float)$options['cartSum'];

        $context->pharmacyId = (int)$options['pharmacyId'];

        if (array_key_exists('dateTime', $options) && $options['dateTime'] !== null && $options['dateTime'] !== '') {
            $context->dateTime = $options['dateTime'] instanceof Carbon
                ? $options['dateTime']
                : Carbon::parse((string) $options['dateTime']);
        }

        return $context;
    }
}
