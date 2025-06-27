<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class CategoryView
 *
 * @package App\Models
 */
class CartsView extends Model
{
    protected $table = 'evo_carts_view';

    public $timestamps = false;

    protected $casts = [
		'cart' => 'array',
        'product_info' => 'array',
	];
}
