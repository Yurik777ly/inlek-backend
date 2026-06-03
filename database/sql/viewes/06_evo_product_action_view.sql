CREATE OR REPLACE VIEW evo_product_action_view AS
SELECT

    product_id,

    promotion_id,

    promotion_text,

    promotion_flg,

    published,

    create_dttm_raw,
    edited_dttm_raw,
    published_dttm_raw,

    create_dttm,
    edited_dttm,
    published_dttm

FROM product_action_cache;
-- кандидат на удаление
