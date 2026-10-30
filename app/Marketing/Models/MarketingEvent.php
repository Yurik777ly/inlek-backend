<?php

namespace App\Marketing\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingEvent extends BaseMarketingModel
{
    protected $table = 'marketing_events';

    protected $fillable = [
        'external_id',
        'name',
        'date_from',
        'date_to',
        'type',
        'type_of_selection',
        'type_of_discount',
        'provide_type',
        'is_ended',
        'no_use_on_site',
        'raw_data',
    ];

    protected $casts = [
        'date_from'      => 'datetime',
        'date_to'        => 'datetime',

        'is_ended'       => 'boolean',
        'no_use_on_site' => 'boolean',

        'raw_data'       => 'array',
    ];

    /**
     * Элементы маркетингового мероприятия.
     */
    public function items(): HasMany
    {
        return $this->hasMany(
            MarketingItem::class,
            'marketing_event_id'
        );
    }

    /**
     * Аптеки мероприятия.
     */
    public function pharmacies(): HasMany
    {
        return $this->hasMany(
            MarketingPharmacy::class,
            'marketing_event_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query
            ->where('is_ended', false)
            ->where('no_use_on_site', false)
            ->where('date_from', '<=', now())
            ->where('date_to', '>=', now());
    }

    public function scopeProducts($query)
    {
        return $query->where('type_of_selection', 2);
    }

    public function scopeSegments($query)
    {
        return $query->where('type_of_selection', 1);
    }

    public function scopeFixedPrice($query)
    {
        return $query->where('type_of_discount', 1);
    }

    public function scopePercentDiscount($query)
    {
        return $query->where('type_of_discount', 2);
    }

    public function isActive(): bool
    {
        if ($this->is_ended || $this->no_use_on_site) {
            return false;
        }

        // На сайте применяются только ММ с автоматическим предоставлением.
        if ((int) $this->provide_type !== 1) {
            return false;
        }

        $now = now();

        if ($this->date_from && $this->date_from->gt($now)) {
            return false;
        }

        if ($this->date_to && $this->date_to->lt($now)) {
            return false;
        }

        return true;
    }
}
