<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Models\EVO\EvoSiteTmplvarContentvalue;
use App\Models\ProductPharmacyView;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class ProductInfoViewJson
 *
 *
 * @package App\Models
 */
class ProductInfoViewJsonDetailed extends Model
{
    protected $table = 'evo_product_info_view_json_detailed';

    public $timestamps = false;

	protected $casts = [
		'product_charachters' => 'array',
        'categories_json' => 'array',
        'promocodes_json' => 'array',
        'action_json' => 'array',
        'brand_products' => 'array',
        'similar_products' => 'array',
        'related_products' => 'array',
        'category_products' => 'array',
	];

    public function productPharmacies(): HasMany
    {
        return $this->hasMany(ProductPharmacyView::class, 'product_id', 'product_id');
    }

}