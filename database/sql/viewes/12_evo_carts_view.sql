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
                                   SELECT epiv.product_charachters
                                   FROM evo_product_info_view_json epiv
                                   WHERE epiv.product_id = cesc.evo_site_content_id
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
