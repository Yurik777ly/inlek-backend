CREATE OR REPLACE VIEW evo_product_categories_json AS
SELECT
    product_id,
    categories_json
FROM category_json_cache;
