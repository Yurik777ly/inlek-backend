create or replace view evo_products_view as
select eso.id                                                    AS product_id,
       eso.pagetitle                                             AS pagetitle,
       eso.alias                                                 AS alias,
       eso.content                                               AS content,
       eso.menutitle                                             AS menutitle,
       eso.pub_date                                              AS pub_date,
       eso.parent                                                AS parent,
       eso.createdon                                             AS create_dttm_raw,
       from_unixtime(eso.createdon)                              AS create_dttm,
       eso.publishedon                                           AS published_dttm_raw,
       from_unixtime(eso.publishedon)                            AS published_dttm,
       eso.editedon                                              AS edited_dttm_raw,
       from_unixtime(eso.editedon)                               AS edited_dttm,
       eso.published                                             AS published,
       estc16.value                                              AS product_description,
       estc22.value                                              AS instruction,
       json_unquote(json_extract(estc2.value, '$.mnn'))          AS mnn,
       estc15.value                                              AS mnn_lat,
       json_unquote(json_extract(estc2.value, '$.code'))         AS code,
       json_unquote(json_extract(estc2.value, '$.brand'))        AS brand,
       json_unquote(json_extract(estc2.value, '$.country'))      AS country,
       json_unquote(json_extract(estc2.value, '$.form'))         AS form,
       json_unquote(json_extract(estc2.value, '$.release_form')) AS release_form,
       json_unquote(json_extract(estc2.value, '$.termin'))       AS termin,
       json_unquote(json_extract(estc2.value, '$.temperature'))  AS temperature,
       estc1.value                                               AS image,
       estc3.value                                               AS dose,
       estc4.value                                               AS recipe,
       IF(estc4.value in ('Рецептурный', 'Рецепт урный'), 'true', 'false') as is_recipe,
       estc5.value                                               AS product_insert,
       estc6.value                                               AS product_time_register,
       estc7.value                                               AS product_register,
       estc8.value                                               AS product_date_register,
       estc9.value                                               AS product_trademark,
       estc10.value                                              AS product_price_from,
       estc11.value                                              AS product_price_from_old,
       estc12.value                                              AS product_price_from_percent,
       estc13.value                                              AS product_sticker,
       coalesce(estc14.value, 'no')                              AS is_alcohol,
       IF((estc4.value is null or estc4.value not in ('Рецептурный', 'Рецепт урный'))
              and (coalesce(delivery_pharmacy.available, 0) = 1) and coalesce(estc14.value, '') <> 'yes',
          'Доставка', 'Самовывоз'
       ) as delivery
from evo_site_content eso
         left join evo_site_tmplvar_contentvalues estc1 on (
    eso.id = estc1.contentid and estc1.tmplvarid = 5
    )
         left join evo_site_tmplvar_contentvalues estc2 on (
    eso.id = estc2.contentid and estc2.tmplvarid = 15
    )
         left join evo_site_tmplvar_contentvalues estc3 on (
    eso.id = estc3.contentid and estc3.tmplvarid = 91
    )
         left join evo_site_tmplvar_contentvalues estc4 on (
    eso.id = estc4.contentid and estc4.tmplvarid = 112
    )
         left join evo_site_tmplvar_contentvalues estc5 on (
    eso.id = estc5.contentid and estc5.tmplvarid = 90
    )
         left join evo_site_tmplvar_contentvalues estc6 on (
    eso.id = estc6.contentid and estc6.tmplvarid = 93
    )
         left join evo_site_tmplvar_contentvalues estc7 on (
    eso.id = estc7.contentid and estc7.tmplvarid = 95
    )
         left join evo_site_tmplvar_contentvalues estc8 on (
    eso.id = estc8.contentid and estc8.tmplvarid = 96
    )
         left join evo_site_tmplvar_contentvalues estc9 on (
    eso.id = estc9.contentid and estc9.tmplvarid = 102
    )
         left join evo_site_tmplvar_contentvalues estc10 on (
    eso.id = estc10.contentid and estc10.tmplvarid = 7
    )
         left join qmediaby_apteka.evo_site_tmplvar_contentvalues estc11 on (
    eso.id = estc11.contentid and estc11.tmplvarid = 8
    )
         left join qmediaby_apteka.evo_site_tmplvar_contentvalues estc12 on (
    eso.id = estc12.contentid and estc12.tmplvarid = 9
    )
         left join evo_site_tmplvar_contentvalues estc13 on (
    eso.id = estc13.contentid and estc13.tmplvarid = 14
    )
         left join evo_site_tmplvar_contentvalues estc14 on (
    eso.id = estc14.contentid and estc14.tmplvarid = 119
    )
         left join evo_site_tmplvar_contentvalues estc15 on (
    eso.id = estc15.contentid and estc15.tmplvarid = 88
    )
         left join evo_site_tmplvar_contentvalues estc16 on (
    eso.id = estc16.contentid and estc16.tmplvarid = 23
    )
         left join evo_site_tmplvar_contentvalues estc22 on (
    eso.id = estc22.contentid and estc22.tmplvarid = 22
    )
         left join (
    select 1 as available, eo.product_id
    from evo_offers eo
             inner join evo_pharmacies_view epv on eo.pharmacy_id = epv.pharmacy_id and eo.pharmacy_id = 17599997
) delivery_pharmacy on (eso.id = delivery_pharmacy.product_id)
where eso.template = 5;
