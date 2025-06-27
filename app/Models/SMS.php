<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Sms
 *
 * @property int $id
 * @property string $phone
 * @property Carbon|null $last_sms_requested_at
 * @property int $sms_requested_qty
 * @property int $code
 * @property bool $is_confirmed
 *
 * @package App\Models
 */
class SMS extends Model
{
    protected $table = 'sms';

    public $timestamps = false;

    protected $casts = [
	    'last_sms_requested_at' => 'datetime',
    	'sms_requested_qty' => 'int',
	    'code' => 'int',
    	'is_confirmed' => 'bool'
    ];

    protected $fillable = [
        'phone',
        'last_sms_requested_at',
        'sms_requested_qty',
        'code',
        'is_confirmed',
    ];
}
