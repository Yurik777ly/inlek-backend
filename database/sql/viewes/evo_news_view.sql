create or replace view evo_news_view as
select
    eso.id as contentid,
    pagetitle, alias, content, menutitle, pub_date,
    createdon as create_dttm_raw, from_unixtime(createdon) as create_dttm,
    publishedon as published_dttm_raw, from_unixtime(publishedon) as published_dttm,
    editedon as edited_dttm_raw, from_unixtime(editedon) as edited_dttm,
    published,
    estc1.value as image,
    estc2.value as description
from evo_site_content eso
left join evo_site_tmplvar_contentvalues estc1 on (
    eso.id = estc1.contentid
    and estc1.tmplvarid = 27
)
left join evo_site_tmplvar_contentvalues estc2 on (
    eso.id = estc2.contentid
    and estc2.tmplvarid = 70
)
where template = 10 and published = 1;
