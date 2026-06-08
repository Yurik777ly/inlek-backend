<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Services\Product\ProductSearchService;
use App\Services\Rees46\Rees46;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SearchController extends Controller
{
    public function __construct(
        private readonly ProductSearchService $productSearchService,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('query'));

        if ($query === '') {
            return response()->json([
                'error' => 'Параметр query обязателен.',
            ], 400);
        }

        $data = $this->searchRees46($query);

        if ($data === null) {
            return response()->json($this->productSearchService->searchLocal($query));
        }

        $products = $this->productSearchService->enrichRees46Products($data['products'] ?? []);
        $data['products'] = $products;

        if (empty($data['products'])) {
            return response()->json($this->productSearchService->searchLocal($query));
        }

        return response()->json($data);
    }

    private function searchRees46(string $query): ?array
    {
        try {
            $response = Http::timeout(5)->get('https://api.rees46.ru/search', [
                'shop_id' => Rees46::SHOP_ID,
                'did' => Rees46::DID,
                'sid' => Rees46::SID,
                'type' => 'instant_search',
                'search_query' => $query,
                'collapse' => 'true',
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Rees46 search request failed', [
                'query' => $query,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('Rees46 search returned error', [
                'query' => $query,
                'status' => $response->status(),
            ]);

            return null;
        }

        $data = $response->json();

        if (!is_array($data)) {
            return null;
        }

        $data['products'] ??= [];
        $data['categories'] ??= [];
        $data['queries'] ??= [];

        return $data;
    }
}
