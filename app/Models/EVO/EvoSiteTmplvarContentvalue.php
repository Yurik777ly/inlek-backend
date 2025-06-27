<?php

namespace App\Models\EVO;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\EVO\EvoSiteTmplvar;

/**
 * Class EvoSiteTmplvarContentvalue
 * 
 * @property int $id
 * @property int $tmplvarid
 * @property int $contentid
 * @property string|null $value
 *
 * @package App\Models
 */
class EvoSiteTmplvarContentvalue extends Model
{
	protected $table = 'evo_site_tmplvar_contentvalues';
	public $timestamps = false;

	protected $casts = [
		'tmplvarid' => 'int',
		'contentid' => 'int'
	];

	protected $hidden = [
		'id',
		'contentid',
//		'value'
	];

    public function tmplvar(): HasOne
    {
        return $this->hasOne(EvoSiteTmplvar::class, 'id', 'tmplvarid');
    }
}
