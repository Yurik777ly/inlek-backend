<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class CategoryView
 *
 * @package App\Models
 */
class CartsDetailed extends Model
{
    protected $table = 'evo_carts_detailed';

    public $timestamps = false;

    protected $casts = [
		'cart' => 'array',
	];
}
