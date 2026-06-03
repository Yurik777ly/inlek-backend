CREATE OR REPLACE VIEW evo_product_action_view_json AS
SELECT
    product_id,
    action_json
FROM product_action_json_cache;
