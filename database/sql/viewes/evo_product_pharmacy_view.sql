create or replace view evo_product_pharmacy_view as
select
    eo.product_id, eo.expiration_date, eo.price, eo.price_old, eo.stock_count, eo.updated_at,
    eppv.pagetitle as product_name, eppv.action_id, eppv.promotion_text, eppv.promotion_flg,
    eppv.alias as product_alias, eppv.mnn, eppv.code, eppv.brand, eppv.country, eppv.recipe, eppv.is_recipe, eppv.is_alcohol, eppv.release_form, eppv.termin,
    eppv.temperature, eppv.image as product_image,
    eo.pharmacy_id, epv.pagetitle as pharmacy_name, epv.alias as pharmacy_alias, epv.address, epv.schedule, epv.coordinates,
    case
        when epv.pharmacy_id = 17599997 then 'Доставка' else 'Самовывоз'
        end as pharmacy_delivery
from evo_offers eo
         inner join evo_product_action_view eppv on eo.product_id = eppv.product_id
         inner join evo_pharmacies_view epv on eo.pharmacy_id = epv.pharmacy_id;
