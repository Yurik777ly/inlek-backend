<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class ActionView
 *
 * @package App\Models
 */
class ActionView extends Model
{
    protected $table = 'evo_actions_view';

    public $timestamps = false;

    protected $casts = [
        'action_products' => 'array',
    ];
}
