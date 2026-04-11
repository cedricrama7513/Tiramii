<?php
/**
 * Garantit que la Box suprême (box_supreme) existe en products + stock_levels.
 * Idempotent : sans effet si déjà correct. À appeler après obtention du PDO.
 */
declare(strict_types=1);

function tiramii_ensure_box_supreme(PDO $pdo): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    try {
        $st = $pdo->query("SELECT 1 FROM products WHERE id = 'box_supreme' LIMIT 1");
        $hasProduct = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;

        if (!$hasProduct) {
            $pdo->beginTransaction();
            $insP = $pdo->prepare(
                'INSERT INTO products (id, name, price_eur, description, badge_class, badge_text, img_key, sort_order, is_active)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $insP->execute([
                'box_supreme',
                'Box suprême',
                10.0,
                "M&M's, Raffaello, Daim, Kinder Bueno White.",
                'badge-hot',
                '✨ Suprême',
                'kw',
                7,
                1,
            ]);
            $insS = $pdo->prepare('INSERT INTO stock_levels (product_id, quantity) VALUES (?, 20)');
            $insS->execute(['box_supreme']);
            $pdo->commit();

            return;
        }

        $st2 = $pdo->query("SELECT 1 FROM stock_levels WHERE product_id = 'box_supreme' LIMIT 1");
        $hasStock = $st2 !== false && $st2->fetch(PDO::FETCH_ASSOC) !== false;
        if (!$hasStock) {
            $insS = $pdo->prepare('INSERT INTO stock_levels (product_id, quantity) VALUES (?, 20)');
            $insS->execute(['box_supreme']);
        }
    } catch (Throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}
