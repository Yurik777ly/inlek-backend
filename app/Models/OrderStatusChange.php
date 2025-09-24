<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusChange extends Model
{
   protected $table = "order_status_changes";

   	protected $fillable = [
		"old_status_id",
        "new_statu_id",
        "order_id",
        "user_id",
	];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
