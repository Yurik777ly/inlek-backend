create or replace view evo_product_info_view_json_detailed as
select
    epivj.*,
    (
        select
            json_arrayagg(
                json_object(
                    'product_id', eso.id,
                    'parent', eso.parent,
                    'alias', eso.alias,
                    'pagetitle', eso.pagetitle,
                    'image', estc1.value,
                    'product_price_from', estc10.value,
                    'product_price_from_old', estc11.value,
                    'product_price_from_percent', estc12.value,
                    'product_sticker', estc13.value
                )
            ) as product_charachters
        from evo_site_tmplvar_contentvalues e1
        inner join evo_site_tmplvar_contentvalues e2 ON e1.tmplvarid = 76 AND e1.tmplvarid = e2.tmplvarid AND e1.contentid != e2.contentid AND e2.value = e1.value
        inner join evo_site_content eso on (e2.contentid = eso.id)
        left join evo_site_tmplvar_contentvalues estc1 on (
            eso.id = estc1.contentid and estc1.tmplvarid = 5
        )
        left join evo_site_tmplvar_contentvalues estc10 on (
            e1.contentid = estc10.contentid and estc10.tmplvarid = 7
        )
        left join evo_site_tmplvar_contentvalues estc11 on (
            e1.contentid = estc11.contentid and estc11.tmplvarid = 8
        )
        left join evo_site_tmplvar_contentvalues estc12 on (
            e1.contentid = estc12.contentid and estc12.tmplvarid = 9
        )
        left join evo_site_tmplvar_contentvalues estc13 on (
            e1.contentid = estc13.contentid and estc13.tmplvarid = 14
        )
        where e1.contentid = epivj.product_id and e1.tmplvarid = 76
    ) as brand_products,
    (
        select
            json_arrayagg(
                json_object(
                    'product_id', eso.id,
                    'parent', eso.parent,
                    'alias', eso.alias,
                    'pagetitle', eso.pagetitle,
                    'image', estc1.value,
                    'product_price_from', estc10.value,
                    'product_price_from_old', estc11.value,
                    'product_price_from_percent', estc12.value,
                    'product_sticker', estc13.value
                )
            ) as product_charachters
        from  evo_site_tmplvar_contentvalues e0
        left join JSON_TABLE(
            CONCAT('["', REPLACE(e0.value, ',', '","'), '"]'),
            '$[*]' COLUMNS (value VARCHAR(255) PATH '$')
        ) AS jt ON jt.value IS NOT NULL
        inner join evo_site_content eso on (jt.value = eso.id)
        left join evo_site_tmplvar_contentvalues estc1 on (
            eso.id = estc1.contentid and estc1.tmplvarid = 5
        )
        left join evo_site_tmplvar_contentvalues estc10 on (
            eso.id = estc10.contentid and estc10.tmplvarid = 7
        )
        left join evo_site_tmplvar_contentvalues estc11 on (
            eso.id = estc11.contentid and estc11.tmplvarid = 8
        )
        left join evo_site_tmplvar_contentvalues estc12 on (
            eso.id = estc12.contentid and estc12.tmplvarid = 9
        )
        left join evo_site_tmplvar_contentvalues estc13 on (
            eso.id = estc13.contentid and estc13.tmplvarid = 14
        )
        where epivj.product_id = e0.contentid AND e0.tmplvarid = 117
    ) as similar_products,
    (
        select
            json_arrayagg(
                json_object(
                    'product_id', eso.id,
                    'parent', eso.parent,
                    'alias', eso.alias,
                    'pagetitle', eso.pagetitle,
                    'image', estc1.value,
                    'product_price_from', estc10.value,
                    'product_price_from_old', estc11.value,
                    'product_price_from_percent', estc12.value,
                    'product_sticker', estc13.value
                )
            ) as product_charachters
        from evo_site_tmplvar_contentvalues e0
        left join JSON_TABLE(
            CONCAT('["', REPLACE(e0.value, ',', '","'), '"]'),
                '$[*]' COLUMNS (value VARCHAR(255) PATH '$')
        ) AS jt ON jt.value IS NOT NULL
        inner join evo_site_content eso on (jt.value = eso.id)
        left join evo_site_tmplvar_contentvalues estc1 on (
            eso.id = estc1.contentid and estc1.tmplvarid = 5
        )
        left join evo_site_tmplvar_contentvalues estc10 on (
            eso.id = estc10.contentid and estc10.tmplvarid = 7
        )
        left join evo_site_tmplvar_contentvalues estc11 on (
            eso.id = estc11.contentid and estc11.tmplvarid = 8
        )
        left join evo_site_tmplvar_contentvalues estc12 on (
            eso.id = estc12.contentid and estc12.tmplvarid = 9
        )
        left join evo_site_tmplvar_contentvalues estc13 on (
            eso.id = estc13.contentid and estc13.tmplvarid = 14
        )
        where epivj.product_id = e0.contentid AND e0.tmplvarid = 16
    ) as related_products,
    (
        select
            json_arrayagg(
                json_object(
                    'product_id', esc.id,
                    'parent', esc.parent,
                    'alias', esc.alias,
                    'pagetitle', esc.pagetitle,
                    'image', estc1.value,
                    'product_price_from', estc10.value,
                    'product_price_from_old', estc11.value,
                    'product_price_from_percent', estc12.value,
                    'product_sticker', estc13.value
                )
            ) as product_charachters
        from evo_site_content eso
        inner join (
            select eso.id as product_id, max(eso_cat.id) as category_id
            from evo_site_content eso
            inner join evo_site_content_categories escc on (eso.id = escc.doc)
            inner join evo_site_content eso_cat on (escc.category = eso_cat.id)
            group by eso.id
        ) max_cat on (eso.id = max_cat.product_id)
        inner join evo_site_content_categories escc_max on (max_cat.category_id = escc_max.category)
        inner join evo_site_content esc on (escc_max.doc = esc.id)
        left join evo_site_tmplvar_contentvalues estc1 on (
            esc.id = estc1.contentid and estc1.tmplvarid = 5
        )
        left join evo_site_tmplvar_contentvalues estc10 on (
            esc.id = estc10.contentid and estc10.tmplvarid = 7
        )
        left join evo_site_tmplvar_contentvalues estc11 on (
            esc.id = estc11.contentid and estc11.tmplvarid = 8
        )
        left join evo_site_tmplvar_contentvalues estc12 on (
            esc.id = estc12.contentid and estc12.tmplvarid = 9
        )
        left join evo_site_tmplvar_contentvalues estc13 on (
            esc.id = estc13.contentid and estc13.tmplvarid = 14
        )
        where eso.id = epivj.product_id
    ) as category_products
from evo_product_info_view_json epivj;