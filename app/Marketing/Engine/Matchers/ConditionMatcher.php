<?php

namespace App\Marketing\Engine\Matchers;

use App\Marketing\DTO\CartItem;
use App\Marketing\DTO\MatchedItem;
use App\Marketing\Engine\Context\MarketingContext;
use App\Marketing\Models\MarketingCondition;
use Illuminate\Support\Collection;

class ConditionMatcher
{
    /**
     * Оставляет только пары товар+ММ, у которых условие выполняется.
     *
     * @param Collection<int, MatchedItem> $matchedItems
     *
     * @return Collection<int, MatchedItem>
     */
    public function match(
        Collection $matchedItems,
        MarketingContext $context
    ): Collection {

        return $matchedItems->filter(

            function (MatchedItem $matched) use ($context) {

                return $this->passes(
                    $matched->condition,
                    $context,
                    $matched->cartItem
                );

            }

        )->values();
    }

    /**
     * Проверка условия для конкретного товара корзины.
     */
    public function passes(
        ?MarketingCondition $condition,
        MarketingContext $context,
        ?CartItem $cartItem = null
    ): bool {

        if ($condition === null) {
            return true;
        }

        if ($condition->isEmpty()) {
            return true;
        }

        if (!$this->checkTime($condition, $context)) {
            return false;
        }

        if (!$this->checkWeekDay($condition, $context)) {
            return false;
        }

        if (!$this->checkAmount($condition, $cartItem)) {
            return false;
        }

        if (!$this->checkPrice($condition, $cartItem)) {
            return false;
        }

        if (!$this->checkSum($condition, $context)) {
            return false;
        }

        if (!$this->checkCheckSum($condition, $context)) {
            return false;
        }

        return true;
    }

    protected function checkTime(
        MarketingCondition $condition,
        MarketingContext $context
    ): bool {

        if (!$condition->usesTime()) {
            return true;
        }

        $time = $context->dateTime->format('H:i:s');
        $from = $this->formatTime($condition->time_from);
        $to = $this->formatTime($condition->time_to);

        if ($from === null || $to === null) {
            return true;
        }

        // В 1С «пустое» время часто приходит как 00:00:00–00:00:00 — не ограничиваем.
        if ($from === '00:00:00' && $to === '00:00:00') {
            return true;
        }

        return $time >= $from && $time <= $to;
    }

    protected function checkWeekDay(
        MarketingCondition $condition,
        MarketingContext $context
    ): bool {

        if (!$condition->usesDay()) {
            return true;
        }

        switch ($context->dateTime->dayOfWeekIso) {
            case 1:
                return (bool)$condition->monday;
            case 2:
                return (bool)$condition->tuesday;
            case 3:
                return (bool)$condition->wednesday;
            case 4:
                return (bool)$condition->thursday;
            case 5:
                return (bool)$condition->friday;
            case 6:
                return (bool)$condition->saturday;
            case 7:
                return (bool)$condition->sunday;
            default:
                return false;
        }
    }

    protected function checkAmount(
        MarketingCondition $condition,
        ?CartItem $cartItem
    ): bool {

        if (!$condition->usesAmount()) {
            return true;
        }

        if ($cartItem === null) {
            return false;
        }

        $quantity = (float)$cartItem->quantity;

        if (
            $condition->amount_from !== null
            && $quantity < (float)$condition->amount_from
        ) {
            return false;
        }

        if (
            $condition->amount_to !== null
            && $quantity > (float)$condition->amount_to
        ) {
            return false;
        }

        return true;
    }

    protected function checkPrice(
        MarketingCondition $condition,
        ?CartItem $cartItem
    ): bool {

        if (!$condition->usesPrice()) {
            return true;
        }

        if ($cartItem === null) {
            return false;
        }

        $price = (float)$cartItem->priceOld;

        if (
            $condition->price_from !== null
            && $price < (float)$condition->price_from
        ) {
            return false;
        }

        if (
            $condition->price_to !== null
            && $price > (float)$condition->price_to
        ) {
            return false;
        }

        return true;
    }

    protected function checkSum(
        MarketingCondition $condition,
        MarketingContext $context
    ): bool {

        if (!$condition->usesSum()) {
            return true;
        }

        $sum = (float)$context->cartSum;

        if (
            $condition->summa_from !== null
            && $sum < (float)$condition->summa_from
        ) {
            return false;
        }

        if (
            $condition->summa_to !== null
            && $sum > (float)$condition->summa_to
        ) {
            return false;
        }

        return true;
    }

    protected function checkCheckSum(
        MarketingCondition $condition,
        MarketingContext $context
    ): bool {

        if (!$condition->usesCheckSum()) {
            return true;
        }

        $sum = (float)$context->cartSum;

        if (
            $condition->summa_check_from !== null
            && $sum < (float)$condition->summa_check_from
        ) {
            return false;
        }

        if (
            $condition->summa_check_to !== null
            && $sum > (float)$condition->summa_check_to
        ) {
            return false;
        }

        return true;
    }

    /**
     * @param mixed $value
     */
    protected function formatTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s');
        }

        $timestamp = strtotime((string)$value);

        if ($timestamp === false) {
            return null;
        }

        return date('H:i:s', $timestamp);
    }
}
