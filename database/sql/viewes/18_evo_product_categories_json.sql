create or replace view evo_product_categories_json as
select product_id,
       json_arrayagg(
            json_object(
            'category_id', category_id,
            'category_name', category_name,
            'category_image', category_image
            )
       ) as categories_json
from evo_category_product_view
group by product_id
;
-- кандидат на удаление
