<?php

namespace App\Services\Category;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\CategoryView;

class CategoryService
{
    public function __construct(
        private readonly CategoryView $CategoryView,
    ) {}

    public function getCategories(int $id = 2): Collection
    {
        return $this->CategoryView
            ->query()
            ->where('parent', $id)
            ->get()
            ->makeHidden([
                'create_dttm_raw',
                'edited_dttm_raw',
                'published_dttm_raw',
                'content',
                'menutitle',
                'published_dttm',
                'edited_dttm',
                'published',
                'pub_date',
                'category_advertisement',
                'create_dttm',
            ]);
    }

    public function getForms(int $id = 2): Collection
    {
        return $this->getDistinctProductValues($id, 'release_form');
    }

    public function getBrands(int $id = 2): Collection
    {
        return $this->getDistinctProductValues($id, 'brand');
    }

    public function getCountries(int $id = 2): Collection
    {
        return $this->getDistinctProductValues($id, 'country');
    }

    private function getDistinctProductValues(int $categoryId, string $column): Collection
    {
        $categoryIds = $this->getCategoryTreeIds($categoryId);

        if ($categoryIds === []) {
            return collect();
        }

        return DB::table('evo_category_product_view as ecpv')
            ->join('product_cache as pc', 'pc.product_id', '=', 'ecpv.product_id')
            ->whereIn('ecpv.category_id', $categoryIds)
            ->where('pc.published', 1)
            ->whereNotNull("pc.$column")
            ->where("pc.$column", '!=', '')
            ->distinct()
            ->orderBy("pc.$column")
            ->pluck("pc.$column")
            ->values();
    }

    /**
     * @return list<int>
     */
    private function getCategoryTreeIds(int $categoryId): array
    {
        $rows = DB::select(
            <<<'SQL'
            WITH RECURSIVE category_tree AS (
                SELECT category_id
                FROM evo_category_view
                WHERE category_id = ?

                UNION ALL

                SELECT c.category_id
                FROM evo_category_view c
                INNER JOIN category_tree ct ON c.parent = ct.category_id
            )
            SELECT category_id FROM category_tree
            SQL,
            [$categoryId],
        );

        return array_map(static fn ($row) => (int) $row->category_id, $rows);
    }
}
