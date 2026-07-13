CREATE TABLE IF NOT EXISTS category_definitions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY category_definitions_name_unique (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE items ADD COLUMN category_id INT NULL;
ALTER TABLE item_definitions ADD COLUMN category_id INT NULL;

INSERT IGNORE INTO category_definitions (name, sort_order)
SELECT category, (@category_order := @category_order + 1)
FROM (
  SELECT category FROM items WHERE TRIM(category) <> ''
  UNION
  SELECT category FROM item_definitions WHERE TRIM(category) <> ''
  ORDER BY category
) AS source
CROSS JOIN (SELECT @category_order := 0) AS counter;

UPDATE items i JOIN category_definitions c ON LOWER(c.name) = LOWER(i.category)
SET i.category_id = c.id
WHERE i.category_id IS NULL;

UPDATE item_definitions d JOIN category_definitions c ON LOWER(c.name) = LOWER(d.category)
SET d.category_id = c.id
WHERE d.category_id IS NULL;
