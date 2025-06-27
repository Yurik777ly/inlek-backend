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
class EvoCommerceOrders extends Model
{
	protected $table = 'evo_commerce_orders';

	protected $hidden = [
		'id',
	];

}
