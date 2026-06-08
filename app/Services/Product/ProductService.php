<?php

namespace App\Services\Product;

use App\Http\Dto\Product\ProductDTO;
use App\Models\EVO\EvoOffers;
use App\Models\PharmaciesView;
use App\Models\ProductActionView;
use App\Models\DailyProductsView;
use App\Models\ActionView;
use App\Models\ProductInfoViewJson;
use App\Models\ProductInfoViewJsonFilter;
use App\Models\ProductInfoViewJsonDetailed;
use App\Models\ProductPharmacyJson;
use App\Models\ProductInfoViewJsonOptNoAct;
use App\Models\ProductPharmacyView;
use App\Services\Rees46\Rees46;
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
            ->get(['product_id','product_charachters','action_json','promocodes_json','categories_json', 'brand_products', 'related_products', 'category_products', 'instruction'])->first();
        if ($product) {
            $categories = $product->categories_json;
            $product->similar_products = Rees46::getRecommendation(last($categories)['category_id']);
            $product = $product->toArray();

            $product['availability'] = 'absent';
            if ($this->getAvailablePharmaciesCount( $productDto->productId) > 0)
            {
                $product['availability'] = 'part';
            }
        }
        return $product;
    }

    public function getDailyProducts(): array
    {
        $daily = $this->DailyProductsView::with('productInfo:product_id,product_charachters,action_json,promocodes_json,categories_json,is_available')
            ->get(['product_id'])->toArray();
        return $daily;
    }

    public function getFilteredProducts(ProductDTO $productDto, int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        $priceFilter =
            $this->ProductInfoViewJsonFilter->query()
            ->select(['evo_product_info_view_json_opt.product_id', 'product_charachters', 'action_json', 'promocodes_json', 'is_available'])
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
            ->when((isset($productDto->recipe)) && $productDto->recipe == false, function($query) {
                return $query->where('is_recipe', false);
            })
            ->when((isset($productDto->recipe)) && $productDto->recipe == true, function($query) {
                return $query->where('is_recipe', true);
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
                        ->where('evo_category_product_view.category_id', $productDto->categoryId)
                        ->whereRaw('evo_product_info_view_json_opt.product_id = evo_category_product_view.product_id')
                        ;
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
            ->paginate($perPage, ['evo_product_info_view_json_opt.product_id','product_charachters','action_json','promocodes_json','categories_json'], 'page', $page);

        return $priceFilter;
    }

    public function getPharmaciesByProductId(ProductDTO $productDto) //: Collection|array
    {
        $query = $this->ProductPharmacyJson->query()
            ->select([
                'evo_product_pharmacy_json.product_id',
                'evo_product_info_view_json_opt.is_recipe',
                'evo_product_info_view_json_opt.is_alcohol',
                'evo_product_info_view_json_opt.is_available',
                'evo_product_pharmacy_json.product_pharmacy_json',
                'evo_product_pharmacy_json.coordinates',
                'evo_product_pharmacy_json.pharmacy_id',
                'evo_product_pharmacy_json.pharmacy_delivery'
            ])
            ->distinct()
            ->join(
                'evo_product_info_view_json_opt',
                'evo_product_pharmacy_json.product_id',
                '=',
                'evo_product_info_view_json_opt.product_id'
            )
            ->where('evo_product_pharmacy_json.product_id', $productDto->productId);

        $query->when(!empty($productDto->pharmacyId), function($q) use($productDto) {
            return $q->where('pharmacy_id', $productDto->pharmacyId);
        });

        $query->when(!empty($productDto->pharmacyDelivery), function($q) use($productDto) {
            return $q
                ->whereIn('evo_product_info_view_json_opt.delivery', $productDto->pharmacyDelivery)
                ->whereIn('evo_product_pharmacy_json.pharmacy_delivery', $productDto->pharmacyDelivery);
        });

        $query->when(!empty($productDto->pharmacyAddress), function($q) use($productDto) {
            return $q->whereRaw(
                "LOWER(JSON_UNQUOTE(JSON_EXTRACT(product_pharmacy_json, '$.address'))) LIKE ?",
                ['%' . strtolower($productDto->pharmacyAddress) . '%']
            );
        });

        $items = $query->get();


        if (empty($productDto->geoLat) || empty($productDto->geoLong)) {
            return $items;
        }

        $userLat = (float)$productDto->geoLat;
        $userLng = (float)$productDto->geoLong;

        $items = $items->map(function($row) use ($userLat, $userLng) {
            $distance = null;

            $coords = $row->coordinates ?? null;
            if (!empty($coords) && is_string($coords)) {
                $parts = preg_split('/\s*,\s*/', trim($coords));

                if (count($parts) >= 2) {
                    $lat = (float) $parts[0];
                    $lng = (float) $parts[1];

                    if ($lat !== 0.0 || $lng !== 0.0) {
                        $distance = $this->haversineDistance($userLat, $userLng, $lat, $lng);
                    }
                }
            }

            $row->distance_m = $distance;
            $row->distance_km = $distance !== null ? ($distance / 1000.0) : null;

            return $row;
        });

        $items = $items->sortBy(function($row) {
            return $row->distance_m === null ? PHP_INT_MAX : (int) round($row->distance_m);
        })->values();

        return $items;
    }

    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function getAvailablePharmaciesCount($productId):int
    {
        return EvoOffers::where('product_id', $productId)->count();
    }
}
