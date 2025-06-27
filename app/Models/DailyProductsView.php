<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProductInfoViewJson;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class ProductActionView
 *
 *
 * @package App\Models
 */
class DailyProductsView extends Model
{
    protected $table = 'evo_daily_products_view';

    public $timestamps = false;

    public function productInfo(): BelongsTo
    {
        return $this->belongsTo(ProductInfoViewJson::class, 'product_id', 'product_id');
    }
}