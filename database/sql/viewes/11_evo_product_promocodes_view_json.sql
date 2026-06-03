CREATE OR REPLACE VIEW evo_product_promocodes_view_json AS
SELECT
    product_id,
    promocodes_json
FROM product_promocode_json_cache;
-- кандидат на удаление
