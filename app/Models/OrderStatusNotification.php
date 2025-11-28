<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusNotification extends Model
{

   public $timestamps = false;

   protected $table = "order_status_notifications";

   	protected $fillable = [
		"text",
        "status_id"
	];


}
