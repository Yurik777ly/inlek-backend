CREATE OR REPLACE VIEW evo_product_pharmacy_view AS
SELECT
    oc.product_id,
    oc.pharmacy_id,

    oc.product_name,
    oc.pharmacy_alias,
    oc.pharmacy_name,
    oc.coordinates,
    oc.schedule,
    oc.address,

    oc.price,
    oc.price_old,

    oc.stock_count,
    oc.expiration_date

FROM offer_cache oc;
