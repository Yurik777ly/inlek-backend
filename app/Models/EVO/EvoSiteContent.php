<?php

namespace App\Models\EVO;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\User;
use App\Models\Cart;

/**
 * Class EvoSiteContent
 *
 * @property int $id
 * @property string $type
 * @property string $contentType
 * @property string $pagetitle
 * @property string $longtitle
 * @property string $description
 * @property string|null $alias
 * @property string $link_attributes
 * @property int $published
 * @property int $pub_date
 * @property int $unpub_date
 * @property int $parent
 * @property int $isfolder
 * @property string|null $introtext
 * @property string|null $content
 * @property bool $richtext
 * @property int $template
 * @property int $menuindex
 * @property int $searchable
 * @property int $cacheable
 * @property int $createdby
 * @property int $createdon
 * @property int $editedby
 * @property int $editedon
 * @property int $deleted
 * @property int $deletedon
 * @property int $deletedby
 * @property int $publishedon
 * @property int $publishedby
 * @property string $menutitle
 * @property bool $hide_from_tree
 * @property bool $privateweb
 * @property bool $privatemgr
 * @property bool $content_dispo
 * @property bool $hidemenu
 * @property int $alias_visible
 *
 * @package App\Models
 */
class EvoSiteContent extends Model
{
	protected $table = 'evo_site_content';
	public $timestamps = false;

	protected $casts = [
		'published' => 'int',
		'pub_date' => 'int',
		'unpub_date' => 'int',
		'parent' => 'int',
		'isfolder' => 'int',
		'richtext' => 'bool',
		'template' => 'int',
		'menuindex' => 'int',
		'searchable' => 'int',
		'cacheable' => 'int',
		'createdby' => 'int',
		'createdon' => 'int',
		'editedby' => 'int',
		'editedon' => 'int',
		'deleted' => 'int',
		'deletedon' => 'int',
		'deletedby' => 'int',
		'publishedon' => 'int',
		'publishedby' => 'int',
		'hide_from_tree' => 'bool',
		'privateweb' => 'bool',
		'privatemgr' => 'bool',
		'content_dispo' => 'bool',
		'hidemenu' => 'bool',
		'alias_visible' => 'int'
	];

	protected $fillable = [
		'type',
		'contentType',
		'pagetitle',
		'longtitle',
		'description',
		'alias',
		'link_attributes',
		'published',
		'pub_date',
		'unpub_date',
		'parent',
		'isfolder',
		'introtext',
		'content',
		'richtext',
		'template',
		'menuindex',
		'searchable',
		'cacheable',
		'createdby',
		'createdon',
		'editedby',
		'editedon',
		'deleted',
		'deletedon',
		'deletedby',
		'publishedon',
		'publishedby',
		'menutitle',
		'hide_from_tree',
		'privateweb',
		'privatemgr',
		'content_dispo',
		'hidemenu',
		'alias_visible'
	];

    public function carts():BelongsToMany
    {
        return $this->belongsToMany(Cart::class)->withPivot('quantity');
    }

	public function users():BelongsToMany
	{
		return $this->belongsToMany(
            User::class,
            'notificate_product_user',
            'product_id',
            'user_id');
	}
}
