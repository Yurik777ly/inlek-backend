<?php

namespace App\Marketing\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingCondition extends BaseMarketingModel
{
    protected $table = 'marketing_conditions';

    protected $fillable = [
        'external_uuid',

        'name',

        'time_from',
        'time_to',

        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',

        'amount_from',
        'amount_to',

        'segment_external_id',

        'expiration_date_from',
        'expiration_date_to',

        'summa_from',
        'summa_to',

        'summa_sale_from',
        'summa_sale_to',

        'summa_check_from',
        'summa_check_to',

        'price_from',
        'price_to',

        'condition_time',
        'condition_day',
        'condition_amount',
        'condition_goods',
        'condition_expiration_date',
        'condition_summa',
        'condition_summa_sale',
        'condition_summa_check',
        'condition_price',

        'raw_data',
    ];

    protected $casts = [

        'time_from' => 'datetime:H:i:s',
        'time_to'   => 'datetime:H:i:s',

        'monday'    => 'boolean',
        'tuesday'   => 'boolean',
        'wednesday' => 'boolean',
        'thursday'  => 'boolean',
        'friday'    => 'boolean',
        'saturday'  => 'boolean',
        'sunday'    => 'boolean',

        'condition_time'              => 'boolean',
        'condition_day'               => 'boolean',
        'condition_amount'            => 'boolean',
        'condition_goods'             => 'boolean',
        'condition_expiration_date'   => 'boolean',
        'condition_summa'             => 'boolean',
        'condition_summa_sale'        => 'boolean',
        'condition_summa_check'       => 'boolean',
        'condition_price'             => 'boolean',

        'raw_data' => 'array',
    ];

    /**
     * Все элементы ММ, использующие данное условие.
     */
    public function items(): HasMany
    {
        return $this->hasMany(
            MarketingItem::class,
            'condition_uuid',
            'external_uuid'
        );
    }

    /**
     * Условие не содержит ограничений.
     */
    public function isEmpty(): bool
    {
        return !(
            $this->condition_time ||
            $this->condition_day ||
            $this->condition_amount ||
            $this->condition_goods ||
            $this->condition_expiration_date ||
            $this->condition_summa ||
            $this->condition_summa_sale ||
            $this->condition_summa_check ||
            $this->condition_price
        );
    }

    /**
     * Используется ли проверка количества.
     */
    public function usesAmount(): bool
    {
        return $this->condition_amount;
    }

    /**
     * Используется ли проверка времени.
     */
    public function usesTime(): bool
    {
        return $this->condition_time;
    }

    /**
     * Используется ли проверка дней недели.
     */
    public function usesDay(): bool
    {
        return $this->condition_day;
    }

    /**
     * Используется ли проверка суммы.
     */
    public function usesSum(): bool
    {
        return $this->condition_summa;
    }

    /**
     * Используется ли проверка суммы чека.
     */
    public function usesCheckSum(): bool
    {
        return $this->condition_summa_check;
    }

    /**
     * Используется ли проверка цены.
     */
    public function usesPrice(): bool
    {
        return $this->condition_price;
    }

    /**
     * Используется ли проверка срока годности.
     */
    public function usesExpirationDate(): bool
    {
        return $this->condition_expiration_date;
    }

    /**
     * Используется ли проверка товаров/сегментов.
     */
    public function usesGoods(): bool
    {
        return $this->condition_goods;
    }
}
