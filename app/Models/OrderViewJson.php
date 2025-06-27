<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Models\EVO\EvoSiteTmplvarContentvalue;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class ProductCategoryView
 *
 *
 * @package App\Models
 */
class OrderViewJson extends Model
{
    protected $table = 'evo_order_view_json';

    public $timestamps = false;

    protected $casts = [
		'order_products_json' => 'array',
	];
}
