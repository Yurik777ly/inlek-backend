<?php

namespace App\Services\Product;

use App\Models\ProductInfoViewJsonFilter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductSearchService
{
    private const LOCAL_SEARCH_LIMIT = 20;

    public function searchLocal(string $query): array
    {
        $like = '%' . $this->escapeLike($query) . '%';

        $productIds = DB::table('product_cache')
            ->where('published', 1)
            ->where(function ($builder) use ($like) {
                $builder
                    ->where('pagetitle', 'LIKE', $like)
                    ->orWhere('menutitle', 'LIKE', $like)
                    ->orWhere('mnn', 'LIKE', $like)
                    ->orWhere('mnn_lat', 'LIKE', $like)
                    ->orWhere('brand', 'LIKE', $like);
            })
            ->where('product_price_from', '>', 0)
            ->orderByRaw('CASE WHEN pagetitle LIKE ? THEN 0 ELSE 1 END', [$like])
            ->orderBy('pagetitle')
            ->limit(self::LOCAL_SEARCH_LIMIT)
            ->pluck('product_id')
            ->all();

        if (empty($productIds)) {
            return [
                'categories' => [],
                'products' => [],
                'queries' => [],
            ];
        }

        $extraData = $this->loadExtraData($productIds);

        $products = collect($productIds)
            ->filter(fn ($productId) => $extraData->has($productId))
            ->map(fn ($productId) => $this->formatProduct($productId, $extraData[$productId]))
            ->values()
            ->all();

        return [
            'categories' => [],
            'products' => $products,
            'queries' => [],
        ];
    }

    public function enrichRees46Products(array $products): array
    {
        if (empty($products)) {
            return [];
        }

        $productIds = array_column($products, 'id');
        $extraData = $this->loadExtraData($productIds);

        return collect($products)
            ->filter(function ($product) use ($extraData) {
                return $extraData->has($product['id']) && $extraData[$product['id']]['price'] > 0;
            })
            ->map(function ($product) use ($extraData) {
                return $this->formatProduct($product['id'], $extraData[$product['id']], $product);
            })
            ->values()
            ->all();
    }

    private function loadExtraData(array $productIds): Collection
    {
        if (empty($productIds)) {
            return collect();
        }

        return ProductInfoViewJsonFilter::whereIn('product_id', $productIds)
            ->select([
                'product_id',
                'product_charachters',
                'promocodes_json',
                'product_price_from',
                'product_price_from_old',
                'product_price_from_percent',
                'is_available',
                'delivery',
            ])
            ->get()
            ->keyBy('product_id')
            ->map(function ($item) {
                $data = $item->toArray();

                $price = !empty($data['product_price_from']) ? (float) $data['product_price_from'] : 0;
                $oldPrice = !empty($data['product_price_from_old']) ? (float) $data['product_price_from_old'] : null;
                $discountPercent = !empty($data['product_price_from_percent']) ? (float) $data['product_price_from_percent'] : null;

                $hasDiscount = $oldPrice !== null && $oldPrice > $price && $price > 0;

                $data['price'] = $price;
                $data['price_old'] = $hasDiscount ? $oldPrice : null;
                $data['discount_percent'] = $hasDiscount ? $discountPercent : null;

                if ($hasDiscount && !$discountPercent) {
                    $data['discount_percent'] = round((($oldPrice - $price) / $oldPrice) * 100);
                }

                return $data;
            });
    }

    private function formatProduct(int $productId, array $productData, array $baseProduct = []): array
    {
        $characters = $productData['product_charachters'] ?? [];
        if (is_string($characters)) {
            $characters = json_decode($characters, true) ?: [];
        }

        $name = $characters['name']
            ?? $characters['product_title']
            ?? $characters['pagetitle']
            ?? $baseProduct['name']
            ?? $baseProduct['product_title']
            ?? '';

        $image = $characters['image']
            ?? $baseProduct['picture']
            ?? $baseProduct['image']
            ?? '';

        $product = array_merge($baseProduct, $productData, [
            'id' => $productId,
            'product_id' => $productId,
            'name' => $name,
            'product_title' => $name,
            'picture' => $image,
            'product_charachters' => $characters,
            'price' => $productData['price'],
            'is_available' => $productData['is_available'] ?? null,
        ]);

        if (!empty($productData['price_old']) && $productData['price_old'] > $productData['price']) {
            $product['price_old'] = $productData['price_old'];
            $product['discount_percent'] = $productData['discount_percent']
                ?? round((($productData['price_old'] - $productData['price']) / $productData['price_old']) * 100);
        }

        return $product;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
