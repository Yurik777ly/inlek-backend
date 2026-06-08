<?php

namespace App\Services\Catalog;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CatalogCacheRefreshState
{
    public function getSince(string $step): ?Carbon
    {
        $refreshedAt = DB::table('catalog_cache_refresh_state')
            ->where('step', $step)
            ->value('refreshed_at');

        if ($refreshedAt === null) {
            return null;
        }

        $overlap = (int) config('catalog_cache.incremental.overlap_seconds', 300);

        return Carbon::parse($refreshedAt)->subSeconds($overlap);
    }

    public function mark(string $step, ?Carbon $at = null): void
    {
        $at ??= now();

        DB::table('catalog_cache_refresh_state')->updateOrInsert(
            ['step' => $step],
            [
                'refreshed_at' => $at,
                'updated_at' => now(),
            ]
        );
    }

    public function forget(string $step): void
    {
        DB::table('catalog_cache_refresh_state')->where('step', $step)->delete();
    }
}
