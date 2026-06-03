CREATE OR REPLACE VIEW evo_product_info_view_json_opt AS
SELECT

    pc.product_id,

    pc.brand,
    pc.country,

    pc.form,
    pc.release_form,

    pc.product_price_from,
    pc.product_price_from_old,
    pc.product_price_from_percent,

    pc.delivery,

    pc.is_recipe,
    pc.is_alcohol,

    pc.is_available,

    pac.action_json,

    pcc.promocodes_json,

    cjc.categories_json,

    JSON_OBJECT(
        'product_id', pc.product_id,
        'name', pc.pagetitle,
        'image', pc.image
        ) AS product_charachters

FROM product_cache pc

         LEFT JOIN product_action_json_cache pac
                   ON pac.product_id = pc.product_id

         LEFT JOIN product_promocode_json_cache pcc
                   ON pcc.product_id = pc.product_id

         LEFT JOIN category_json_cache cjc
                   ON cjc.product_id = pc.product_id;
