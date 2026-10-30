<?php

namespace App\Marketing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingSegmentProduct extends BaseMarketingModel
{
    protected $table = 'marketing_segment_products';

    protected $fillable = [
        'segment_id',
        'product_id',
    ];

    protected $casts = [
        'segment_id' => 'integer',
        'product_id' => 'integer',
    ];

    public function segment(): BelongsTo
    {
        return $this->belongsTo(
            MarketingSegment::class,
            'segment_id'
        );
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeForSegment($query, int $segmentId)
    {
        return $query->where('segment_id', $segmentId);
    }
}
