CREATE OR REPLACE VIEW evo_product_pharmacy_view AS
SELECT
    ppc.product_id,
    ppc.pharmacy_id,
    ppc.product_name,
    ppc.pharmacy_alias,
    ppc.pharmacy_name,
    ppc.coordinates,
    ppc.schedule,
    ppc.address,
    ppc.price,
    ppc.price_old,
    ppc.stock_count,
    ppc.expiration_date
FROM product_pharmacy_cache ppc;
