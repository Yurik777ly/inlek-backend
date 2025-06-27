CREATE OR REPLACE VIEW evo_cart_product_pharmacy_view AS
WITH
    user_cart_coords AS (
        SELECT
            c.id AS cart_id,
            c.user_id,
            c.geo_lat,
            c.geo_long
        FROM carts c
    ),

    pharmacy_base AS (
        SELECT
            uc.user_id,
            cesc.cart_id,
            cesc.evo_site_content_id AS product_id,
            cesc.quantity AS required_quantity,
            eppv.pharmacy_id,
            eppv.pharmacy_name,
            eppv.coordinates,
            eppv.schedule,
            eppv.address,
            eppv.stock_count,
            eppv.price,
            eppv.price_old,
            IF(eppv.stock_count >= cesc.quantity, 'full', 'part') AS availability,
            CASE
                WHEN uc.geo_lat = '' OR uc.geo_long = '' THEN 0
                ELSE ROUND(
                        ST_Distance_Sphere(
                           POINT(uc.geo_long, uc.geo_lat),
                           POINT(
                                   CAST(SUBSTRING_INDEX(eppv.coordinates, ',', -1) AS DECIMAL(10,6)),
                                   CAST(SUBSTRING_INDEX(eppv.coordinates, ',', 1) AS DECIMAL(10,6))
                           )
                        )
                    )
           END AS distance_meters
           FROM evo_product_pharmacy_view eppv
           JOIN cart_evo_site_content cesc ON eppv.product_id = cesc.evo_site_content_id
           JOIN user_cart_coords uc ON cesc.cart_id = uc.cart_id
           WHERE eppv.stock_count > 0
    ),

sorted_pharmacies AS (
    SELECT
        pb.*,
        ROW_NUMBER() OVER (
            PARTITION BY pb.cart_id, pb.product_id
            ORDER BY pb.distance_meters ASC,
            CASE pb.availability
                WHEN 'full' THEN 0
                WHEN 'part' THEN 1
                ELSE 2
            END ASC
        ) AS pharmacy_rank
    FROM pharmacy_base pb
),

                pharmacy_groups AS (
    SELECT
        cart_id,
        user_id,
        product_id,
        required_quantity,
        JSON_ARRAYAGG(
            JSON_OBJECT(
                'pharmacy_id', pharmacy_id,
                'pharmacy_name', pharmacy_name,
                'coordinates', coordinates,
                'schedule', schedule,
                'address', address,
                'stock_count', stock_count,
                'availability', availability,
                'distance_meters', distance_meters,
                'price', price,
                'price_old', GREATEST(price_old, price)
            )
        ) AS pharmacies
    FROM sorted_pharmacies
    GROUP BY cart_id, user_id, product_id, required_quantity
)

SELECT
    pg.cart_id,
    pg.user_id,
    JSON_ARRAYAGG(
            JSON_OBJECT(
                    'product_id', pg.product_id,
                    'required_quantity', pg.required_quantity,
                    'pharmacies', pg.pharmacies
            )
    ) AS cart
FROM pharmacy_groups pg
GROUP BY pg.cart_id, pg.user_id;