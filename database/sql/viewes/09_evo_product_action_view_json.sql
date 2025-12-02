create or replace view evo_product_action_view_json as
select
    epav.product_id,
    json_arrayagg(
            json_object(
                    'action_id', action_id,
                    'action_text', promotion_text,
                    'action_percent', product_price_from_percent
            )
    ) as action_json
from evo_product_action_view epav
where epav.action_id is not null and epav.promotion_flg = 1 and epav.product_price_from_percent is not null
group by epav.product_id;