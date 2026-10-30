<?php

namespace App\Marketing\Engine;

use App\Marketing\DTO\DiscountResult;
use App\Marketing\Engine\Context\MarketingContext;
use App\Marketing\Engine\Discounts\DiscountCalculator;
use App\Marketing\Engine\Matchers\ConditionMatcher;
use App\Marketing\Engine\Matchers\EventMatcher;
use App\Marketing\Engine\Matchers\ItemMatcher;
use App\Marketing\Engine\Matchers\PharmacyMatcher;

class MarketingEngine
{
    /** @var EventMatcher */
    protected $eventMatcher;

    /** @var PharmacyMatcher */
    protected $pharmacyMatcher;

    /** @var ItemMatcher */
    protected $itemMatcher;

    /** @var ConditionMatcher */
    protected $conditionMatcher;

    /** @var DiscountCalculator */
    protected $calculator;

    public function __construct(
        EventMatcher $eventMatcher,
        PharmacyMatcher $pharmacyMatcher,
        ItemMatcher $itemMatcher,
        ConditionMatcher $conditionMatcher,
        DiscountCalculator $calculator
    ) {
        $this->eventMatcher = $eventMatcher;
        $this->pharmacyMatcher = $pharmacyMatcher;
        $this->itemMatcher = $itemMatcher;
        $this->conditionMatcher = $conditionMatcher;
        $this->calculator = $calculator;
    }

    /**
     * Рассчитать маркетинговые мероприятия.
     *
     * Pipeline:
     *
     * 1. Поиск активных маркетинговых мероприятий.
     * 2. Фильтрация по аптеке.
     * 3. Поиск совпавших товаров/сегментов.
     * 4. Проверка условий для каждой пары товар+ММ.
     * 5. Выбор минимальной цены.
     */
    public function calculate(
        MarketingContext $context
    ): DiscountResult {

        $events = $this->eventMatcher->match(
            $context
        );

        if ($events->isEmpty()) {
            $this->logMiss('events', $context, [
                'active_events' => 0,
            ]);

            return new DiscountResult();
        }

        $eventsAfterPharmacy = $this->pharmacyMatcher->match(
            $events,
            $context
        );

        if ($eventsAfterPharmacy->isEmpty()) {
            $this->logMiss('pharmacy', $context, [
                'active_events' => $events->pluck('external_id')->values()->all(),
            ]);

            return new DiscountResult();
        }

        $matchedItems = $this->itemMatcher->match(
            $eventsAfterPharmacy,
            $context
        );

        if ($matchedItems->isEmpty()) {
            $this->logMiss('items', $context, [
                'events_after_pharmacy' => $eventsAfterPharmacy->pluck('external_id')->values()->all(),
                'event_items_count' => $eventsAfterPharmacy->map(static function ($event) {
                    return [
                        'external_id' => $event->external_id,
                        'items' => $event->items->count(),
                    ];
                })->values()->all(),
            ]);

            return new DiscountResult();
        }

        $matchedAfterCondition = $this->conditionMatcher->match(
            $matchedItems,
            $context
        );

        if ($matchedAfterCondition->isEmpty()) {
            $this->logMiss('conditions', $context, [
                'matched_before_condition' => $matchedItems->map(static function ($matched) {
                    return [
                        'event' => $matched->event->external_id ?? null,
                        'productId' => $matched->cartItem->productId ?? null,
                        'quantity' => $matched->cartItem->quantity ?? null,
                        'condition' => $matched->condition->external_uuid ?? null,
                        'condition_time' => $matched->condition->condition_time ?? null,
                        'condition_amount' => $matched->condition->condition_amount ?? null,
                        'amount_from' => $matched->condition->amount_from ?? null,
                        'amount_to' => $matched->condition->amount_to ?? null,
                        'time_from' => $matched->condition && $matched->condition->time_from
                            ? (string) $matched->condition->time_from
                            : null,
                        'time_to' => $matched->condition && $matched->condition->time_to
                            ? (string) $matched->condition->time_to
                            : null,
                    ];
                })->values()->all(),
            ]);

            return new DiscountResult();
        }

        return $this->calculator->calculate(
            $matchedAfterCondition,
            $context
        );
    }

    /**
     * @param array<string, mixed> $extra
     */
    protected function logMiss(
        string $stage,
        MarketingContext $context,
        array $extra = []
    ): void {
        if (!function_exists('evo')) {
            return;
        }

        evo()->logEvent(0, 2, json_encode(array_merge([
            'stage' => $stage,
            'now' => (string) $context->dateTime,
            'cartSum' => $context->cartSum,
            'cart' => $context->items->map(static function ($item) {
                return [
                    'productId' => $item->productId,
                    'quantity' => $item->quantity,
                    'priceOld' => $item->priceOld,
                    'priceNew' => $item->priceNew,
                    'pharmacyId' => $item->pharmacyId,
                    'pharmacyUid' => $item->pharmacyUid,
                    'pharmacyParentId' => $item->pharmacyParentId,
                ];
            })->values()->all(),
        ], $extra), JSON_UNESCAPED_UNICODE), '[MARKETING][MISS:' . $stage . ']');
    }
}
