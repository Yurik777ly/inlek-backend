CREATE OR REPLACE VIEW evo_products_view AS
SELECT
    product_id,

    pagetitle,
    alias,

    content,
    menutitle,

    pub_date,

    parent,

    create_dttm_raw,
    create_dttm,

    published_dttm_raw,
    published_dttm,

    edited_dttm_raw,
    edited_dttm,

    published,

    product_description,
    instruction,

    mnn,
    mnn_lat,

    code,
    brand,
    country,

    form,
    release_form,

    termin,
    temperature,

    image,

    dose,
    recipe,
    is_recipe,

    product_insert,
    product_time_register,
    product_register,
    product_date_register,
    product_trademark,

    product_price_from,
    product_price_from_old,
    product_price_from_percent,

    product_sticker,

    is_alcohol,

    delivery,

    is_available

FROM product_cache;
