<?php

namespace App\Models\EVO;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

	public function status(): BelongsTo
	{
		return $this->belongsTo(EvoCommerceOrderStatuses::class, 'status_id');
	}

	public function products(): HasMany
    {
		return $this->hasMany(EvoCommerceOrderProducts::class, 'order_id');
	}
}
