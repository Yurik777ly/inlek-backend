<?php

namespace App\Models\EVO;

use Illuminate\Database\Eloquent\Model;

/**
 * Class EvoCommerceOrders
 *
 * @property int $id
 * @property int $notify
 * @property int $default
 * @property int $canbepaid
 * @property string|null $title
 * @property string|null $color
 *
 * @package App\Models
 */
class EvoCommerceOrderProducts extends Model
{

	public $timestamps = false;
	protected $table = 'evo_commerce_order_products';

	protected $fillable = [
                'product_id',
                'order_id',
                'title',
                'price',
                'count',
                'options',
                'meta',
                'position',
    ];

	protected $hidden = [
		'id',
	];

}
