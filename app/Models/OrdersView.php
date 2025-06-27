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
class OrdersView extends Model
{
    protected $table = 'evo_category_product_view';

    public $timestamps = false;
}
