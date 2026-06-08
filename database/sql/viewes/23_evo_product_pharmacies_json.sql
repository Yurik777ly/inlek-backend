CREATE OR REPLACE VIEW evo_product_pharmacies_json AS
SELECT
    product_id,
    JSON_ARRAYAGG(
        JSON_OBJECT(
            'product_id', product_id,
            'product_name', product_name,
            'pharmacy_id', pharmacy_id,
            'price', price,
            'price_old', price_old,
            'stock_count', stock_count,
            'expiration_date', expiration_date,
            'pharmacy_name', pharmacy_name,
            'pharmacy_alias', pharmacy_alias,
            'address', address,
            'coordinates', coordinates,
            'schedule', schedule
        )
    ) AS product_pharmacy_json
FROM product_pharmacy_cache
WHERE stock_count > 0
GROUP BY product_id;
