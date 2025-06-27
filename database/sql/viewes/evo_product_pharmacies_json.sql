create or replace view evo_product_pharmacies_json as
select product_id,
       json_arrayagg(
               json_object(
                       'product_id', product_id,
                       'product_name', product_name,
                       'pharmacy_id', pharmacy_id,
                       'price', price,
                       'price_old', price_old,
                       'stock_count', stock_count,
                       'expiration_date', expiration_date,
                       'pharmacy_name', pharmacy_name,
                       'pharmacy_alias', pharmacy_alias,
                       'address', address,
                       'coordinates', coordinates,
                       'schedule', schedule
               )
       )
       as product_pharmacy_json
from evo_product_pharmacy_view
group by product_id;