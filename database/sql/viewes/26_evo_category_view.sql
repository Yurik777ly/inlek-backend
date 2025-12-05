CREATE OR REPLACE VIEW evo_category_view AS
SELECT
    eso.id AS category_id,
    eso.parent,
    eso.pagetitle,
    eso.alias,
    eso.content,
    eso.menutitle,
    eso.pub_date,
    eso.createdon AS create_dttm_raw,
    FROM_UNIXTIME(eso.createdon) AS create_dttm,
    eso.publishedon AS published_dttm_raw,
    FROM_UNIXTIME(eso.publishedon) AS published_dttm,
    eso.editedon AS edited_dttm_raw,
    FROM_UNIXTIME(eso.editedon) AS edited_dttm,
    eso.published,
    COALESCE(
        NULLIF(TRIM(estc2.value), ''),
        NULLIF(TRIM(estc1.value), ''),
        NULL
    ) AS image,
    NULL AS category_advertisement
FROM evo_site_content eso
LEFT JOIN evo_site_tmplvar_contentvalues estc1 ON (
    eso.id = estc1.contentid
    AND estc1.tmplvarid = 1
)
LEFT JOIN evo_site_tmplvar_contentvalues estc2 ON (
    eso.id = estc2.contentid
    AND estc2.tmplvarid = 2
)
WHERE eso.template = 2;