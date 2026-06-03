CREATE OR REPLACE VIEW evo_category_product_view AS
SELECT
    category_id,
    product_id,
    category_name,
    category_image
FROM product_category_cache;
