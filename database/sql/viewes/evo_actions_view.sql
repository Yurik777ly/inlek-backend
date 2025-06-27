create or replace view evo_actions_view as
select
    eso.id as action_id,
    pagetitle, alias, content, menutitle, pub_date,
    replace(replace(REGEXP_SUBSTR(pagetitle, '(скидка|скидки от) ([0-9]+)%', 1, 1, 'c'), 'скидка ', '-'), 'скидки от ', '-') AS discount,
    replace(replace(REGEXP_SUBSTR(pagetitle, 'по .*скидк', 1, 1, 'c'), ' скидк', ''), 'по ', '') AS end_action_date,
    createdon as create_dttm_raw, from_unixtime(createdon) as create_dttm,
    publishedon as published_dttm_raw, from_unixtime(publishedon) as published_dttm,
    editedon as edited_dttm_raw, from_unixtime(editedon) as edited_dttm,
    published,
    estc1.value as image,
    estc2.value as image_1400_300,
    estc3.value as image_960_400,
    estc4.value as goods_ids,
    action_products.action_products
from evo_site_content eso
left join evo_site_tmplvar_contentvalues estc1 on (
    eso.id = estc1.contentid
    and estc1.tmplvarid = 31 -- photo
)
left join evo_site_tmplvar_contentvalues estc2 on (
    eso.id = estc2.contentid
    and estc2.tmplvarid = 32 -- 1400-300
)
left join evo_site_tmplvar_contentvalues estc3 on (
    eso.id = estc3.contentid
    and estc3.tmplvarid = 33 -- 960-400
)
left join evo_site_tmplvar_contentvalues estc4 on (
    eso.id = estc4.contentid
    and estc4.tmplvarid = 35 -- goods in promo
)
left join (
    select
    eso_inner.id as action_id,
    json_arrayagg(
        json_object(
            'product_id', epivjo.product_id,
            'product_charachters', epivjo.product_charachters,
            'promocodes', epivjo.promocodes_json
        )
    ) as action_products
    from evo_site_content eso_inner
    left join evo_site_tmplvar_contentvalues estc4_inner on (
        eso_inner.id = estc4_inner.contentid
        and estc4_inner.tmplvarid = 35 -- goods in promo
    )
    left JOIN JSON_TABLE(
        CONCAT('["', REPLACE(estc4_inner.value, ',', '","'), '"]'),
        '$[*]' COLUMNS (value VARCHAR(255) PATH '$')
    ) AS jt ON jt.value IS NOT NULL
    left join evo_product_info_view_json_opt_noact epivjo on jt.value = epivjo.product_id
    where eso_inner.template = 12
    group by eso_inner.id
) as action_products on (eso.id = action_products.action_id)
where template = 12;