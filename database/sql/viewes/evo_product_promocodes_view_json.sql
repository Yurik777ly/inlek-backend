create or replace view evo_product_promocodes_view_json as
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
and active = 1;