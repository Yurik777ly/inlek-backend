CREATE OR REPLACE VIEW evo_product_info_view_json_detailed AS
SELECT

    epivj.*,

    pbc.brand_products_json
        AS brand_products,

    psc.similar_products_json
        AS similar_products,

    prc.related_products_json
        AS related_products,

    pcc.category_products_json
        AS category_products

FROM evo_product_info_view_json epivj

         LEFT JOIN product_brand_cache pbc
                   ON pbc.product_id = epivj.product_id

         LEFT JOIN product_similar_cache psc
                   ON psc.product_id = epivj.product_id

         LEFT JOIN product_related_cache prc
                   ON prc.product_id = epivj.product_id

         LEFT JOIN product_category_products_cache pcc
                   ON pcc.product_id = epivj.product_id;
