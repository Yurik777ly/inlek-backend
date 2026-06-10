<?php

namespace App\Http\Controllers\API\Internal;

use App\Http\Controllers\Controller;
use App\Services\Catalog\CatalogCacheRefresher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogCacheController extends Controller
{
    public function refreshOffers(Request $request, CatalogCacheRefresher $refresher): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|min:1',
            'incremental' => 'nullable|boolean',
        ]);

        $productIds = array_map('intval', $validated['product_ids'] ?? []);
        $incremental = (bool) ($validated['incremental'] ?? false);

        if ($productIds !== []) {
            $count = $refresher->refreshOffersForProducts($productIds);
            $mode = 'products';
        } elseif ($incremental) {
            $count = $refresher->refreshOffersIncremental();
            $mode = 'incremental';
        } else {
            $count = $refresher->refreshOffers(false);
            $mode = 'full';
        }

        return response()->json([
            'success' => true,
            'mode' => $mode,
            'affected_rows' => $count,
            'product_ids' => $productIds,
        ]);
    }
}
