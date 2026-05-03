-- À exécuter une fois dans phpMyAdmin (base du site) pour supprimer toutes les commandes de test.
-- order_items est supprimé en cascade si la contrainte ON DELETE CASCADE est en place.

DELETE FROM stock_reservations;
DELETE FROM orders;
