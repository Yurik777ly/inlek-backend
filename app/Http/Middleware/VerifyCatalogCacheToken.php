<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCatalogCacheToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('catalog_cache.webhook.token');

        if ($expected === '') {
            return response()->json([
                'success' => false,
                'message' => 'Catalog cache webhook is not configured',
            ], 503);
        }

        $token = $request->bearerToken()
            ?? $request->header('X-Catalog-Cache-Token');

        if (!is_string($token) || !hash_equals($expected, $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        return $next($request);
    }
}
