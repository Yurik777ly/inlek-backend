<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Models\ProductInfoViewJsonFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;


class SearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('query');

        if (!$query) {
            return response()->json([
                'error' => 'Параметр query обязателен.'
            ], 400);
        }

        $searchUrl = 'https://api.rees46.ru/search';

        $params = [
            'shop_id' => 'a46b953ef509cadb85a3692a13dfac',
            'did' => 'KwkHbFxeho',
            'sid' => '8Cix7LJ0Pw',
            'type' => 'instant_search',
            'search_query' => $query,
            'collapse' => 'true',
        ];

        $response = Http::get($searchUrl, $params);

        if ($response->failed()) {
            return response()->json([
                'error' => 'Ошибка при запросе к Rees46',
                'status' => $response->status()
            ], $response->status());
        }

        $data = $response->json();

        $productIds = array_column($data['products'], 'id');

        $extraData = ProductInfoViewJsonFilter::whereIn('product_id', $productIds)
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

                $price = !empty($data['product_price_from']) ? (float)$data['product_price_from'] : 0;
                $oldPrice = !empty($data['product_price_from_old']) ? (float)$data['product_price_from_old'] : null;
                $discountPercent = !empty($data['product_price_from_percent']) ? (float)$data['product_price_from_percent'] : null;

                $hasDiscount = $oldPrice !== null && $oldPrice > $price && $price > 0;

                $data['price'] = $price;
                $data['price_old'] = $hasDiscount ? $oldPrice : null;
                $data['discount_percent'] = $hasDiscount ? $discountPercent : null;

                if ($hasDiscount && !$discountPercent) {
                    $data['discount_percent'] = round((($oldPrice - $price) / $oldPrice) * 100);
                }

                return $data;
            });

        $data['products'] = collect($data['products'])
        ->filter(function ($product) use ($extraData) {
            return $extraData->has($product['id']);
        })
        ->map(function ($product) use ($extraData) {
            $productId = $product['id'];

            if ($extraData->has($productId)) {
                $productData = $extraData[$productId];
                $product = array_merge($product, $productData);

                // Set the price fields that will be used by the frontend
                $product['price'] = $productData['price'];
                if (isset($productData['price_old']) && $productData['price_old'] > $productData['price']) {
                    $product['price_old'] = $productData['price_old'];
                    $product['discount_percent'] = $productData['discount_percent'] ??
                        round((($productData['price_old'] - $productData['price']) / $productData['price_old']) * 100);
                }
            }

            return $product;
        })->values()->all();

        return response()->json($data);
    }
}
