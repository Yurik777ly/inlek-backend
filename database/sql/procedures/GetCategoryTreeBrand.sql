DROP PROCEDURE IF EXISTS GetCategoryTreeBrand;
CREATE PROCEDURE GetCategoryTreeBrand(IN input_category_id INT)
BEGIN
    WITH RECURSIVE category_tree AS (
        SELECT category_id, parent, pagetitle, alias, category_advertisement, image
        FROM evo_category_view
        WHERE category_id = input_category_id  -- Начинаем с указанной категории

        UNION ALL

        SELECT c.category_id, c.parent, c.pagetitle, c.alias, c.category_advertisement, c.image
        FROM evo_category_view c
                 INNER JOIN category_tree ct ON c.parent = ct.category_id
    )
    SELECT
        distinct ecpv.brand
    FROM category_tree ct
             inner join evo_category_product_view ecpv on (ct.category_id = ecpv.category_id)
    order by ecpv.brand;
END;