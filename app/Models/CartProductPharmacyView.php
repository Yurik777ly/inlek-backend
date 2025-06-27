<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class CategoryView
 *
 * @package App\Models
 */
class CartProductPharmacyView extends Model
{
    protected $table = 'evo_cart_product_pharmacy_view';

    public $timestamps = false;

    protected $casts = [
		'cart' => 'array',
	];
}
