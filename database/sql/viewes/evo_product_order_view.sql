create or replace view evo_product_order_view as
select
    ecop.order_id, ecop.product_id, ecop.pharmacy_id,
    ecop.title as product_title, ecop.price, eppv.price as pharmacy_price, ecop.position, ecop.count,
    epv.alias, epv.product_description, epv.mnn, epv.mnn_lat, epv.code, epv.brand, epv.country, epv.release_form, epv.termin, epv.temperature, epv.image,
    epv.dose, epv.recipe, epv.is_recipe, epv.is_alcohol, epv.product_insert, epv.product_time_register, epv.product_date_register, epv.product_register,
    epv.product_trademark, epv.product_sticker
from evo_commerce_order_products ecop
         inner join evo_products_view epv on ecop.product_id = epv.product_id
         inner join evo_product_pharmacy_view eppv on (
    ecop.product_id = eppv.product_id
        and eppv.pharmacy_id = ecop.pharmacy_id
    );


ALTER TABLE evo_commerce_order_products
    ADD COLUMN pharmacy_id INT AS (
        CASE
            WHEN json_extract(options, '$.pharmacy_id') IS NULL THEN NULL
            WHEN json_unquote(json_extract(options, '$.pharmacy_id')) = 'null' THEN NULL
            ELSE CAST(json_unquote(json_extract(options, '$.pharmacy_id')) AS UNSIGNED)
            END
        ) STORED;

CREATE INDEX idx_pharmacy_id ON evo_commerce_order_products (pharmacy_id);