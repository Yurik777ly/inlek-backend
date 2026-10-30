<?php

namespace App\Marketing\Models;

use Illuminate\Database\Eloquent\Model;

abstract class BaseMarketingModel extends Model
{
    /**
     * Все маркетинговые модели используют timestamps.
     */
    public $timestamps = true;

    /**
     * Защиту через fillable определяем в дочерних моделях.
     */
    protected $guarded = [];

    /**
     * Общие преобразования типов.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
