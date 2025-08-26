<?php

namespace App\Services\Product;

use App\Http\Dto\Product\ProductDTO;
use App\Models\ProductActionView;
use App\Models\DailyProductsView;
use App\Models\ActionView;
use App\Models\ProductInfoViewJson;
use App\Models\ProductInfoViewJsonFilter;
use App\Models\ProductInfoViewJsonDetailed;
use App\Models\ProductPharmacyJson;
use App\Models\ProductInfoViewJsonOptNoAct;
use App\Models\ProductPharmacyView;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\Product\ContentValueService;
//use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;

const RECIPE_ARRAY = [
    'Рецептурный',
    'Рецепт урный',
];

class ProductService
{
    public function __construct(
        protected readonly ProductActionView            $ProductActionView,
        protected readonly ContentValueService          $ContentValueService,
        protected readonly DailyProductsView            $DailyProductsView,
        protected readonly ActionView                   $ActionView,
        protected readonly ProductInfoViewJson          $ProductInfoViewJson,
        protected readonly ProductInfoViewJsonFilter    $ProductInfoViewJsonFilter,
        protected readonly ProductInfoViewJsonDetailed  $productInfoViewJsonDetailed,
        protected readonly ProductPharmacyView          $ProductPharmacyView,
        protected readonly ProductPharmacyJson          $ProductPharmacyJson,
        protected readonly ProductInfoViewJsonOptNoAct  $ProductInfoViewJsonOptNoAct,
    ) {}

    public function getActionsList(array $ids = []): ?Collection
    {
        if (empty($ids)) return null;
        $data = $this->ProductActionView
                    ->query()
                    ->whereIn('product_id', $ids)
                    ->where('published', 1)
                    ->with('contentvalues')
                    ->get()
                    ->makeHidden([
                        'promotion_id',
                        'promotion_flg',
                        'promotion_text',
                        'create_dttm_raw',
                        'edited_dttm_raw',
                        'published_dttm_raw',
                        'published_dttm',
                        'edited_dttm',
                        'published',
                        'pub_date',
                    ]);

        return collect($ids)->map(function ($id) use ($data) {
            $rowArray = [];
            $row = $data->where('product_id', $id)->first();
            if($row) {
                $contentValues = $this->ContentValueService->getContentValues($row->contentvalues);
                unset($row->contentvalues);
                $rowArray = $row->toArray();
                $rowArray['info'] = $contentValues;
            }
            return $rowArray;
        });
    }

    public function getActionById(int $promotion_id): array
    {
        $promotion = $this->ActionView
                    ->query()
                    ->where(['published' => 1, 'promotion_id' => $promotion_id])
                    ->first()
                    ->makeHidden([
                        'create_dttm_raw',
                        'edited_dttm_raw',
                        'published_dttm_raw',
                        'content',
                        'menutitle',
                        'pub_date',
                        'published_dttm',
                        'edited_dttm',
                        'published',
                    ]);

        $product_ids = explode(',', $promotion->goods_ids);
        $products = $this->ProductService->getProducts($product_ids);
        return [
            'promotion' => $promotion,
            'products' => $products
        ];
    }

    public function getForms($category = 2): array
    {
        return [];
    }

    public function getManufacturers($category = 2): array
    {
        return [];
    }

    public function getProductDetails(ProductDTO $productDto): ?array
    {
        $product = $this->productInfoViewJsonDetailed->query()->where('product_id', $productDto->productId)
            ->get(['product_id','product_charachters','action_json','promocodes_json','categories_json', 'brand_products', 'similar_products', 'related_products', 'category_products', 'instruction'])->first();
        if ($product) {
            $product = $product->toArray();
        }
        return $product;
    }

    public function getDailyProducts(): array
    {
        $daily = $this->DailyProductsView::with('productInfo:product_id,product_charachters,action_json,promocodes_json,categories_json')
            ->get(['product_id'])->toArray();
        return $daily;
    }

    public function getFilteredProducts(ProductDTO $productDto, $perPage=10, $page)//: array|LengthAwarePaginator
    {
        $priceFilter =
            $this->ProductInfoViewJsonFilter->query()
            ->select(['evo_product_info_view_json_opt.product_id', 'product_charachters', 'action_json', 'promocodes_json'])
            ->where('product_price_from', '>=', $productDto->priceFrom)
            ->where('product_price_from', '<=', $productDto->priceTo)
            ->when(!empty($productDto->releaseForm), function($query) use($productDto) {
                return $query->whereIn('release_form', $productDto->releaseForm);
            })
            ->when(!empty($productDto->form), function($query) use($productDto) {
                return $query->whereIn('form', $productDto->form);
            })
            ->when(!empty($productDto->brand), function($query) use($productDto) {
                return $query->whereIn('brand', $productDto->brand);
            })
            ->when((isset($productDto->recipe)) && $productDto->recipe == false, function($query) use($productDto) {
                return $query->where(fn($q) => $q->where('recipe', 'no')->orWhereNull('recipe')->orWhere('recipe', ''));
            })
            ->when((isset($productDto->recipe)) && $productDto->recipe == true, function($query) use($productDto) {
                 return $query->where('recipe', 'yes');
            })
            ->when(!empty($productDto->country), function($query) use($productDto) {
                return $query->whereIn('country', $productDto->country);
            })
            ->when((!empty($productDto->delivery) && $productDto->delivery == '1'), function($query) use($productDto) {
                return $query->where('delivery', 'Доставка');
            })
            ->when($productDto->action, function($query) use($productDto) {
                return $query->where(fn($q) => $q->whereNotNull('action_json')->orWhereNotNull('product_price_from_percent'));
            })
            ->when($productDto->available, function ($query) use ($productDto) {
                return $query->where('is_available', $productDto->available);
            })
            ->when(!empty($productDto->categoryId), function ($query) use ($productDto) {
                // Добавляем условие EXISTS вместо JOIN
                return $query->whereExists(function ($subQuery) use ($productDto) {
                    $subQuery->select(DB::raw(1))
                        ->from('evo_category_product_view')
                        ->where('evo_category_product_view.category_id', $productDto->categoryId);
                });
            })
            ->when($productDto->sortBy == 'price_desc', function($query) use($productDto) {
                return $query->orderByRaw('(product_price_from+0) DESC');
            })
            ->when($productDto->sortBy == 'price_asc', function($query) use($productDto) {
                return $query->orderByRaw('(product_price_from+0) ASC');
            })
            ->when($productDto->sortBy == 'popularity', function($query) use($productDto) {
                return $query->orderBy('evo_product_info_view_json_opt.product_id', 'ASC');
            })
            //->simplePaginate($perPage, ['evo_product_info_view_json_opt.product_id','product_charachters','action_json','promocodes_json','categories_json'], 'page', $page);
            ->paginate($perPage, ['evo_product_info_view_json.product_id','product_charachters','action_json','promocodes_json','categories_json'], 'page', $page);

        return $priceFilter;
    }

    public function getPharmaciesByProductId(ProductDTO $productDto) //: array|LengthAwarePaginator
    {
        $query = $this->ProductPharmacyJson->query()
            ->from('evo_product_pharmacy_json as ep')
            ->select([
                'ep.product_id',
                'info.is_recipe',
                'info.is_alcohol',
                'ep.product_pharmacy_json',
                'ep.pharmacy_id',
                'ep.pharmacy_delivery',
            ])
            ->join(
                'evo_product_info_view_json_opt_noact as info',
                'ep.product_id',
                '=',
                'info.product_id'
            )
            ->where('ep.product_id', $productDto->productId)
            ->when(!empty($productDto->pharmacyId), function ($q) use ($productDto) {
                return $q->where('ep.pharmacy_id', $productDto->pharmacyId);
            })
            ->when(!empty($productDto->pharmacyDelivery), function ($q) use ($productDto) {
                return $q
                    ->whereIn('info.delivery', $productDto->pharmacyDelivery)
                    ->whereIn('ep.pharmacy_delivery', $productDto->pharmacyDelivery);
            })
            ->when(!empty($productDto->pharmacyAddress), function ($q) use ($productDto) {
                return $q->whereRaw(
                    "LOWER(JSON_UNQUOTE(JSON_EXTRACT(ep.product_pharmacy_json, '$.address'))) LIKE ?",
                    ['%' . strtolower($productDto->pharmacyAddress) . '%']
                );
            });

        $distanceSql = "
            (6371 * acos(
                cos(radians(?)) *
                cos(radians(CAST(TRIM(SUBSTRING_INDEX(REPLACE(ep.coordinates, ' ', ''), ',', 1)) AS DECIMAL(10,6)))) *
                cos(radians(CAST(TRIM(SUBSTRING_INDEX(REPLACE(ep.coordinates, ' ', ''), ',', -1)) AS DECIMAL(10,6))) - radians(?))
                +
                sin(radians(?)) *
                sin(radians(CAST(TRIM(SUBSTRING_INDEX(REPLACE(ep.coordinates, ' ', ''), ',', 1)) AS DECIMAL(10,6))))
            )) AS distance_km
        ";

        $query->selectRaw($distanceSql, [
            $productDto->geoLat,
            $productDto->geoLong,
            $productDto->geoLat
        ]);

        $query->orderByRaw('distance_km ASC');

        return $query->get();
    }
}
