CREATE OR REPLACE VIEW evo_pharmacies_view AS
SELECT
    pharmacy_id,
    pagetitle,
    alias,
    content,

    address,
    coordinates,
    image,
    schedule,

    create_dttm_raw,
    create_dttm,

    published_dttm_raw,
    published_dttm,

    edited_dttm_raw,
    edited_dttm,

    published

FROM pharmacy_cache;
