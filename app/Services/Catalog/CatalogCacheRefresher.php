<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CatalogCacheRefresher
{
    /** @var array<int, int>|null */
    protected ?array $lastTouchedProductIds = null;

    public function __construct(
        protected readonly CatalogTmplvarResolver $tmplvars,
        protected readonly CatalogCacheRefreshState $refreshState,
    ) {}

    /**
     * @param  array<int, string>|null  $only
     * @return array<string, int>
     */
    public function refresh(?array $only = null, bool $truncate = true, bool $incremental = false): array
    {
        $this->lastTouchedProductIds = null;

        $steps = [
            'pharmacies' => fn () => $this->refreshPharmacies($truncate),
            'products' => fn () => $this->refreshProducts($truncate),
            'offers' => fn () => $incremental
                ? $this->refreshOffersIncremental()
                : $this->refreshOffers($truncate),
            'product_pharmacies' => function () use ($incremental, $truncate) {
                if ($incremental) {
                    if ($this->lastTouchedProductIds !== null) {
                        return 0;
                    }

                    return $this->refreshProductPharmaciesIncremental();
                }

                return $this->refreshProductPharmacies($truncate);
            },
            'categories' => fn () => $this->refreshProductCategories($truncate),
            'category_json' => fn () => $this->refreshCategoryJson($truncate),
            'promocodes' => fn () => $incremental
                ? $this->refreshPromocodesIncremental()
                : $this->refreshPromocodes($truncate),
            'actions' => fn () => $this->refreshProductActions($truncate),
            'relations' => fn () => $this->refreshProductRelations($truncate),
            'product_characters' => fn () => $this->refreshProductCharactersJson(),
        ];

        if ($only !== null) {
            $steps = array_intersect_key($steps, array_flip($only));
        }

        $counts = [];

        foreach ($steps as $name => $callback) {
            $counts[$name] = $callback();
        }

        return $counts;
    }

    public function refreshPharmacies(bool $truncate = true): int
    {
        if ($truncate) {
            $this->truncateTable('pharmacy_cache');
        }

        $addressId = $this->tmplvars->resolveIdByNames(config('catalog_cache.pharmacy_tmplvar_names.address'));
        $coordinatesId = $this->tmplvars->resolveIdByNames(config('catalog_cache.pharmacy_tmplvar_names.coordinates'));
        $scheduleId = $this->tmplvars->resolveIdByNames(config('catalog_cache.pharmacy_tmplvar_names.schedule'));
        $imageId = $this->tmplvars->resolveIdByNames(config('catalog_cache.pharmacy_tmplvar_names.image'));

        // Порядок полей должен совпадать с INSERT: address, coordinates, image, schedule.
        $pivotFields = [
            'address' => $addressId,
            'coordinates' => $coordinatesId,
            'image' => $imageId,
            'schedule' => $scheduleId,
        ];

        $pivotSelect = collect($pivotFields)->map(function (?int $id, string $alias) {
            return $id
                ? "MAX(CASE WHEN tvc.tmplvarid = {$id} THEN tvc.value END) AS {$alias}"
                : "NULL AS {$alias}";
        })->implode(",\n                ");

        $tmplvarFilter = collect($pivotFields)->filter()->values()->unique()->implode(',');

        $joinTmplvars = $tmplvarFilter !== ''
            ? "LEFT JOIN evo_site_tmplvar_contentvalues tvc ON tvc.contentid = eso.id AND tvc.tmplvarid IN ({$tmplvarFilter})"
            : '';

        $groupBy = $tmplvarFilter !== '' ? 'GROUP BY eso.id' : '';

        $sql = <<<SQL
            INSERT INTO pharmacy_cache (
                pharmacy_id, pagetitle, alias, content, address, coordinates, image, schedule,
                published, create_dttm_raw, create_dttm, published_dttm_raw, published_dttm,
                edited_dttm_raw, edited_dttm
            )
            SELECT
                eso.id AS pharmacy_id,
                eso.pagetitle,
                eso.alias,
                eso.content,
                {$pivotSelect},
                eso.published,
                eso.createdon AS create_dttm_raw,
                CAST({$this->sqlFromUnixTime('eso.createdon')} AS CHAR) AS create_dttm,
                eso.publishedon AS published_dttm_raw,
                CAST({$this->sqlFromUnixTime('eso.publishedon')} AS CHAR) AS published_dttm,
                eso.editedon AS edited_dttm_raw,
                CAST({$this->sqlFromUnixTime('eso.editedon')} AS CHAR) AS edited_dttm
            FROM evo_site_content eso
            {$joinTmplvars}
            WHERE eso.id IN (
                SELECT DISTINCT pharmacy_id FROM evo_offers WHERE pharmacy_id IS NOT NULL
            )
            AND eso.deleted = 0
            {$groupBy}
        SQL;

        return DB::affectingStatement($sql);
    }

    public function refreshProducts(bool $truncate = true): int
    {
        if ($truncate) {
            $this->truncateTable('product_cache');
        }

        $tv = fn (string $field) => $this->tmplvars->productTmplvarId($field);
        $recipeValues = implode(',', array_map(
            fn ($value) => DB::getPdo()->quote($value),
            config('catalog_cache.recipe_values')
        ));
        $deliveryPharmacyId = (int) config('catalog_cache.delivery_pharmacy_id');
        $productTemplate = (int) config('catalog_cache.templates.product');

        $tmplvarIds = implode(',', array_map(
            fn (string $field) => (string) $tv($field),
            array_keys(config('catalog_cache.product_tmplvars'))
        ));

        $sql = <<<SQL
            INSERT INTO product_cache (
                product_id, pagetitle, alias, content, menutitle, parent,
                create_dttm, published_dttm, edited_dttm, published,
                product_description, instruction, mnn, mnn_lat, code, brand, country,
                form, release_form, termin, temperature, image, dose, recipe, is_recipe,
                product_insert, product_time_register, product_register, product_date_register,
                product_trademark, product_price_from, product_price_from_old, product_price_from_percent,
                product_sticker, is_alcohol, delivery, is_available,
                pub_date, create_dttm_raw, published_dttm_raw, edited_dttm_raw,
                created_at, updated_at
            )
            SELECT
                eso.id AS product_id,
                eso.pagetitle,
                eso.alias,
                eso.content,
                eso.menutitle,
                eso.parent,
                {$this->sqlFromUnixTime('eso.createdon')} AS create_dttm,
                {$this->sqlFromUnixTime('eso.publishedon')} AS published_dttm,
                {$this->sqlFromUnixTime('eso.editedon')} AS edited_dttm,
                eso.published,
                MAX(CASE WHEN tvc.tmplvarid = {$tv('product_description')} THEN tvc.value END) AS product_description,
                MAX(CASE WHEN tvc.tmplvarid = {$tv('instruction')} THEN tvc.value END) AS instruction,
                JSON_UNQUOTE(JSON_EXTRACT(MAX(CASE WHEN tvc.tmplvarid = {$tv('product_json')} THEN tvc.value END), '$.mnn')) AS mnn,
                MAX(CASE WHEN tvc.tmplvarid = {$tv('mnn_lat')} THEN tvc.value END) AS mnn_lat,
                JSON_UNQUOTE(JSON_EXTRACT(MAX(CASE WHEN tvc.tmplvarid = {$tv('product_json')} THEN tvc.value END), '$.code')) AS code,
                JSON_UNQUOTE(JSON_EXTRACT(MAX(CASE WHEN tvc.tmplvarid = {$tv('product_json')} THEN tvc.value END), '$.brand')) AS brand,
                JSON_UNQUOTE(JSON_EXTRACT(MAX(CASE WHEN tvc.tmplvarid = {$tv('product_json')} THEN tvc.value END), '$.country')) AS country,
                JSON_UNQUOTE(JSON_EXTRACT(MAX(CASE WHEN tvc.tmplvarid = {$tv('product_json')} THEN tvc.value END), '$.form')) AS form,
                JSON_UNQUOTE(JSON_EXTRACT(MAX(CASE WHEN tvc.tmplvarid = {$tv('product_json')} THEN tvc.value END), '$.release_form')) AS release_form,
                JSON_UNQUOTE(JSON_EXTRACT(MAX(CASE WHEN tvc.tmplvarid = {$tv('product_json')} THEN tvc.value END), '$.termin')) AS termin,
                JSON_UNQUOTE(JSON_EXTRACT(MAX(CASE WHEN tvc.tmplvarid = {$tv('product_json')} THEN tvc.value END), '$.temperature')) AS temperature,
                MAX(CASE WHEN tvc.tmplvarid = {$tv('image')} THEN tvc.value END) AS image,
                MAX(CASE WHEN tvc.tmplvarid = {$tv('dose')} THEN tvc.value END) AS dose,
                MAX(CASE WHEN tvc.tmplvarid = {$tv('recipe')} THEN tvc.value END) AS recipe,
                IF(
                    MAX(CASE WHEN tvc.tmplvarid = {$tv('recipe')} THEN tvc.value END) IN ({$recipeValues}),
                    1,
                    0
                ) AS is_recipe,
                MAX(CASE WHEN tvc.tmplvarid = {$tv('product_insert')} THEN tvc.value END) AS product_insert,
                MAX(CASE WHEN tvc.tmplvarid = {$tv('product_time_register')} THEN tvc.value END) AS product_time_register,
                MAX(CASE WHEN tvc.tmplvarid = {$tv('product_register')} THEN tvc.value END) AS product_register,
                MAX(CASE WHEN tvc.tmplvarid = {$tv('product_date_register')} THEN tvc.value END) AS product_date_register,
                MAX(CASE WHEN tvc.tmplvarid = {$tv('product_trademark')} THEN tvc.value END) AS product_trademark,
                {$this->sqlTmplvarDecimal($tv('product_price_from'))} AS product_price_from,
                {$this->sqlTmplvarDecimal($tv('product_price_from_old'))} AS product_price_from_old,
                CAST(ROUND({$this->sqlTmplvarDecimal($tv('product_price_from_percent'))}) AS SIGNED) AS product_price_from_percent,
                MAX(CASE WHEN tvc.tmplvarid = {$tv('product_sticker')} THEN tvc.value END) AS product_sticker,
                IF(COALESCE(MAX(CASE WHEN tvc.tmplvarid = {$tv('is_alcohol')} THEN tvc.value END), 'no') = 'yes', 1, 0) AS is_alcohol,
                IF(
                    (
                        MAX(CASE WHEN tvc.tmplvarid = {$tv('recipe')} THEN tvc.value END) IS NULL
                        OR MAX(CASE WHEN tvc.tmplvarid = {$tv('recipe')} THEN tvc.value END) NOT IN ({$recipeValues})
                    )
                    AND COALESCE(delivery_pharmacy.available, 0) = 1
                    AND COALESCE(MAX(CASE WHEN tvc.tmplvarid = {$tv('is_alcohol')} THEN tvc.value END), '') <> 'yes',
                    'Доставка',
                    'Самовывоз'
                ) AS delivery,
                IF(COALESCE(availability.is_available, 0) = 1, 1, 0) AS is_available,
                eso.pub_date,
                eso.createdon AS create_dttm_raw,
                eso.publishedon AS published_dttm_raw,
                eso.editedon AS edited_dttm_raw,
                NOW() AS created_at,
                NOW() AS updated_at
            FROM evo_site_content eso
            LEFT JOIN evo_site_tmplvar_contentvalues tvc
                ON tvc.contentid = eso.id AND tvc.tmplvarid IN ({$tmplvarIds})
            LEFT JOIN (
                SELECT product_id, IF(SUM({$this->sqlStockCountDecimal('stock_count')}) > 0, 1, 0) AS is_available
                FROM evo_offers
                GROUP BY product_id
            ) availability ON availability.product_id = eso.id
            LEFT JOIN (
                SELECT DISTINCT product_id, 1 AS available
                FROM evo_offers
                WHERE pharmacy_id = {$deliveryPharmacyId}
            ) delivery_pharmacy ON delivery_pharmacy.product_id = eso.id
            WHERE eso.template = {$productTemplate}
              AND eso.deleted = 0
            GROUP BY eso.id
        SQL;

        return DB::affectingStatement($sql);
    }

    public function refreshOffers(bool $truncate = true): int
    {
        if ($truncate) {
            $this->truncateTable('offer_cache');
        }

        $count = DB::affectingStatement($this->buildOffersInsertSql());

        if ($truncate) {
            $this->refreshState->mark('offers');
        }

        return $count;
    }

    public function refreshOffersIncremental(): int
    {
        $since = $this->refreshState->getSince('offers');

        if ($since === null) {
            return $this->refreshOffers(true);
        }

        $startedAt = now();
        $sinceSql = $this->quoteDateTime($since);

        $upserted = DB::affectingStatement(
            $this->buildOffersInsertSql(
                "AND COALESCE(eo.updated_at, eo.created_at) >= {$sinceSql}"
            ) . $this->buildOffersUpsertClause()
        );

        $deleted = DB::delete(<<<SQL
            DELETE oc FROM offer_cache oc
            INNER JOIN (
                SELECT DISTINCT product_id
                FROM evo_offers
                WHERE COALESCE(updated_at, created_at) >= {$sinceSql}
            ) touched ON touched.product_id = oc.product_id
            LEFT JOIN evo_offers eo
                ON eo.product_id = oc.product_id AND eo.pharmacy_id = oc.pharmacy_id
            WHERE eo.id IS NULL
        SQL);

        $this->lastTouchedProductIds = DB::table('evo_offers')
            ->whereRaw('COALESCE(updated_at, created_at) >= ?', [$since])
            ->distinct()
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->refreshProductPharmaciesIncremental($this->lastTouchedProductIds);
        $this->refreshState->mark('offers', $startedAt);

        return $upserted + $deleted;
    }

    public function refreshProductPharmacies(bool $truncate = true): int
    {
        if ($truncate) {
            $this->truncateTable('product_pharmacy_cache');
        }

        return DB::affectingStatement($this->buildProductPharmaciesInsertSql());
    }

    /**
     * @param  array<int, int>|null  $productIds
     */
    public function refreshProductPharmaciesIncremental(?array $productIds = null): int
    {
        $productIds ??= $this->resolveTouchedProductIdsSinceOffers();

        if ($productIds === []) {
            return 0;
        }

        $idsSql = $this->quoteIntList($productIds);

        $deleted = DB::delete(<<<SQL
            DELETE ppc FROM product_pharmacy_cache ppc
            WHERE ppc.product_id IN ({$idsSql})
              AND NOT EXISTS (
                  SELECT 1 FROM offer_cache oc
                  WHERE oc.product_id = ppc.product_id
                    AND oc.pharmacy_id = ppc.pharmacy_id
                    AND oc.stock_count > 0
              )
        SQL);

        $upserted = DB::affectingStatement(
            $this->buildProductPharmaciesInsertSql("AND oc.product_id IN ({$idsSql})")
            . $this->buildProductPharmaciesUpsertClause()
        );

        return $upserted + $deleted;
    }

    public function refreshProductCategories(bool $truncate = true): int
    {
        if ($truncate) {
            $this->truncateTable('product_category_cache');
        }

        $categoryTemplate = (int) config('catalog_cache.templates.category');
        $productTemplate = (int) config('catalog_cache.templates.product');
        $imagePrimary = (int) config('catalog_cache.category_tmplvar_ids.image_primary');
        $imageSecondary = (int) config('catalog_cache.category_tmplvar_ids.image_secondary');

        $sql = <<<SQL
            INSERT INTO product_category_cache (category_id, product_id, category_name, category_image)
            SELECT
                escc.category AS category_id,
                escc.doc AS product_id,
                cat.pagetitle AS category_name,
                COALESCE(
                    NULLIF(TRIM(cat_img2.value), ''),
                    NULLIF(TRIM(cat_img1.value), ''),
                    NULL
                ) AS category_image
            FROM evo_site_content_categories escc
            INNER JOIN evo_site_content cat
                ON cat.id = escc.category AND cat.template = {$categoryTemplate} AND cat.deleted = 0
            INNER JOIN evo_site_content prod
                ON prod.id = escc.doc AND prod.template = {$productTemplate} AND prod.deleted = 0
            LEFT JOIN evo_site_tmplvar_contentvalues cat_img1
                ON cat_img1.contentid = cat.id AND cat_img1.tmplvarid = {$imagePrimary}
            LEFT JOIN evo_site_tmplvar_contentvalues cat_img2
                ON cat_img2.contentid = cat.id AND cat_img2.tmplvarid = {$imageSecondary}
        SQL;

        return DB::affectingStatement($sql);
    }

    public function refreshCategoryJson(bool $truncate = true): int
    {
        if ($truncate) {
            $this->truncateTable('category_json_cache');
        }

        $sql = <<<SQL
            INSERT INTO category_json_cache (product_id, categories_json, updated_at)
            SELECT
                product_id,
                JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'category_id', category_id,
                        'category_name', category_name,
                        'category_image', category_image
                    )
                ) AS categories_json,
                NOW() AS updated_at
            FROM product_category_cache
            GROUP BY product_id
        SQL;

        return DB::affectingStatement($sql);
    }

    public function refreshPromocodes(bool $truncate = true): int
    {
        if ($truncate) {
            $this->truncateTable('product_promocode_cache');
            $this->truncateTable('product_promocode_json_cache');
        }

        $rowCache = DB::affectingStatement($this->buildPromocodesInsertSql());
        $jsonCache = $this->rebuildPromocodeJsonCache();

        if ($truncate) {
            $this->refreshState->mark('promocodes');
        }

        return $rowCache + $jsonCache;
    }

    public function refreshPromocodesIncremental(): int
    {
        $since = $this->refreshState->getSince('promocodes');

        if ($since === null) {
            return $this->refreshPromocodes(true);
        }

        $startedAt = now();
        $sinceSql = $this->quoteDateTime($since);

        $touchedPromocodeIds = DB::table('evo_promocodes')
            ->where(function ($query) use ($since) {
                $query->where('updatedon', '>=', $since)
                    ->orWhere('createdon', '>=', $since)
                    ->orWhere(function ($query) use ($since) {
                        $query->where('end', '>=', $since)->where('end', '<=', now());
                    })
                    ->orWhere(function ($query) use ($since) {
                        $query->where('begin', '>=', $since)->where('begin', '<=', now());
                    });
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($touchedPromocodeIds === []) {
            $this->refreshState->mark('promocodes', $startedAt);

            return 0;
        }

        $promocodeIdsSql = $this->quoteIntList($touchedPromocodeIds);

        $affectedProductIds = DB::table('evo_promocodes_links')
            ->whereIn('pcid', $touchedPromocodeIds)
            ->distinct()
            ->pluck('link')
            ->map(fn ($id) => (int) $id)
            ->merge(
                DB::table('product_promocode_cache')
                    ->whereIn('promocode_id', $touchedPromocodeIds)
                    ->distinct()
                    ->pluck('product_id')
                    ->map(fn ($id) => (int) $id)
            )
            ->unique()
            ->values()
            ->all();

        DB::delete("DELETE FROM product_promocode_cache WHERE promocode_id IN ({$promocodeIdsSql})");

        $inserted = DB::affectingStatement(
            $this->buildPromocodesInsertSql("AND ep.id IN ({$promocodeIdsSql})")
        );

        $jsonUpdated = $this->rebuildPromocodeJsonCache($affectedProductIds);
        $this->refreshState->mark('promocodes', $startedAt);

        return $inserted + $jsonUpdated;
    }

    public function refreshProductActions(bool $truncate = true): int
    {
        if ($truncate) {
            $this->truncateTable('product_action_cache');
            $this->truncateTable('product_action_json_cache');
        }

        $actionTemplate = (int) config('catalog_cache.templates.action');
        $goodsTmplvarId = (int) config('catalog_cache.action_goods_tmplvar_id');

        $actions = DB::table('evo_site_content as eso')
            ->leftJoin('evo_site_tmplvar_contentvalues as tv', function ($join) use ($goodsTmplvarId) {
                $join->on('tv.contentid', '=', 'eso.id')
                    ->where('tv.tmplvarid', '=', $goodsTmplvarId);
            })
            ->where('eso.template', $actionTemplate)
            ->where('eso.published', 1)
            ->where('eso.deleted', 0)
            ->get([
                'eso.id as promotion_id',
                'eso.pagetitle as promotion_text',
                'eso.published',
                'eso.createdon',
                'eso.editedon',
                'eso.publishedon',
                'tv.value as goods_ids',
            ]);

        $rows = [];
        $seen = [];

        foreach ($actions as $action) {
            if (empty($action->goods_ids)) {
                continue;
            }

            foreach ($this->parseIdList($action->goods_ids) as $productId) {
                if ($productId <= 0) {
                    continue;
                }

                $key = "{$productId}:{$action->promotion_id}";

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;

                $rows[] = [
                    'product_id' => $productId,
                    'promotion_id' => $action->promotion_id,
                    'promotion_text' => $action->promotion_text,
                    'promotion_flg' => 1,
                    'published' => (bool) $action->published,
                    'create_dttm_raw' => $action->createdon,
                    'edited_dttm_raw' => $action->editedon,
                    'published_dttm_raw' => $action->publishedon,
                    'create_dttm' => $action->createdon ? date('Y-m-d H:i:s', $action->createdon) : null,
                    'edited_dttm' => $action->editedon ? date('Y-m-d H:i:s', $action->editedon) : null,
                    'published_dttm' => $action->publishedon ? date('Y-m-d H:i:s', $action->publishedon) : null,
                ];
            }
        }

        $inserted = 0;

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('product_action_cache')->insert($chunk);
            $inserted += count($chunk);
        }

        $jsonInserted = DB::affectingStatement(<<<SQL
            INSERT INTO product_action_json_cache (product_id, action_json, updated_at)
            SELECT
                product_id,
                JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'promotion_id', promotion_id,
                        'promotion_text', promotion_text,
                        'promotion_flg', promotion_flg,
                        'published', published,
                        'create_dttm_raw', create_dttm_raw,
                        'edited_dttm_raw', edited_dttm_raw,
                        'published_dttm_raw', published_dttm_raw,
                        'create_dttm', create_dttm,
                        'edited_dttm', edited_dttm,
                        'published_dttm', published_dttm
                    )
                ) AS action_json,
                NOW() AS updated_at
            FROM product_action_cache
            WHERE published = 1
            GROUP BY product_id
        SQL);

        return $inserted + $jsonInserted;
    }

    public function refreshProductRelations(bool $truncate = true): int
    {
        if ($truncate) {
            $this->truncateTable('product_brand_cache');
            $this->truncateTable('product_similar_cache');
            $this->truncateTable('product_related_cache');
            $this->truncateTable('product_category_products_cache');
        }

        $brandCount = DB::affectingStatement(<<<SQL
            INSERT INTO product_brand_cache (product_id, brand_products_json, updated_at)
            SELECT
                p1.product_id,
                (
                    SELECT JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'product_id', p2.product_id,
                            'pagetitle', p2.pagetitle,
                            'image', p2.image,
                            'product_price_from', p2.product_price_from
                        )
                    )
                    FROM product_cache p2
                    WHERE p2.brand = p1.brand
                      AND p2.brand IS NOT NULL
                      AND p2.brand <> ''
                      AND p2.product_id <> p1.product_id
                      AND p2.published = 1
                    LIMIT 20
                ) AS brand_products_json,
                NOW() AS updated_at
            FROM product_cache p1
            WHERE p1.brand IS NOT NULL AND p1.brand <> ''
        SQL);

        $relatedCount = DB::affectingStatement(<<<SQL
            INSERT INTO product_related_cache (product_id, related_products_json, updated_at)
            SELECT
                pcc.product_id,
                (
                    SELECT JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'product_id', p2.product_id,
                            'pagetitle', p2.pagetitle,
                            'image', p2.image,
                            'product_price_from', p2.product_price_from
                        )
                    )
                    FROM product_category_cache pcc2
                    INNER JOIN product_cache p2 ON p2.product_id = pcc2.product_id AND p2.published = 1
                    WHERE pcc2.category_id = pcc.category_id
                      AND pcc2.product_id <> pcc.product_id
                    LIMIT 20
                ) AS related_products_json,
                NOW() AS updated_at
            FROM (
                SELECT product_id, MIN(category_id) AS category_id
                FROM product_category_cache
                GROUP BY product_id
            ) pcc
        SQL);

        $categoryProductsCount = DB::affectingStatement(<<<SQL
            INSERT INTO product_category_products_cache (product_id, category_products_json, updated_at)
            SELECT
                pcc.product_id,
                (
                    SELECT JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'product_id', p2.product_id,
                            'pagetitle', p2.pagetitle,
                            'image', p2.image,
                            'product_price_from', p2.product_price_from
                        )
                    )
                    FROM product_category_cache pcc2
                    INNER JOIN product_cache p2 ON p2.product_id = pcc2.product_id AND p2.published = 1
                    WHERE pcc2.category_id IN (
                        SELECT category_id FROM product_category_cache WHERE product_id = pcc.product_id
                    )
                      AND pcc2.product_id <> pcc.product_id
                    LIMIT 30
                ) AS category_products_json,
                NOW() AS updated_at
            FROM (
                SELECT DISTINCT product_id FROM product_category_cache
            ) pcc
        SQL);

        // similar_products заполняется через Rees46 в runtime; оставляем пустую таблицу.
        return $brandCount + $relatedCount + $categoryProductsCount;
    }

    public function refreshProductCharactersJson(): int
    {
        $path = database_path('sql/refresh/populate_product_charachters_json.sql');

        if (!File::exists($path)) {
            return 0;
        }

        return DB::affectingStatement(File::get($path));
    }

    /**
     * @return array<int, int>
     */
    protected function parseIdList(string $value): array
    {
        $parts = preg_split('/\s*,\s*/', trim($value)) ?: [];

        return array_values(array_unique(array_filter(array_map('intval', $parts))));
    }

    protected function sqlFromUnixTime(string $column): string
    {
        return "CASE WHEN {$column} > 0 THEN FROM_UNIXTIME({$column}) ELSE NULL END";
    }

    protected function sqlTmplvarDecimal(int $tmplvarId, int $precision = 10, int $scale = 2): string
    {
        return $this->sqlNullableDecimal(
            "MAX(CASE WHEN tvc.tmplvarid = {$tmplvarId} THEN tvc.value END)",
            $precision,
            $scale
        );
    }

    protected function sqlNullableDecimal(string $expression, int $precision = 10, int $scale = 2): string
    {
        $normalized = "REPLACE(REPLACE(TRIM({$expression}), ',', '.'), ' ', '')";

        return "CASE WHEN {$normalized} REGEXP '^[0-9]+(\\.[0-9]+)?$' "
            . "THEN CAST({$normalized} AS DECIMAL({$precision},{$scale})) "
            . 'ELSE NULL END';
    }

    protected function sqlAsJsonColumn(string $column): string
    {
        return "CASE
            WHEN {$column} IS NULL OR TRIM({$column}) = '' THEN NULL
            WHEN JSON_VALID({$column}) THEN CAST({$column} AS JSON)
            ELSE CAST(JSON_QUOTE({$column}) AS JSON)
        END";
    }

    protected function sqlExpirationDate(string $column): string
    {
        $trimmed = "TRIM({$column})";
        $isoDate = "LEFT({$trimmed}, 10)";
        $dotDate = "REGEXP_SUBSTR({$column}, '[0-9]{2}\\.[0-9]{2}\\.[0-9]{4}')";

        return "CASE
            WHEN {$column} IS NULL OR {$trimmed} = '' THEN NULL
            WHEN {$trimmed} REGEXP '^(0000|0001)-' THEN NULL
            WHEN {$trimmed} REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}'
                AND CAST(LEFT({$trimmed}, 4) AS UNSIGNED) BETWEEN 1000 AND 9999
                THEN DATE({$isoDate})
            WHEN {$trimmed} REGEXP '^[0-9]{2}\\.[0-9]{2}\\.[0-9]{4}$'
                AND CAST(RIGHT({$trimmed}, 4) AS UNSIGNED) BETWEEN 1000 AND 9999
                THEN STR_TO_DATE({$trimmed}, '%d.%m.%Y')
            WHEN {$trimmed} REGEXP '[0-9]{2}\\.[0-9]{2}\\.[0-9]{4}'
                AND CAST(RIGHT({$dotDate}, 4) AS UNSIGNED) BETWEEN 1000 AND 9999
                THEN STR_TO_DATE({$dotDate}, '%d.%m.%Y')
            ELSE NULL
        END";
    }

    protected function sqlStockCountDecimal(string $column): string
    {
        return "CAST(COALESCE(NULLIF(TRIM({$column}), ''), '0') AS DECIMAL(12,3))";
    }

    protected function sqlStockCountInt(string $column): string
    {
        return "CAST(FLOOR({$this->sqlStockCountDecimal($column)}) AS SIGNED)";
    }

    protected function buildOffersInsertSql(string $extraWhere = ''): string
    {
        return <<<SQL
            INSERT INTO offer_cache (
                product_id, pharmacy_id, pharmacy_name, pharmacy_alias, product_name,
                address, coordinates, schedule, price, price_old, stock_count,
                expiration_date, updated_at
            )
            SELECT
                eo.product_id,
                eo.pharmacy_id,
                ph.pagetitle AS pharmacy_name,
                ph.alias AS pharmacy_alias,
                pr.pagetitle AS product_name,
                ph.address,
                ph.coordinates,
                {$this->sqlAsJsonColumn('ph.schedule')} AS schedule,
                eo.price,
                eo.price_old,
                {$this->sqlStockCountInt('eo.stock_count')} AS stock_count,
                {$this->sqlExpirationDate('eo.expiration_date')} AS expiration_date,
                NOW() AS updated_at
            FROM evo_offers eo
            LEFT JOIN pharmacy_cache ph ON ph.pharmacy_id = eo.pharmacy_id
            LEFT JOIN evo_site_content pr ON pr.id = eo.product_id
            WHERE eo.pharmacy_id IS NOT NULL
              AND eo.product_id IS NOT NULL
              {$extraWhere}
        SQL;
    }

    protected function buildOffersUpsertClause(): string
    {
        return <<<'SQL'

            ON DUPLICATE KEY UPDATE
                pharmacy_name = VALUES(pharmacy_name),
                pharmacy_alias = VALUES(pharmacy_alias),
                product_name = VALUES(product_name),
                address = VALUES(address),
                coordinates = VALUES(coordinates),
                schedule = VALUES(schedule),
                price = VALUES(price),
                price_old = VALUES(price_old),
                stock_count = VALUES(stock_count),
                expiration_date = VALUES(expiration_date),
                updated_at = VALUES(updated_at)
        SQL;
    }

    protected function buildProductPharmaciesInsertSql(string $extraWhere = ''): string
    {
        return <<<SQL
            INSERT INTO product_pharmacy_cache (
                product_id, pharmacy_id, product_name, price, price_old, stock_count,
                expiration_date, recipe, is_recipe, is_alcohol, pharmacy_name, pharmacy_alias,
                pharmacy_delivery, address, coordinates, schedule, updated_at
            )
            SELECT
                oc.product_id,
                oc.pharmacy_id,
                COALESCE(oc.product_name, pc.pagetitle) AS product_name,
                oc.price,
                oc.price_old,
                oc.stock_count,
                oc.expiration_date,
                pc.recipe,
                pc.is_recipe,
                IF(pc.is_alcohol, 'yes', 'no') AS is_alcohol,
                oc.pharmacy_name,
                oc.pharmacy_alias,
                pc.delivery AS pharmacy_delivery,
                oc.address,
                oc.coordinates,
                COALESCE(ph.schedule, JSON_UNQUOTE(CAST(oc.schedule AS CHAR))) AS schedule,
                NOW() AS updated_at
            FROM offer_cache oc
            INNER JOIN product_cache pc ON pc.product_id = oc.product_id
            LEFT JOIN pharmacy_cache ph ON ph.pharmacy_id = oc.pharmacy_id
            WHERE oc.stock_count > 0
              {$extraWhere}
        SQL;
    }

    protected function buildProductPharmaciesUpsertClause(): string
    {
        return <<<'SQL'

            ON DUPLICATE KEY UPDATE
                product_name = VALUES(product_name),
                price = VALUES(price),
                price_old = VALUES(price_old),
                stock_count = VALUES(stock_count),
                expiration_date = VALUES(expiration_date),
                recipe = VALUES(recipe),
                is_recipe = VALUES(is_recipe),
                is_alcohol = VALUES(is_alcohol),
                pharmacy_name = VALUES(pharmacy_name),
                pharmacy_alias = VALUES(pharmacy_alias),
                pharmacy_delivery = VALUES(pharmacy_delivery),
                address = VALUES(address),
                coordinates = VALUES(coordinates),
                schedule = VALUES(schedule),
                updated_at = VALUES(updated_at)
        SQL;
    }

    protected function buildPromocodesInsertSql(string $extraWhere = ''): string
    {
        return <<<SQL
            INSERT INTO product_promocode_cache (
                product_id, promocode_id, promocode, discount, begin_date, end_date
            )
            SELECT
                epl.link AS product_id,
                ep.id AS promocode_id,
                ep.promocode,
                ep.discount,
                ep.begin AS begin_date,
                ep.end AS end_date
            FROM evo_promocodes ep
            INNER JOIN evo_promocodes_links epl ON epl.pcid = ep.id
            WHERE ep.active = 1
              AND NOW() > ep.begin
              AND NOW() < ep.end
              {$extraWhere}
        SQL;
    }

    /**
     * @param  array<int, int>|null  $productIds
     */
    protected function rebuildPromocodeJsonCache(?array $productIds = null): int
    {
        if ($productIds !== null && $productIds === []) {
            return 0;
        }

        if ($productIds !== null) {
            $idsSql = $this->quoteIntList($productIds);

            DB::delete("DELETE FROM product_promocode_json_cache WHERE product_id IN ({$idsSql})");
        } else {
            $this->truncateTable('product_promocode_json_cache');
        }

        $extraWhere = $productIds !== null
            ? 'AND product_id IN (' . $this->quoteIntList($productIds) . ')'
            : '';

        return DB::affectingStatement(<<<SQL
            INSERT INTO product_promocode_json_cache (product_id, promocodes_json, updated_at)
            SELECT
                product_id,
                JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'promotion_id', promocode_id,
                        'promocode', promocode,
                        'promocode_percent', discount,
                        'min_amount', NULL,
                        'usages', NULL,
                        'begin', begin_date,
                        'end', end_date
                    )
                ) AS promocodes_json,
                NOW() AS updated_at
            FROM product_promocode_cache
            WHERE 1 = 1 {$extraWhere}
            GROUP BY product_id
            ON DUPLICATE KEY UPDATE
                promocodes_json = VALUES(promocodes_json),
                updated_at = VALUES(updated_at)
        SQL);
    }

    /**
     * @return array<int, int>
     */
    protected function resolveTouchedProductIdsSinceOffers(): array
    {
        $since = $this->refreshState->getSince('offers');

        if ($since === null) {
            return [];
        }

        return DB::table('evo_offers')
            ->whereRaw('COALESCE(updated_at, created_at) >= ?', [$since])
            ->distinct()
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $ids
     */
    protected function quoteIntList(array $ids): string
    {
        return implode(',', array_map('intval', $ids));
    }

    protected function quoteDateTime(\DateTimeInterface $dateTime): string
    {
        return DB::getPdo()->quote($dateTime->format('Y-m-d H:i:s'));
    }

    protected function truncateTable(string $table): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            DB::table($table)->truncate();
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
