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

    public function getCategories(int $id=2): Collection
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
        return collect(DB::select("CALL GetCategoryTreeForm(" . $id . ")"))->pluck('form')
		->filter()
            	->values();
    }

    public function getBrands(int $id = 2): Collection
    {
        return collect(DB::select("CALL GetCategoryTreeBrand(" . $id . ")"))->pluck('brand')
		->filter()
            	->values();
    }

    public function getCountries(int $id = 2): Collection
    {
        return  collect(DB::select("CALL GetCategoryTreeCountry(" . $id . ")"))->pluck('country')
		->filter()
            	->values();
    }
}
