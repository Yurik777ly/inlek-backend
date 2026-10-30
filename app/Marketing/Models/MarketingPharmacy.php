<?php

namespace App\Marketing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingPharmacy extends BaseMarketingModel
{
    protected $table = 'marketing_pharmacies';

    protected $fillable = [
        'marketing_event_id',
        'pharmacy_uid',
        'is_group',
        'date_from',
        'raw_data',
    ];

    protected $casts = [
        'is_group' => 'boolean',
        'date_from' => 'datetime',
        'raw_data' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(
            MarketingEvent::class,
            'marketing_event_id'
        );
    }

    public function isGroup(): bool
    {
        return $this->is_group;
    }

    public function isPharmacy(): bool
    {
        return !$this->is_group;
    }
}
