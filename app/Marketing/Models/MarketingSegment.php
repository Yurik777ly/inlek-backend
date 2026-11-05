<?php

namespace App\Marketing\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingSegment extends BaseMarketingModel
{
    protected $table = 'evo_marketing_segments';

    protected $fillable = [
        'external_id',
        'name',
        'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(
            MarketingSegmentProduct::class,
            'segment_id'
        );
    }
}
