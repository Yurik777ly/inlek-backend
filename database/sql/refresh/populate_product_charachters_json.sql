-- Запускать после наполнения product_cache (cron / после обмена 1С).
-- Снимает сборку JSON_OBJECT с read-path views.

UPDATE product_cache pc
SET product_charachters_json = JSON_OBJECT(
    'product_id', pc.product_id,
    'pagetitle', pc.pagetitle,
    'parent', pc.parent,
    'product_description', pc.product_description,
    'instruction', pc.instruction,
    'mnn', pc.mnn,
    'mnn_lat', pc.mnn_lat,
    'code', pc.code,
    'brand', pc.brand,
    'country', pc.country,
    'form', pc.form,
    'release_form', pc.release_form,
    'termin', pc.termin,
    'temperature', pc.temperature,
    'image', pc.image,
    'dose', pc.dose,
    'recipe', pc.recipe,
    'is_recipe', pc.is_recipe,
    'is_alcohol', pc.is_alcohol,
    'product_insert', pc.product_insert,
    'product_time_register', pc.product_time_register,
    'product_register', pc.product_register,
    'product_date_register', pc.product_date_register,
    'product_trademark', pc.product_trademark,
    'product_price_from', pc.product_price_from,
    'product_price_from_old', pc.product_price_from_old,
    'product_price_from_percent', pc.product_price_from_percent,
    'product_sticker', pc.product_sticker,
    'delivery', pc.delivery
);
