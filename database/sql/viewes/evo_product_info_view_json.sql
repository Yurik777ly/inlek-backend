create or replace view evo_product_info_view_json as
select
    epv.*,
    json_object(
            'product_id', epv.product_id,
            'pagetitle', epv.pagetitle,
            'parent', epv.parent,
            'product_description', epv.product_description,
            'instruction', epv.instruction,
            'mnn', epv.mnn,
            'mnn_lat', epv.mnn_lat,
            'code', epv.code,
            'brand', epv.brand,
            'country', epv.country,
            'form', epv.form,
            'release_form', epv.release_form,
            'termin', epv.termin,
            'temperature', epv.temperature,
            'image', epv.image,
            'dose', epv.dose,
            'recipe', epv.recipe,
            'is_recipe', epv.is_recipe,
            'is_alcohol', epv.is_alcohol,
            'product_insert', epv.product_insert,
            'product_time_register', epv.product_time_register,
            'product_register', epv.product_register,
            'product_date_register', epv.product_date_register,
            'product_trademark', epv.product_trademark,
            'product_price_from', epv.product_price_from,
            'product_price_from_old', epv.product_price_from_old,
            'product_price_from_percent', epv.product_price_from_percent,
            'product_sticker', epv.product_sticker,
            'delivery', epv.delivery
    ) as product_charachters,
    epavj.action_json,
    eppvj.promocodes_json,
    epcj.categories_json
from evo_products_view epv
         left join evo_product_action_view_json epavj on (
    epv.product_id = epavj.product_id
    )
         left join evo_product_promocodes_view_json eppvj on (
    epv.product_id = eppvj.product_id
    )
         left join evo_product_categories_json epcj on (
    epv.product_id = epcj.product_id
    );