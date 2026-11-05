<?php

namespace App\Marketing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingItem extends BaseMarketingModel
{
    public const TARGET_PRODUCT = 1;
    public const TARGET_SEGMENT = 2;
    public const DISCOUNT_FIXED_PRICE = 1;
    public const DISCOUNT_PERCENT = 2;
    public const PROVIDE_AUTO = 1;
    public const PROVIDE_MANUAL = 2;
    public const PROVIDE_CARD = 3;
    protected $table = 'evo_marketing_items';

    protected $fillable = [
        'marketing_event_id',
        'target_type',
        'discount_type',
        'provide_type',
        'target_id',
        'condition_uuid',
        'value',
        'raw_data',
    ];

    protected $casts = [
        'target_type' => 'integer',
        'target_id' => 'integer',
        'value' => 'float',
        'raw_data' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(
            MarketingEvent::class,
            'marketing_event_id'
        );
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(
            MarketingCondition::class,
            'condition_uuid',
            'external_uuid'
        );
    }

    public function isProduct(): bool
    {
        return $this->target_type === self::TARGET_PRODUCT;
    }

    public function isSegment(): bool
    {
        return $this->target_type === self::TARGET_SEGMENT;
    }

    public function hasCondition(): bool
    {
        return !empty($this->condition_uuid);
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query
            ->where('target_type', self::TARGET_PRODUCT)
            ->where('target_id', $productId);
    }

    public function scopeForSegment($query, int $segmentId)
    {
        return $query
            ->where('target_type', self::TARGET_SEGMENT)
            ->where('target_id', $segmentId);
    }
}
