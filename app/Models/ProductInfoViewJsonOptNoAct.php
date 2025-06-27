<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Models\EVO\EvoSiteTmplvarContentvalue;
use App\Models\ProductPharmacyView;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class ProductInfoViewJsonOptNoAct
 *
 *
 * @package App\Models
 */
class ProductInfoViewJsonOptNoAct extends Model
{
    protected $table = 'evo_product_info_view_json_opt_noact';

    public $timestamps = false;

	protected $casts = [
		'product_charachters' => 'array',
        'promocodes_json' => 'array',
	];

    public function productPharmacies(): HasMany
    {
        return $this->hasMany(ProductPharmacyView::class, 'product_id', 'product_id');
    }

}