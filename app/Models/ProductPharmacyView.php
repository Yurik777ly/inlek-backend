<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Models\EVO\EvoSiteTmplvarContentvalue;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class ProductPharmacyView
 *
 *
 * @package App\Models
 */
class ProductPharmacyView extends Model
{
    protected $table = 'evo_product_pharmacy_view';

    public $timestamps = false;

    public function productInfo(): BelongsTo
    {
        return $this->belongsTo(ProductInfoViewJson::class, 'product_id', 'product_id');
    }

	// protected $casts = [
	// 	'product_charachters' => 'array',
    //     'categories_json' => 'array',
    //     'promocodes_json' => 'array',
    //     'action_json' => 'array',
	// ];

}