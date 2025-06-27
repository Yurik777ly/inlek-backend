<?php

namespace App\Models\EVO;

use Illuminate\Database\Eloquent\Model;

/**
 * Class EvoSystemSetting
 * 
 * @property string $setting_name
 * @property string|null $setting_value
 *
 * @package App\Models
 */
class EvoSystemSetting extends Model
{
	protected $table = 'evo_system_settings';
	protected $primaryKey = 'setting_name';
	public $incrementing = false;
	public $timestamps = false;

	protected $fillable = [
		'setting_value'
	];
}
