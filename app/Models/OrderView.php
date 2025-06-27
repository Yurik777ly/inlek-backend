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
class OrderView extends Model
{
    protected $table = 'evo_orders_view';

    public $timestamps = false;
}
