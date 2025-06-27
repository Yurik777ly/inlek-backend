create or replace view evo_product_action_view as
select eav.action_id, not not coalesce(find_in_set(epv.product_id, eav.goods_ids), 0) as promotion_flg,
       eav.pagetitle as promotion_text, epv.*
from evo_products_view epv
         left join evo_actions_view eav
                   on find_in_set(epv.product_id, eav.goods_ids);