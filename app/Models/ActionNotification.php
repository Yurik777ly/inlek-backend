<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionNotification extends Model
{
   protected $table = "actions_notifications";

   	protected $fillable = [
		"action_id",
        "pub_date",
        "sent"
	];

    public function action()
    {
        return $this->belongsTo(ActionView::class, 'action_id', 'action_id');
    }
}
