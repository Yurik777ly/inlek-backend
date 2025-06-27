create or replace view evo_product_info_view_json_opt_noact as
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
       json_unquote(json_extract(estc2.value, '$.brand'))        AS brand_j,
       json_unquote(json_extract(estc2.value, '$.country'))      AS country_j,
       json_unquote(json_extract(estc2.value, '$.form'))         AS form_j,
       json_unquote(json_extract(estc2.value, '$.release_form')) AS release_form_j,
       json_unquote(json_extract(estc2.value, '$.termin'))       AS termin,
       json_unquote(json_extract(estc2.value, '$.temperature'))  AS temperature,
       estc1.value                                               AS image,
       estc3.value                                               AS dose,
       estc4.value                                               AS recipe_title,
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
       estc17.value                                              AS brand,
       estc18.value                                              AS country,
       estc19.value                                              AS release_form,
       -- coalesce(estc20.value, 'no')                              AS recipe,
       estc21.value                                              AS form,
       IF((estc4.value is null or estc4.value not in ('Рецептурный', 'Рецепт урный'))
              and (coalesce(delivery_pharmacy.available, 0) = 1) and coalesce(estc14.value, '') <> 'yes',
          'Доставка', 'Самовывоз'
       ) as delivery,
       availability.is_available,
       eppvj.promocodes_json,
       json_object(
               'product_id', eso.id,
               'pagetitle', eso.pagetitle,
               'parent', eso.parent,
               'product_description', estc16.value,
               'instruction', estc22.value,
               'mnn', json_unquote(json_extract(estc2.value, '$.mnn')),
               'mnn_lat', estc15.value,
               'code', json_unquote(json_extract(estc2.value, '$.code')),
               'brand', json_unquote(json_extract(estc2.value, '$.brand')),
               'country', json_unquote(json_extract(estc2.value, '$.country')),
               'form', json_unquote(json_extract(estc2.value, '$.form')),
               'release_form', json_unquote(json_extract(estc2.value, '$.release_form')),
               'termin', json_unquote(json_extract(estc2.value, '$.termin')),
               'temperature', json_unquote(json_extract(estc2.value, '$.temperature')),
               'image', estc1.value,
               'dose', estc3.value,
               'recipe', estc4.value,
               'is_recipe', IF(estc4.value in ('Рецептурный', 'Рецепт урный'), 'true', 'false'),
               'is_alcohol', coalesce(estc14.value, 'no'),
               'product_insert', estc5.value,
               'product_time_register', estc6.value,
               'product_register', estc7.value,
               'product_date_register', estc8.value,
               'product_trademark', estc9.value,
               'product_price_from', estc10.value,
               'product_price_from_old', estc11.value,
               'product_price_from_percent', estc12.value,
               'product_sticker', estc13.value,
               'delivery', IF((estc4.value is null or estc4.value not in ('Рецептурный', 'Рецепт урный'))
                                  and (coalesce(delivery_pharmacy.available, 0) = 1) and coalesce(estc14.value, '') <> 'yes',
                              'Доставка', 'Самовывоз'
                           )
       ) as product_charachters
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
         left join evo_site_tmplvar_contentvalues estc11 on (
    eso.id = estc11.contentid and estc11.tmplvarid = 8
    )
         left join evo_site_tmplvar_contentvalues estc12 on (
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
         left join evo_site_tmplvar_contentvalues estc17 on (
    eso.id = estc17.contentid and estc17.tmplvarid = 101
    )
         left join evo_site_tmplvar_contentvalues estc18 on (
    eso.id = estc18.contentid and estc18.tmplvarid = 75
    )
         left join evo_site_tmplvar_contentvalues estc19 on (
    eso.id = estc19.contentid and estc19.tmplvarid = 89
    )
         left join evo_site_tmplvar_contentvalues estc20 on (
    eso.id = estc20.contentid and estc20.tmplvarid = 10
    )
         left join evo_site_tmplvar_contentvalues estc21 on (
    eso.id = estc21.contentid and estc21.tmplvarid = 78
    )
         left join evo_site_tmplvar_contentvalues estc22 on (
    eso.id = estc22.contentid and estc22.tmplvarid = 22
    )
     LEFT JOIN (
    SELECT DISTINCT product_id, 1 as available
    FROM evo_offers
    WHERE pharmacy_id = 6864
) delivery_pharmacy ON (eso.id = delivery_pharmacy.product_id)
         left join (
    select
        epl.link as product_id,
        json_arrayagg(
                json_object(
                        'promotion_id', ep.promotion_id,
                        'promocode', ep.promocode,
                        'promocode_percent', ep.discount,
                        'min_amount', ep.min_amount,
                        'usages', ep.usages,
                        'begin', ep.begin,
                        'end', ep.end
                )
        ) as promocodes_json
    from evo_promocodes ep
             inner join evo_promocodes_links epl on (
        ep.id = epl.pcid
        )
    where ep.begin < now() < ep.end
    group by epl.link, ep.promotion_id, ep.promocode, ep.discount, ep.min_amount, ep.usages, ep.begin, ep.end
        and active = 1
) eppvj on (eso.id = eppvj.product_id)
         left join (
    select product_id,
           IF(sum(stock_count) > 0, 1, 0) is_available
    from evo_offers
    group by product_id
) availability on (eso.id = availability.product_id)
where eso.template = 5;