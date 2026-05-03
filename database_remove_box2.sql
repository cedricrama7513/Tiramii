-- À exécuter une fois sur la base déjà en production (phpMyAdmin > SQL)
SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM stock_levels WHERE product_id = 'box2';
DELETE FROM products WHERE id = 'box2';
SET FOREIGN_KEY_CHECKS = 1;
UPDATE products SET name = 'Box gourmande' WHERE id = 'box1';
