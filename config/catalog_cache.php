<?php

use App\Models\PharmaciesView;

return [
    'delivery_pharmacy_id' => (int) env('CATALOG_DELIVERY_PHARMACY_ID', PharmaciesView::PHARMACY_ID_FOR_DELIVERY),

    'templates' => [
        'product' => (int) env('CATALOG_TEMPLATE_PRODUCT', 5),
        'category' => (int) env('CATALOG_TEMPLATE_CATEGORY', 2),
        'action' => (int) env('CATALOG_TEMPLATE_ACTION', 12),
    ],

    'recipe_values' => ['Рецептурный', 'Рецепт урный'],

    /*
     * ID полей MODX (evo_site_tmplvar_contentvalues.tmplvarid) для товаров.
     */
    'product_tmplvars' => [
        'image' => 5,
        'product_json' => 15,
        'dose' => 91,
        'recipe' => 112,
        'product_insert' => 90,
        'product_time_register' => 93,
        'product_register' => 95,
        'product_date_register' => 96,
        'product_trademark' => 102,
        'product_price_from' => 7,
        'product_price_from_old' => 8,
        'product_price_from_percent' => 9,
        'product_sticker' => 14,
        'is_alcohol' => 119,
        'mnn_lat' => 88,
        'product_description' => 23,
        'instruction' => 22,
        'brand' => 101,
        'country' => 75,
        'release_form' => 89,
        'form' => 78,
    ],

    /*
     * Имена TV-полей аптек (evo_site_tmplvars.name).
     */
    'pharmacy_tmplvar_names' => [
        'address' => ['pharmacy_address', 'address', 'adres'],
        'coordinates' => ['pharmacy_coordinates', 'coordinates', 'coords', 'map'],
        'schedule' => ['pharmacy_work_time', 'schedule', 'work_time', 'working_hours'],
        'image' => ['pharmacy_image', 'image', 'photo'],
    ],

    'category_tmplvar_ids' => [
        'image_primary' => 1,
        'image_secondary' => 2,
    ],

    'action_goods_tmplvar_id' => 35,

    'schedule' => [
        'enabled' => (bool) env('CATALOG_CACHE_SCHEDULE_ENABLED', false),
        'cron' => env('CATALOG_CACHE_SCHEDULE_CRON', '30 4 * * *'),
    ],

    /*
     * Инкрементальное обновление offers / promocodes (catalog:refresh-cache --incremental).
     * overlap_seconds — запас при чтении watermark, чтобы не пропустить строки на границе запусков.
     */
    'incremental' => [
        'overlap_seconds' => (int) env('CATALOG_CACHE_INCREMENTAL_OVERLAP', 300),
    ],
];
