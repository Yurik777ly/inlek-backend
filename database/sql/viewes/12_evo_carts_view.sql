CREATE OR REPLACE VIEW evo_carts_view AS
SELECT
    c.user_id,
    JSON_OBJECT(
            'cart_id', c.id,
            'user_id', c.user_id,
            'cart_created_at', c.created_at,
            'cart_updated_at', c.updated_at
    ) AS cart,
    (
        SELECT JSON_ARRAYAGG(
                       JSON_OBJECT(
                               'quantity', cesc.quantity,
                               'product_charachters', (
                                   SELECT JSON_OBJECT(
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
                                          )
                                   FROM evo_products_view epv
                                   WHERE epv.product_id = cesc.evo_site_content_id
                                   LIMIT 1
                               ),
                               'promocodes_json', (
                                    SELECT ppc.promocodes_json
                                    FROM product_promocode_json_cache ppc
                                    WHERE ppc.product_id = cesc.evo_site_content_id
                                    LIMIT 1
                                ),
                                'action_json', (
                                    SELECT pac.action_json
                                    FROM product_action_json_cache pac
                                    WHERE pac.product_id = cesc.evo_site_content_id
                                    LIMIT 1
                                )
                       )
               )
        FROM cart_evo_site_content cesc
        WHERE cesc.cart_id = c.id
    ) AS product_info
FROM carts c;
