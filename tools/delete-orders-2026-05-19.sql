-- Vérifier avant suppression (phpMyAdmin) :
-- SELECT id, created_at, first_name, phone, total_eur FROM orders WHERE DATE(created_at) = '2026-05-19';

-- Suppression (order_items supprimés en CASCADE) :
DELETE FROM orders WHERE DATE(created_at) = '2026-05-19';
