<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Models\EVO\EvoSiteTmplvarContentvalue;
use App\Models\ProductPharmacyView;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class ProductInfoViewJson
 *
 * @property int $product_id
 * @property string $pagetitle
 * @property string $alias
 * @property string $content
 * @property string $menutitle
 * @property int $pub_date
 * @property int $parent
 * @property int $create_dttm_raw
 * @property Carbon $create_dttm
 * @property int $published_dttm_raw
 * @property Carbon $published_dttm
 * @property int $edited_dttm_raw
 * @property Carbon $edited_dttm
 * @property int $published
 * @property string $product_description
 * @property string $instruction
 * @property string $mnn
 * @property string $mnn_lat
 * @property string $code
 * @property string $brand_j
 * @property string $country_j
 * @property string $form_j
 * @property string $release_form_j
 * @property string $termin
 * @property string $temperature
 * @property string $image
 * @property string $dose
 * @property string $recipe_title
 * @property string $product_insert
 * @property string $product_time_register
 * @property string $product_register
 * @property string $product_date_register
 * @property string $product_trademark
 * @property string $product_price_from
 * @property string $product_price_from_old
 * @property string $product_price_from_percent
 * @property string $product_sticker
 * @property string $is_alcohol
 * @property string $brand
 * @property string $country
 * @property string $release_form
 * @property string $recipe
 * @property string $form
 * @property string $delivery
 * @property int $is_available
 * @property array $promocodes_json
 * @property array $action_json
 * @property array $product_charachters
 * @property array $categories_json
 *
 * @package App\Models
 */
class ProductInfoViewJson extends Model
{
    protected $table = 'evo_product_info_view_json_opt';
    protected $primaryKey = 'product_id';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'product_id',
        'pagetitle',
        'alias',
        'content',
        'menutitle',
        'pub_date',
        'parent',
        'published',
        'product_description',
        'instruction',
        'mnn',
        'mnn_lat',
        'code',
        'brand',
        'country',
        'form',
        'release_form',
        'termin',
        'temperature',
        'image',
        'dose',
        'recipe_title',
        'recipe',
        'product_price_from',
        'product_price_from_old',
        'product_price_from_percent',
        'product_sticker',
        'is_alcohol',
        'delivery',
        'is_available',
    ];

    protected $casts = [
        'product_charachters' => 'array',
        'categories_json' => 'array',
        'promocodes_json' => 'array',
        'action_json' => 'array',
        'create_dttm' => 'datetime',
        'published_dttm' => 'datetime',
        'edited_dttm' => 'datetime',
        'pub_date' => 'date',
        'product_price_from' => 'decimal:2',
        'product_price_from_old' => 'decimal:2',
        'product_price_from_percent' => 'decimal:2',
        'is_available' => 'boolean',
        'published' => 'boolean',
    ];

    protected $hidden = [
        'create_dttm_raw',
        'published_dttm_raw',
        'edited_dttm_raw',
    ];

    /**
     * Связь с аптеками, где доступен товар
     */
    public function productPharmacies(): HasMany
    {
        return $this->hasMany(ProductPharmacyView::class, 'product_id', 'product_id');
    }

    /**
     * Связь с контент-значениями
     */
    public function contentValues(): HasMany
    {
        return $this->hasMany(EvoSiteTmplvarContentvalue::class, 'contentid', 'product_id');
    }

    /**
     * Скоуп для получения только опубликованных товаров
     */
    public function scopePublished($query)
    {
        return $query->where('published', 1);
    }

    /**
     * Скоуп для получения только доступных товаров
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', 1);
    }

    /**
     * Скоуп для товаров с доставкой
     */
    public function scopeWithDelivery($query)
    {
        return $query->where('delivery', 'Доставка');
    }

    /**
     * Скоуп для товаров с промокодами
     */
    public function scopeWithPromocodes($query)
    {
        return $query->whereNotNull('promocodes_json');
    }

    /**
     * Скоуп для товаров с акциями
     */
    public function scopeWithActions($query)
    {
        return $query->whereNotNull('action_json');
    }

    /**
     * Accessor для получения изображения товара
     */
    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }

        // Добавляем базовый URL если нужно
        if (!str_starts_with($this->image, 'http')) {
            return asset($this->image);
        }

        return $this->image;
    }

    /**
     * Accessor для проверки наличия скидки
     */
    public function getHasDiscountAttribute(): bool
    {
        return !empty($this->product_price_from_old) &&
               (float)$this->product_price_from_old > (float)$this->product_price_from;
    }

    /**
     * Accessor для получения размера скидки
     */
    public function getDiscountAmountAttribute(): float
    {
        if (!$this->has_discount) {
            return 0;
        }

        return (float)$this->product_price_from_old - (float)$this->product_price_from;
    }

    /**
     * Accessor для проверки рецептурности
     */
    public function getIsRecipeAttribute(): bool
    {
        return in_array($this->recipe_title, ['Рецептурный', 'Рецепт урный']);
    }

    /**
     * Accessor для получения активных промокодов
     */
    public function getActivePromocodesAttribute(): array
    {
        if (empty($this->promocodes_json)) {
            return [];
        }

        $now = now();
        return array_filter($this->promocodes_json, function($promocode) use ($now) {
            $begin = isset($promocode['begin']) ? Carbon::parse($promocode['begin']) : null;
            $end = isset($promocode['end']) ? Carbon::parse($promocode['end']) : null;

            return (!$begin || $now->gte($begin)) && (!$end || $now->lte($end));
        });
    }
}