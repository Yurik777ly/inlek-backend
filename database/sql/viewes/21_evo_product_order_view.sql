SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'evo_commerce_order_products'
        AND COLUMN_NAME = 'pharmacy_id'
);
SET @add_column_sql = IF(@column_exists = 0,
    'ALTER TABLE evo_commerce_order_products
    ADD COLUMN pharmacy_id INT AS (
        CASE
            WHEN json_extract(options, ''$.pharmacy_id'') IS NULL THEN NULL
            WHEN json_unquote(json_extract(options, ''$.pharmacy_id'')) = ''null'' THEN NULL
            ELSE CAST(json_unquote(json_extract(options, ''$.pharmacy_id'')) AS UNSIGNED)
        END
    ) STORED',
    'SELECT ''Column pharmacy_id already exists'' as message');

PREPARE stmt FROM @add_column_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'evo_commerce_order_products'
        AND INDEX_NAME = 'idx_pharmacy_id'
);
SET @create_index_sql = IF(@index_exists = 0,
    'CREATE INDEX idx_pharmacy_id ON evo_commerce_order_products (pharmacy_id)',
    'SELECT ''Index idx_pharmacy_id already exists'' as message');
PREPARE stmt2 FROM @create_index_sql;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
CREATE OR REPLACE VIEW evo_product_order_view AS
SELECT
    ecop.order_id, ecop.product_id, ecop.pharmacy_id,
    ecop.title as product_title, ecop.price, eppv.price as pharmacy_price, ecop.position, ecop.count,
    epv.alias, epv.product_description, epv.mnn, epv.mnn_lat, epv.code, epv.brand, epv.country, epv.release_form, epv.termin, epv.temperature, epv.image,
    epv.dose, epv.recipe, epv.is_recipe, epv.is_alcohol, epv.product_insert, epv.product_time_register, epv.product_date_register, epv.product_register,
    epv.product_trademark, epv.product_sticker
FROM evo_commerce_order_products ecop
INNER JOIN evo_products_view epv ON ecop.product_id = epv.product_id
INNER JOIN evo_product_pharmacy_view eppv ON (
    ecop.product_id = eppv.product_id
    AND eppv.pharmacy_id = ecop.pharmacy_id
);