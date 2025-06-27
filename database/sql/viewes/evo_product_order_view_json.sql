create or replace view evo_product_order_view_json as
select
    order_id,
    json_arrayagg(
            json_object(
                    'product_id', product_id,
                    'pharmacy_id', pharmacy_id,
                    'product_title', product_title,
                    'price', price,
                    'pharmacy_price', pharmacy_price,
                    'position', position,
                    'count', count,
                    'alias', alias,
                    'mnn', mnn,
                    'code', code,
                    'brand', brand,
                    'country', country,
                    'release_form', release_form,
                    'termin', termin,
                    'temperature', temperature,
                    'image', image,
                    'dose', dose,
                    'recipe', recipe,
                    'is_recipe', is_recipe,
                    'is_alcohol', is_alcohol,
                    'product_insert', product_insert,
                    'product_time_register', product_time_register,
                    'product_register', product_register,
                    'product_date_register', product_date_register,
                    'product_trademark', product_trademark,
                    'product_sticker', product_sticker
            )
    ) product_json
from evo_product_order_view
group by order_id;