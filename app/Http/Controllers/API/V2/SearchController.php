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

        $productIds = collect($data['products'] ?? [])->pluck('id')->all();

        $extraData = ProductInfoViewJsonFilter::query()
            ->whereIn('product_id', $productIds)
            ->select([
                'product_id',
                'product_charachters',
                'action_json',
                'promocodes_json',
                'categories_json',
            ])
            ->get()
            ->keyBy('product_id');

        $data['products'] = collect($data['products'])->map(function ($product) use ($extraData) {
            $productId = $product['id'];

            unset($product['price'], $product['price_full']);

            if ($extraData->has($productId)) {
                $product = array_merge($product, $extraData[$productId]->toArray());
            }

            return $product;
        })->values()->all();

        return response()->json($data);
    }
}
