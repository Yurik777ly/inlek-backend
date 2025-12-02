create or replace view evo_category_product_view as
select
    eso.id as category_id,
    eso.pagetitle as category_name,
    estc1.value as category_image,
    epv.product_id,
    epv.pagetitle as product_name,
    epv.alias as product_alias,
    epv.mnn,
    epv.code,
    epv.brand,
    epv.country,
    epv.form,
    epv.release_form,
    epv.termin,
    epv.temperature,
    epv.image as product_image

from evo_site_content eso
inner join evo_site_content_categories escc on (eso.id = escc.category)
inner join evo_products_view epv on (escc.doc = epv.product_id)
left join evo_site_tmplvar_contentvalues estc1 on (
    eso.id = estc1.contentid
    and estc1.tmplvarid = 2
)
left join evo_site_tmplvar_contentvalues estc2 on (
    eso.id = estc2.contentid
    and estc2.tmplvarid = 104
);