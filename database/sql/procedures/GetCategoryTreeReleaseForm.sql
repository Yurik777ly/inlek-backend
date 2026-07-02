DROP PROCEDURE IF EXISTS GetCategoryTreeReleaseForm;
CREATE PROCEDURE GetCategoryTreeReleaseForm(IN input_category_id INT)
BEGIN
    WITH RECURSIVE category_tree AS (
        SELECT category_id, parent, pagetitle, alias, category_advertisement, image
        FROM evo_category_view
        WHERE category_id = input_category_id

        UNION ALL

        SELECT c.category_id, c.parent, c.pagetitle, c.alias, c.category_advertisement, c.image
        FROM evo_category_view c
                 INNER JOIN category_tree ct ON c.parent = ct.category_id
    )
    SELECT DISTINCT
        pc.release_form
    FROM category_tree ct
             INNER JOIN evo_category_product_view ecpv ON ct.category_id = ecpv.category_id
             INNER JOIN product_cache pc ON pc.product_id = ecpv.product_id
    WHERE pc.release_form IS NOT NULL
      AND TRIM(pc.release_form) != ''
      AND pc.published = 1
    ORDER BY pc.release_form;
END;
