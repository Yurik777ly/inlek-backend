<?php

namespace App\Models\EVO;

use Illuminate\Database\Eloquent\Model;

/**
 * Class EvoSiteTmplvar
 * 
 * @property int $id
 * @property string $type
 * @property string $name
 * @property string $caption
 * @property string $description
 * @property int $editor_type
 * @property int $category
 * @property bool $locked
 * @property string|null $elements
 * @property int $rank
 * @property string|null $display
 * @property string|null $display_params
 * @property string|null $default_text
 * @property int $createdon
 * @property int $editedon
 * @property string|null $properties
 *
 * @package App\Models
 */
class EvoSiteTmplvar extends Model
{
	protected $table = 'evo_site_tmplvars';
	public $timestamps = false;

	protected $casts = [
		'editor_type' => 'int',
		'category' => 'int',
		'locked' => 'bool',
		'rank' => 'int',
		'createdon' => 'int',
		'editedon' => 'int'
	];

	protected $fillable = [
		'type',
		'name',
		'caption',
		'description',
		'editor_type',
		'category',
		'locked',
		'elements',
		'rank',
		'display',
		'display_params',
		'default_text',
		'createdon',
		'editedon',
		'properties'
	];
}
