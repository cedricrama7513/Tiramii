<?php
/**
 * Colonne prix unitaire pro (HT) sur products ; NULL = tarif affiché « sur devis ».
 */
declare(strict_types=1);

function tiramii_ensure_pro_price_column(PDO $pdo): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    try {
        $q = $pdo->query("SHOW COLUMNS FROM products LIKE 'pro_price_eur'");
        if ($q && $q->rowCount() === 0) {
            $pdo->exec(
                "ALTER TABLE products ADD COLUMN pro_price_eur DECIMAL(6,2) NULL DEFAULT NULL
                 COMMENT 'Prix unitaire pro HT ; NULL = sur devis' AFTER price_eur"
            );
        }
    } catch (Throwable) {
        /* table absente ou droits ALTER refusés */
    }
}
