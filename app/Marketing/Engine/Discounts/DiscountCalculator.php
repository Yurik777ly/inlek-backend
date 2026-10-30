<?php

namespace App\Marketing\Engine\Discounts;

use App\Marketing\DTO\DiscountResult;
use App\Marketing\DTO\MatchedItem;
use App\Marketing\Engine\Context\MarketingContext;
use App\Marketing\Models\MarketingItem;
use Illuminate\Support\Collection;
use App\Marketing\DTO\DiscountedCartItem;

class DiscountCalculator
{
    /**
     * Для каждой позиции корзины:
     * marketing_price от price_old по каждой подходящей ММ,
     * итоговая цена = min(price_new, marketing_price_…).
     *
     * @param Collection<int, MatchedItem> $matchedItems
     */
    public function calculate(
        Collection $matchedItems,
        MarketingContext $context
    ): DiscountResult {

        $result = new DiscountResult();

        $byLine = $matchedItems->groupBy(
            fn (MatchedItem $matched) => $this->lineKey($matched->cartItem->productId, $matched->cartItem->pharmacyId)
        );

        $total = 0.0;
        $discount = 0.0;

        foreach ($context->items as $cartItem) {

            $candidates = [(float)$cartItem->priceNew];
            $winner = null;

            /** @var Collection<int, MatchedItem> $lineMatches */
            $lineMatches = $byLine->get(
                $this->lineKey($cartItem->productId, $cartItem->pharmacyId),
                new Collection()
            );

            foreach ($lineMatches as $matched) {

                $marketingPrice = $this->marketingPrice($matched);

                if ($marketingPrice === null) {
                    continue;
                }

                $candidates[] = $marketingPrice;

                if (
                    $winner === null
                    || $marketingPrice < $winner['price']
                ) {
                    $winner = [
                        'price' => $marketingPrice,
                        'matched' => $matched,
                    ];
                }
            }

            $finalPrice = min($candidates);
            $lineSum = $finalPrice * (int)$cartItem->quantity;
            $baseSum = (float)$cartItem->priceNew * (int)$cartItem->quantity;
            $lineDiscount = max(0.0, (float)$cartItem->priceNew - $finalPrice);

            $winningEvent = null;

            // ММ применилась, если итоговая цена равна цене победившей ММ
            // (даже когда скидка 0 — цена уже была равна фикс. цене ММ).
            if (
                $winner !== null
                && abs($finalPrice - $winner['price']) < 0.0001
            ) {
                $winningEvent = $winner['matched']->event;

                if (!$result->events->contains('id', $winningEvent->id)) {
                    $result->events->push($winningEvent);
                }
            }

            $result->items->push(
                new DiscountedCartItem(
                    $cartItem->productId,
                    (float)$cartItem->priceNew,
                    $finalPrice,
                    $lineDiscount,
                    (int)$cartItem->quantity,
                    (int)$cartItem->pharmacyId,
                    $winningEvent
                )
            );

            $total += $lineSum;
            $discount += max(0.0, $baseSum - $lineSum);
        }

        $result->total = round($total, 2);
        $result->discount = round($discount, 2);

        return $result;
    }

    protected function lineKey(int $productId, int $pharmacyId): string
    {
        return $productId . ':' . $pharmacyId;
    }

    protected function marketingPrice(MatchedItem $matched): ?float
    {
        $priceOld = (float)$matched->cartItem->priceOld;
        $value = (float)$matched->item->value;

        $discountType = (int)(
            $matched->item->discount_type
            ?? $matched->event->type_of_discount
        );

        return $discountType === MarketingItem::DISCOUNT_FIXED_PRICE
            ? $value
            : ($discountType === MarketingItem::DISCOUNT_PERCENT
                ? round($priceOld * (1 - ($value / 100)), 4)
                : null);
    }
}
