CREATE OR REPLACE VIEW evo_product_pharmacy_json AS
WITH user_cart AS (
    SELECT
        user_id,
        geo_lat,
        geo_long
    FROM carts
    WHERE user_id IS NOT NULL
    ORDER BY updated_at DESC
    LIMIT 1
),
     product_pharmacy_data AS (
         SELECT
             epp.product_id,
             epp.product_name,
             epp.pharmacy_id,
             epp.price,
             epp.price_old,
             epp.stock_count,
             epp.expiration_date,
             epp.pharmacy_name,
             epp.pharmacy_alias,
             epp.pharmacy_delivery,
             epp.recipe,
             epp.is_recipe,
             epp.is_alcohol,
             epv.address,
             epv.coordinates,
             epv.schedule,
             uc.user_id,
             CASE
                 WHEN uc.geo_lat = '' OR uc.geo_long = '' THEN 0
                 ELSE ROUND(ST_Distance_Sphere(
                                    POINT(uc.geo_long, uc.geo_lat),
                                    POINT(
                                            CAST(SUBSTRING_INDEX(epv.coordinates, ',', -1) AS DECIMAL(10,6)),
                                            CAST(SUBSTRING_INDEX(epv.coordinates, ',', 1) AS DECIMAL(10,6))
                                    )))
                            END AS distance_meters
                            FROM evo_product_pharmacy_view epp
                            JOIN evo_pharmacies_view epv ON epp.pharmacy_id = epv.pharmacy_id
                            LEFT JOIN user_cart uc ON 1=1
                            WHERE epp.stock_count > 0
                      )
SELECT
    ppd.product_id,
    ppd.recipe,
    ppd.is_recipe,
    ppd.is_alcohol,
    ppd.product_name,
    ppd.pharmacy_id,
    ppd.pharmacy_delivery,
    ppd.price,
    ppd.price_old,
    ppd.stock_count,
    ppd.expiration_date,
    ppd.pharmacy_name,
    ppd.pharmacy_alias,
    ppd.address,
    ppd.coordinates,
    ppd.schedule,
    ppd.user_id,
    JSON_OBJECT(
            'product_id', ppd.product_id,
            'product_name', ppd.product_name,
            'pharmacy_id', ppd.pharmacy_id,
            'pharmacy_delivery', ppd.pharmacy_delivery,
            'price', ppd.price,
            'price_old', ppd.price_old,
            'stock_count', ppd.stock_count,
            'expiration_date', ppd.expiration_date,
            'pharmacy_name', ppd.pharmacy_name,
            'pharmacy_alias', ppd.pharmacy_alias,
            'address', ppd.address,
            'coordinates', ppd.coordinates,
            'schedule', ppd.schedule,
            'distance_meters', ppd.distance_meters
    ) AS product_pharmacy_json,
    ppd.distance_meters AS sort_distance,
    ppd.stock_count AS sort_stock
FROM product_pharmacy_data ppd
order by ppd.distance_meters asc, ppd.stock_count desc;