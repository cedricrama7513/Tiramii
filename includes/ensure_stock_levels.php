<?php
/**
 * Crée une ligne stock_levels (50 par défaut) pour chaque produit catalogue sans entrée.
 * Évite les commandes « fantômes » sans déstockage (ex. Saveur Spéculoos id spec).
 */
declare(strict_types=1);

function tiramii_ensure_stock_levels_for_all_products(PDO $pdo): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    try {
        $pdo->exec(
            'INSERT INTO stock_levels (product_id, quantity)
             SELECT p.id, 50 FROM products p
             LEFT JOIN stock_levels s ON s.product_id = p.id
             WHERE s.product_id IS NULL'
        );
    } catch (Throwable) {
        /* schéma partiel ou droits */
    }
}
