<?php

namespace App\Services\Marketing;

use App\Marketing\DTO\CartItem;
use App\Marketing\DTO\DiscountedCartItem;
use App\Marketing\Engine\Context\MarketingContextFactory;
use App\Marketing\Engine\MarketingEngine;
use Illuminate\Support\Facades\DB;

class MarketingCartApplier
{
    public function __construct(
        private readonly MarketingEngine $marketingEngine,
        private readonly MarketingContextFactory $contextFactory,
    ) {}

    /**
     * Применить маркетинговые мероприятия к детализированной корзине.
     *
     * @param array<string, mixed> $cartDetailed
     * @return array<string, mixed>
     */
    public function apply(array $cartDetailed, int $pharmacyId): array
    {
        if ($pharmacyId <= 0 || empty($cartDetailed['cart']['products'])) {
            return $cartDetailed;
        }

        $products = $cartDetailed['cart']['products'];
        if (!is_array($products) || $products === []) {
            return $cartDetailed;
        }

        $pharmacyUid = $this->resolvePharmacyUid($pharmacyId);
        $pharmacyParentId = $this->resolvePharmacyParentId($pharmacyId);

        $cartItems = [];
        $cartSum = 0.0;

        foreach ($products as $product) {
            $priceNew = (float) ($product['prices']['price'] ?? 0);
            $priceOld = (float) ($product['prices']['price_old'] ?? $priceNew);
            $quantity = (int) ($product['quantity'] ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            $item = new CartItem(
                (int) ($product['product_id'] ?? 0),
                $priceOld,
                $priceNew,
                $quantity,
                $priceNew * $quantity,
                $pharmacyId,
                $pharmacyUid,
                $pharmacyParentId,
            );

            $cartItems[] = $item;
            $cartSum += $item->sum;
        }

        if ($cartItems === []) {
            return $cartDetailed;
        }

        $context = $this->contextFactory->make($cartItems, [
            'cartSum' => $cartSum,
            'pharmacyId' => $pharmacyId,
        ]);

        $result = $this->marketingEngine->calculate($context);

        $discountByProduct = [];
        foreach ($result->items as $discounted) {
            $discountByProduct[(int) $discounted->productId] = $discounted;
        }

        $totalPriceOld = 0.0;
        $totalFinalPrice = 0.0;
        $totalDiscount = 0.0;
        $discountOnlyPromos = 0.0;

        foreach ($products as &$product) {
            $productId = (int) ($product['product_id'] ?? 0);
            $quantity = (int) ($product['quantity'] ?? 0);
            $priceOld = (float) ($product['prices']['price_old'] ?? 0);
            $price = (float) ($product['prices']['price'] ?? 0);

            /** @var DiscountedCartItem|null $discounted */
            $discounted = $discountByProduct[$productId] ?? null;

            if (
                $discounted !== null
                && ($discounted->event !== null || $discounted->discount > 0)
            ) {
                $price = round((float) $discounted->newPrice, 2);
                $product['prices']['price'] = $price;
                $product['marketing_discount'] = round((float) $discounted->discount, 2);

                if ($discounted->event !== null) {
                    $eventId = trim((string) ($discounted->event->external_id ?? ''));
                    $product['marketing_event_id'] = $eventId;
                    $product['id_marketingevent'] = $eventId;
                    $product['marketing_event_name'] = $discounted->event->name;
                }
            }

            $appliedPromos = $this->normalizeAppliedPromocodes(
                $product['prices']['applied_promocodes'] ?? []
            );

            $finalPriceWithPromos = $this->applyPromocodeMultiplier($price, $appliedPromos);
            $finalPrice = min($price, $finalPriceWithPromos);

            $discountWithoutPromos = max(0.0, $priceOld - $price);
            $discountWithPromos = max(0.0, max($priceOld, $price) - $finalPriceWithPromos);
            $productDiscountOnlyPromos = max(0.0, $discountWithPromos - $discountWithoutPromos);

            $product['prices']['final_price'] = round($finalPrice, 2);
            $product['prices']['final_price_with_promos'] = round($finalPriceWithPromos, 2);
            $product['prices']['discount_without_promos'] = round($discountWithoutPromos, 2);
            $product['prices']['discount_with_promos'] = round($discountWithPromos, 2);
            $product['prices']['discount_only_promos'] = round($productDiscountOnlyPromos, 2);

            $product['product_totals'] = [
                'total' => round($price * $quantity, 2),
                'total_old' => round($priceOld * $quantity, 2),
                'final_total' => round($finalPrice * $quantity, 2),
                'final_total_with_promos' => round($finalPriceWithPromos * $quantity, 2),
                'total_discount_without_promos' => round($discountWithoutPromos * $quantity, 2),
                'total_discount_with_promos' => round($discountWithPromos * $quantity, 2),
                'total_discount_only_promos' => round($productDiscountOnlyPromos * $quantity, 2),
            ];

            $totalPriceOld += $priceOld * $quantity;
            $totalFinalPrice += $finalPrice * $quantity;
            $totalDiscount += $discountWithPromos * $quantity;
            $discountOnlyPromos += $productDiscountOnlyPromos * $quantity;
        }
        unset($product);

        $cartDetailed['cart']['products'] = $products;

        $deliverySum = $cartDetailed['cart']['totals']['delivery_sum'] ?? null;

        $cartDetailed['cart']['totals'] = [
            'total_price_old' => round($totalPriceOld, 2),
            'total_final_price' => round($totalFinalPrice, 2),
            'total_discount' => round($totalDiscount, 2),
            'discount_only_promos' => round($discountOnlyPromos, 2),
            'delivery_sum' => $deliverySum,
        ];

        return $cartDetailed;
    }

    private function resolvePharmacyUid(int $pharmacyId): string
    {
        $value = DB::table('evo_site_tmplvar_contentvalues as tvc')
            ->join('evo_site_tmplvars as tv', 'tv.id', '=', 'tvc.tmplvarid')
            ->where('tv.name', 'ID_punkt')
            ->where('tvc.contentid', $pharmacyId)
            ->value('tvc.value');

        return trim((string) $value);
    }

    private function resolvePharmacyParentId(int $pharmacyId): string
    {
        $value = DB::table('evo_site_tmplvar_contentvalues as tvc')
            ->join('evo_site_tmplvars as tv', 'tv.id', '=', 'tvc.tmplvarid')
            ->where('tv.name', 'pharmacy_parent_id')
            ->where('tvc.contentid', $pharmacyId)
            ->value('tvc.value');

        return trim((string) $value);
    }

    /**
     * @param mixed $appliedPromocodes
     * @return array<int, array{discount?: float|int|string}>
     */
    private function normalizeAppliedPromocodes(mixed $appliedPromocodes): array
    {
        if (is_string($appliedPromocodes)) {
            $decoded = json_decode($appliedPromocodes, true);
            $appliedPromocodes = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($appliedPromocodes)) {
            return [];
        }

        return $appliedPromocodes;
    }

    /**
     * Мультипликативное применение % промокодов: price * Π(1 - discount/100).
     *
     * @param array<int, array{discount?: float|int|string}> $appliedPromocodes
     */
    private function applyPromocodeMultiplier(float $price, array $appliedPromocodes): float
    {
        if ($appliedPromocodes === []) {
            return round($price, 2);
        }

        $factor = 1.0;
        foreach ($appliedPromocodes as $promo) {
            $discount = (float) ($promo['discount'] ?? 0);
            $factor *= (1 - ($discount / 100));
        }

        return round($price * $factor, 2);
    }
}
