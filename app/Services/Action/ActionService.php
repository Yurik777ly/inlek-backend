<?php

namespace App\Services\Action;

use App\Models\ActionView;
use App\Services\Product\ProductService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ActionService
{
    public function __construct(
        protected readonly ActionView $ActionView,
        protected readonly ProductService $ProductService
    ) {}

    public function getActionsList(): Collection
    {
        return $this->ActionView
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

    public function getActionById(int $action_id): array
    {
        $action = $this->ActionView
                    ->query()
                    ->where(['published' => 1, 'action_id' => $action_id])
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

        $product_ids = explode(',', $action->goods_ids);
        $products = $this->ProductService->getActionsList($product_ids);
        return ['action' => $action, 'products' => $products];
    }
}
