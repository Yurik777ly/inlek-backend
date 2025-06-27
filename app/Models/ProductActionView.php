<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Models\EVO\EvoSiteTmplvarContentvalue;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class ProductActionView
 *
 *
 * @package App\Models
 */
class ProductActionView extends Model
{
    protected $table = 'evo_product_action_view';

    public $timestamps = false;

    public function contentvalues(): HasMany
    {
        return $this->hasMany(EvoSiteTmplvarContentvalue::class, 'contentid', 'product_id');
    }
}
