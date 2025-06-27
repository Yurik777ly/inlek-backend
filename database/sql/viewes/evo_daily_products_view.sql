create or replace view evo_daily_products_view as
select epv.*
from evo_products_view epv
inner join evo_system_settings ess on find_in_set(epv.product_id, ess.setting_value)
where setting_name = 'site_products_day';