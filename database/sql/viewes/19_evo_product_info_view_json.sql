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
    pac.action_json,
    ppc.promocodes_json,
    cjc.categories_json
from evo_products_view epv
         left join product_action_json_cache pac on (
    epv.product_id = pac.product_id
    )
         left join product_promocode_json_cache ppc on (
    epv.product_id = ppc.product_id
    )
         left join category_json_cache cjc on (
    epv.product_id = cjc.product_id
    );
