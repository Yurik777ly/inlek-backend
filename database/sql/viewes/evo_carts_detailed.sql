CREATE OR REPLACE VIEW evo_carts_detailed AS
WITH RECURSIVE
    user_cart AS (
        SELECT id, user_id, pharmacy_id, promocodes, created_at, updated_at, delivery_zone
        FROM carts
    ),

    cart_items AS (
        SELECT
            c.id AS cart_id,
            c.user_id,
            c.pharmacy_id,
            c.promocodes AS cart_promocodes,
            c.created_at AS cart_created_at,
            c.updated_at AS cart_updated_at,
            c.delivery_zone,
            cesc.evo_site_content_id AS product_id,
            cesc.quantity AS requested_quantity, -- Сохраняем исходное количество из корзины
            -- quantity не может быть больше stock_count
            CAST(LEAST(cesc.quantity, COALESCE(eppv.stock_count, 0)) AS UNSIGNED) AS quantity,
            COALESCE(eod.price, 0) AS price,
            CASE 
                WHEN COALESCE(eod.price_old, 0) = 0 THEN COALESCE(eod.price, 0) 
                ELSE COALESCE(eod.price_old, 0) 
            END AS price_old,
            COALESCE(eppv.stock_count, 0) AS stock_count
        FROM user_cart c
                 JOIN cart_evo_site_content cesc ON c.id = cesc.cart_id
                 LEFT JOIN evo_offers eod ON cesc.evo_site_content_id = eod.product_id
            AND eod.pharmacy_id = c.pharmacy_id
                 LEFT JOIN evo_product_pharmacy_view eppv ON cesc.evo_site_content_id = eppv.product_id
            AND c.pharmacy_id = eppv.pharmacy_id
    ),
    -- Рекурсивно разбиваем строку промокодов
    split_promocodes AS (
        SELECT
            cart_id,
            TRIM(SUBSTRING_INDEX(cart_promocodes, '|', 1)) COLLATE utf8mb4_unicode_ci AS promo,
            IF(LOCATE('|', cart_promocodes) > 0,
               SUBSTRING(cart_promocodes, LOCATE('|', cart_promocodes) + 1),
               NULL) AS remaining_promos
        FROM cart_items
        WHERE cart_promocodes IS NOT NULL AND cart_promocodes != ''

        UNION ALL

        SELECT
            cart_id,
            TRIM(SUBSTRING_INDEX(remaining_promos, '|', 1)) COLLATE utf8mb4_unicode_ci AS promo,
            IF(LOCATE('|', remaining_promos) > 0,
               SUBSTRING(remaining_promos, LOCATE('|', remaining_promos) + 1),
               NULL) AS remaining_promos
        FROM split_promocodes
        WHERE remaining_promos IS NOT NULL
    ),

    cart_applied_promocodes AS (
        SELECT DISTINCT cart_id, promo
        FROM split_promocodes
        WHERE promo != ''
    ),

    product_promocodes AS (
        SELECT
            ci.cart_id,
            ci.product_id,
            ep.id AS promocode_id,
            ep.promocode COLLATE utf8mb4_unicode_ci AS promocode_name,
            ep.discount
        FROM cart_items ci
                 JOIN evo_promocodes_links epl ON epl.link = ci.product_id
                 JOIN evo_promocodes ep ON ep.id = epl.pcid
        WHERE ep.begin < NOW()
          AND NOW() < ep.end
          AND ep.active = 1
          -- Используем requested_quantity для проверки минимального количества
          AND ci.requested_quantity >= ep.min_amount
    ),

    applied_promocodes AS (
        SELECT
            ci.cart_id,
            ci.product_id,
            pp.promocode_id,
            pp.promocode_name,
            pp.discount
        FROM cart_items ci
                 JOIN product_promocodes pp ON pp.cart_id = ci.cart_id AND pp.product_id = ci.product_id
                 JOIN cart_applied_promocodes cap ON cap.cart_id = ci.cart_id AND cap.promo = pp.promocode_name
    ),

    product_prices AS (
        SELECT
            ci.cart_id,
            ci.user_id,
            ci.pharmacy_id,
            ci.cart_created_at,
            ci.cart_updated_at,
            ci.cart_promocodes,
            ci.delivery_zone,
            ci.product_id,
            ci.quantity,
            ci.price,
            ci.price_old,
            ci.stock_count,
            ci.requested_quantity,
            CASE 
                WHEN ci.stock_count = 0 THEN 'absent'
                WHEN ci.stock_count >= ci.requested_quantity THEN 'full' -- используем requested_quantity для проверки доступности
                ELSE 'part'
            END AS availability,
            GREATEST(ci.price_old, ci.price) AS max_price,
            -- Цена с промокодами
            CASE
                WHEN EXISTS (SELECT 1 FROM applied_promocodes ap
                             WHERE ap.cart_id = ci.cart_id AND ap.product_id = ci.product_id)
                    THEN ci.price * (
                    SELECT EXP(SUM(LOG(1 - COALESCE(ap.discount, 0)/100)))
                    FROM applied_promocodes ap
                    WHERE ap.cart_id = ci.cart_id AND ap.product_id = ci.product_id
                )
                ELSE ci.price
                END AS final_price_with_promos,
            -- Скидка без промокодов
            GREATEST(ci.price_old, ci.price) - ci.price AS discount_without_promos,
            -- Скидка с промокодами
            CASE
                WHEN EXISTS (SELECT 1 FROM applied_promocodes ap
                             WHERE ap.cart_id = ci.cart_id AND ap.product_id = ci.product_id)
                    THEN GREATEST(ci.price_old, ci.price) -
                         (ci.price * (
                             SELECT EXP(SUM(LOG(1 - COALESCE(ap.discount, 0)/100)))
                             FROM applied_promocodes ap
                             WHERE ap.cart_id = ci.cart_id AND ap.product_id = ci.product_id
                         ))
                ELSE GREATEST(ci.price_old, ci.price) - ci.price
                END AS discount_with_promos,
            -- Примененные промокоды
            (
                SELECT IFNULL(JSON_ARRAYAGG(
                                      JSON_OBJECT(
                                              'promocode_id', ap.promocode_id,
                                              'promocode_name', ap.promocode_name,
                                              'discount', ap.discount
                                      )
                              ), JSON_ARRAY())
                FROM applied_promocodes ap
                WHERE ap.cart_id = ci.cart_id AND ap.product_id = ci.product_id
            ) AS applied_promocodes_json,
            -- Все доступные промокоды
            (
                SELECT IFNULL(JSON_ARRAYAGG(
                                      JSON_OBJECT(
                                              'promocode_id', pp.promocode_id,
                                              'promocode_name', pp.promocode_name,
                                              'discount', pp.discount
                                      )
                              ), JSON_ARRAY())
                FROM product_promocodes pp
                WHERE pp.cart_id = ci.cart_id AND pp.product_id = ci.product_id
            ) AS all_promocodes_json
        FROM cart_items ci
    ),

    unique_promocodes AS (
        SELECT
            cart_id,
            promocode_id,
            promocode_name,
            discount
        FROM product_promocodes
        GROUP BY cart_id, promocode_id, promocode_name, discount
    ),

    cart_summary AS (
        SELECT
            pp.cart_id,
            SUM(pp.price_old * pp.quantity) AS total_price_old,
            SUM(LEAST(pp.price, COALESCE(pp.final_price_with_promos, pp.price)) * pp.quantity) AS total_final_price,
            SUM(pp.discount_with_promos * pp.quantity) AS total_discount,
            SUM((pp.discount_with_promos - pp.discount_without_promos) * pp.quantity) AS discount_only_promos,
            MAX(pp.delivery_zone) AS delivery_zone,
            (
                SELECT IFNULL(JSON_ARRAYAGG(
                                      JSON_OBJECT(
                                              'promocode_id', up.promocode_id,
                                              'promocode_name', up.promocode_name,
                                              'discount', up.discount
                                      )
                              ), JSON_ARRAY())
                FROM unique_promocodes up
                WHERE up.cart_id = pp.cart_id
            ) AS all_promocodes_json
        FROM product_prices pp
        GROUP BY pp.cart_id
    )
SELECT
    pp.user_id,
    pp.pharmacy_id,
    JSON_OBJECT(
            'cart_id', pp.cart_id,
            'user_id', pp.user_id,
            'pharmacy', JSON_OBJECT(
                    'pharmacy_id', epv.pharmacy_id,
                    'pharmacy_name', epv.pagetitle,
                    'address', epv.address,
                    'schedule', epv.schedule,
                    'coordinates', epv.coordinates,
                    'image', epv.image
                        ),
            'cart_created_at', pp.cart_created_at,
            'cart_updated_at', pp.cart_updated_at,
            'entered_promocodes', pp.cart_promocodes,
            'all_promocodes', cs.all_promocodes_json,
            'totals', JSON_OBJECT(
                    'total_price_old', round(cs.total_price_old, 2),
                    'total_final_price', round(cs.total_final_price, 2),
                    'total_discount', round(cs.total_discount, 2),
                    'discount_only_promos', round(cs.discount_only_promos, 2),
                    'delivery_sum', CASE
                                        WHEN cs.delivery_zone = 'green' AND cs.total_final_price < 40 THEN 8
                                        WHEN cs.delivery_zone = 'green' AND cs.total_final_price >= 40 THEN 0
                                        WHEN cs.delivery_zone = 'yellow' THEN 8
                                        WHEN cs.delivery_zone IS NULL OR cs.delivery_zone = '' THEN 'ERROR'
                                        ELSE 'ERROR'
                        END
                      ),
            'products', (
                SELECT IFNULL(JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'product_id', grouped_products.product_id,
                        'quantity', CAST(grouped_products.quantity AS UNSIGNED),
                        'stock_count', grouped_products.stock_count,
                        'requested_quantity', CAST(grouped_products.requested_quantity AS UNSIGNED),
                        'availability', grouped_products.availability,
                        'product_info', grouped_products.product_info,
                        'prices', grouped_products.prices,
                        'product_totals', grouped_products.product_totals,
                        'action_json', grouped_products.action_json
                    )
                ), JSON_ARRAY())
                FROM (
                    SELECT 
                        pp2.product_id,
                        MAX(pp2.quantity) as quantity,
                        MAX(pp2.stock_count) as stock_count,
                        MAX(pp2.requested_quantity) as requested_quantity,
                        MAX(pp2.availability) as availability,
                        (
                            SELECT JSON_OBJECT(
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
                                'delivery', epv.delivery,
                                'other_pharmacy', (SELECT 1 as available FROM evo_offers WHERE epv.pharmacy_id <> evo_offers.pharmacy_id AND evo_offers.pharmacy_id <> 17599997 and pp2.product_id = evo_offers.product_id GROUP BY evo_offers.product_id)
                            )
                            FROM evo_products_view epv
                            WHERE epv.product_id = pp2.product_id
                            LIMIT 1
                        ) as product_info,
                        JSON_OBJECT(
                            'price', MAX(pp2.price),
                            'price_old', MAX(pp2.price_old),
                            'final_price', LEAST(MAX(pp2.price), COALESCE(MAX(pp2.final_price_with_promos), MAX(pp2.price))),
                            'final_price_with_promos', COALESCE(MAX(pp2.final_price_with_promos), MAX(pp2.price)),
                            'discount_without_promos', MAX(pp2.discount_without_promos),
                            'discount_with_promos', MAX(pp2.discount_with_promos),
                            'discount_only_promos', MAX(pp2.discount_with_promos) - MAX(pp2.discount_without_promos),
                            'applied_promocodes', MAX(pp2.applied_promocodes_json)
                        ) as prices,
                        JSON_OBJECT(
                            'total', MAX(pp2.price) * MAX(pp2.quantity),
                            'total_old', MAX(pp2.price_old) * MAX(pp2.quantity),
                            'final_total', LEAST(MAX(pp2.price), COALESCE(MAX(pp2.final_price_with_promos), MAX(pp2.price))) * MAX(pp2.quantity),
                            'final_total_with_promos', COALESCE(MAX(pp2.final_price_with_promos), MAX(pp2.price)) * MAX(pp2.quantity),
                            'total_discount_without_promos', MAX(pp2.discount_without_promos) * MAX(pp2.quantity),
                            'total_discount_with_promos', MAX(pp2.discount_with_promos) * MAX(pp2.quantity),
                            'total_discount_only_promos', (MAX(pp2.discount_with_promos) - MAX(pp2.discount_without_promos)) * MAX(pp2.quantity)
                        ) as product_totals,
                        (SELECT IFNULL(action_json, JSON_OBJECT()) FROM evo_product_action_view_json WHERE product_id = pp2.product_id LIMIT 1) as action_json
                    FROM product_prices pp2
                    WHERE pp2.cart_id = pp.cart_id
                    GROUP BY pp2.product_id
                ) AS grouped_products
            )
    ) AS cart
FROM product_prices pp
         JOIN cart_summary cs ON cs.cart_id = pp.cart_id
         JOIN evo_pharmacies_view epv on (pp.pharmacy_id = epv.pharmacy_id)
GROUP BY pp.cart_id, pp.user_id, pp.pharmacy_id, pp.cart_created_at, pp.cart_updated_at,
         pp.cart_promocodes, cs.all_promocodes_json, cs.total_price_old, cs.total_final_price,
         cs.total_discount, cs.discount_only_promos, cs.delivery_zone;