<?php

namespace App\Services\Cart;

/**
 * В доставке базовая цена — текущая цена оффера виртуальной аптеки.
 * Разница price_old и price оффера не является скидкой.
 * Скидку дают только маркетинговое мероприятие (оно уже снизило price)
 * и промокод (discount_only_promos).
 */
class DeliveryPriceAdjuster
{
    /**
     * Представление корзины обрезает quantity до остатка виртуальной аптеки.
     * Если оффера нет, quantity становится 0 и приложение снимает товар с корзины.
     * Здесь возвращаем количество, которое пользователь реально положил.
     *
     * @param array<string, mixed> $cartDetailed
     * @return array<string, mixed>
     */
    public function keepAddedProducts(array $cartDetailed): array
    {
        $products = $cartDetailed['cart']['products'] ?? null;
        if (!is_array($products)) {
            return $cartDetailed;
        }

        foreach ($products as &$product) {
            if (!is_array($product)) {
                continue;
            }

            $requested = (int) ($product['requested_quantity'] ?? 0);
            if ($requested > 0) {
                $product['quantity'] = $requested;
            }

            $prices = is_array($product['prices'] ?? null) ? $product['prices'] : [];
            $price = (float) ($prices['price'] ?? 0);
            if ($price <= 0) {
                $info = $this->productInfo($product['product_info'] ?? []);
                $fallback = (float) ($info['product_price_from'] ?? $info['price'] ?? 0);
                if ($fallback > 0) {
                    $prices['price'] = round($fallback, 2);
                    $prices['price_old'] = round($fallback, 2);
                    $prices['final_price'] = round($fallback, 2);
                    $product['prices'] = $prices;
                }
            }

            $stock = (int) ($product['stock_count'] ?? 0);
            if ($stock <= 0) {
                unset($product['stock_count']);
                $product['availability'] = 'full';
            }
        }
        unset($product);

        $cartDetailed['cart']['products'] = $products;

        return $cartDetailed;
    }

    /**
     * @param array<string, mixed> $cartDetailed
     * @param array<int, float> $basePrices цена оффера до применения ММ
     * @return array<string, mixed>
     */
    public function apply(array $cartDetailed, array $basePrices): array
    {
        $products = $cartDetailed['cart']['products'] ?? null;
        if (!is_array($products)) {
            return $cartDetailed;
        }

        $totalPriceOld = 0.0;
        $totalFinal = 0.0;
        $discountOnlyPromos = 0.0;

        foreach ($products as &$product) {
            if (!is_array($product)) {
                continue;
            }

            $productId = (int) ($product['product_id'] ?? 0);
            $quantity = (int) ($product['quantity'] ?? 0);
            $prices = is_array($product['prices'] ?? null) ? $product['prices'] : [];

            $base = round((float) ($basePrices[$productId] ?? ($prices['price'] ?? 0)), 2);
            $price = round((float) ($prices['price'] ?? $base), 2);
            $promoPerUnit = round((float) ($prices['discount_only_promos'] ?? 0), 2);
            $marketingPerUnit = round(max(0, $base - $price), 2);

            $prices['price'] = $price;
            $prices['price_old'] = $base;
            $prices['discount_without_promos'] = $marketingPerUnit;
            $prices['discount_with_promos'] = round($marketingPerUnit + $promoPerUnit, 2);
            $product['prices'] = $prices;

            $product['product_info'] = $this->alignProductInfoPrices(
                $product['product_info'] ?? [],
                $price,
                $base
            );

            if (is_array($product['product_totals'] ?? null)) {
                $product['product_totals']['total'] = round($price * $quantity, 2);
                $product['product_totals']['total_old'] = round($base * $quantity, 2);
                $product['product_totals']['total_discount_without_promos'] = round($marketingPerUnit * $quantity, 2);
                $product['product_totals']['total_discount_with_promos'] = round(($marketingPerUnit + $promoPerUnit) * $quantity, 2);
            }

            $totalPriceOld += $base * $quantity;
            $totalFinal += $price * $quantity;
            $discountOnlyPromos += $promoPerUnit * $quantity;
        }
        unset($product);

        $cartDetailed['cart']['products'] = $products;

        if (is_array($cartDetailed['cart']['totals'] ?? null)) {
            $cartDetailed['cart']['totals']['total_price_old'] = round($totalPriceOld, 2);
            $cartDetailed['cart']['totals']['total_final_price'] = round($totalFinal, 2);
            $cartDetailed['cart']['totals']['total_discount'] = round($totalPriceOld - $totalFinal + $discountOnlyPromos, 2);
            $cartDetailed['cart']['totals']['discount_only_promos'] = round($discountOnlyPromos, 2);
        }

        return $cartDetailed;
    }

    /**
     * @param mixed $info
     * @return array<string, mixed>
     */
    private function productInfo(mixed $info): array
    {
        if (is_string($info) && $info !== '') {
            $decoded = json_decode($info, true);
            $info = is_array($decoded) ? $decoded : [];
        }

        return is_array($info) ? $info : [];
    }

    /**
     * @param mixed $info
     * @return array<string, mixed>
     */
    private function alignProductInfoPrices(mixed $info, float $price, float $base): array
    {
        $info = $this->productInfo($info);

        $info['product_price_from'] = $price;
        $info['product_price_from_old'] = $base;
        $info['price'] = $price;
        $info['price_old'] = $base;
        $info['product_price_from_percent'] = null;

        return $info;
    }
}
