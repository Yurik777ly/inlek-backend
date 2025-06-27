<?php

namespace App\Services\Promotion;

use App\Models\PromotionView;
use App\Services\Product\ProductService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PromotionService
{
    public function __construct(
        protected readonly PromotionView $PromotionView,
        protected readonly ProductService $ProductService
    ) {}

    public function getPromotionsList(): Collection
    {
        return $this->PromotionView
                    ->query()
                    ->where('published', 1)
                    ->orderBy('create_dttm_raw', 'DESC')
                    ->get()
                    ->makeHidden([
                        'goods_ids', 
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
    }

    public function getPromotionById(int $promotion_id): array
    {
        $promotion = $this->PromotionView
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
        $products = $this->ProductService->getPromotionsList($product_ids);
        return ['promotion' => $promotion, 'products' => $products];
    }
}
