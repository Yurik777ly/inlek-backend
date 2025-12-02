create or replace view evo_pharmacies_view as
select
    eso.id as pharmacy_id,
    eso.pagetitle,
    eso.alias,
    eso.content,
    estc1.value as address,
    estc2.value as coordinates,
    estc3.value as image,
    estc4.value as schedule,
    createdon as create_dttm_raw, from_unixtime(createdon) as create_dttm,
    publishedon as published_dttm_raw, from_unixtime(publishedon) as published_dttm,
    editedon as edited_dttm_raw, from_unixtime(editedon) as edited_dttm,
    published

from evo_site_content eso
left join evo_site_tmplvar_contentvalues estc1 on (
    eso.id = estc1.contentid
    and estc1.tmplvarid = 57
)
left join evo_site_tmplvar_contentvalues estc2 on (
    eso.id = estc2.contentid
    and estc2.tmplvarid = 58
)
left join evo_site_tmplvar_contentvalues estc3 on (
    eso.id = estc3.contentid
    and estc3.tmplvarid = 59
)
left join evo_site_tmplvar_contentvalues estc4 on (
    eso.id = estc4.contentid
    and estc4.tmplvarid = 61
)
where eso.template = 24;
