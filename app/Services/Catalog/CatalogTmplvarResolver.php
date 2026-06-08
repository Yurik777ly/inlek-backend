<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CatalogTmplvarResolver
{
    public function productTmplvarId(string $field): int
    {
        $id = config("catalog_cache.product_tmplvars.{$field}");

        if ($id === null) {
            throw new \InvalidArgumentException("Unknown product tmplvar field [{$field}]");
        }

        return (int) $id;
    }

    /**
     * @param  array<int, string>  $names
     */
    public function resolveIdByNames(array $names): ?int
    {
        foreach ($names as $name) {
            $id = $this->idByName($name);

            if ($id !== null) {
                return $id;
            }
        }

        return null;
    }

    public function idByName(string $name): ?int
    {
        return Cache::remember(
            'catalog_cache.tmplvar.' . $name,
            3600,
            fn () => DB::table('evo_site_tmplvars')->where('name', $name)->value('id')
        );
    }
}
