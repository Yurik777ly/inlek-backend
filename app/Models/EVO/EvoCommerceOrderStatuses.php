<?php

namespace App\Models\EVO;

use Illuminate\Database\Eloquent\Model;

/**
 * Class EvoCommerceOrderStatuses
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
class EvoCommerceOrderStatuses extends Model
{
	protected $table = 'evo_commerce_order_statuses';
	public $timestamps = false;

	protected $hidden = [
		'id',
		'alias',
        'default'
//		'value'
	];

}
