<?php
/**
 * Garantit les saveurs mms, kitkat, raffaello en products + stock_levels,
 * et réaligne sort_order des box (9 / 10) pour l’ordre catalogue.
 */
declare(strict_types=1);

function tiramii_ensure_new_flavors(PDO $pdo): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    $defs = [
        [
            'id' => 'mms',
            'name' => 'Saveur M&M\'s',
            'price' => 5.0,
            'description' => 'Mascarpone onctueux, M&M\'s croquants et sauce chocolat.',
            'badge_class' => 'badge-hot',
            'badge_text' => 'Nouveau',
            'img_key' => 'mms',
            'sort_order' => 6,
        ],
        [
            'id' => 'kitkat',
            'name' => 'Saveur KitKat',
            'price' => 5.0,
            'description' => 'Mascarpone, barres KitKat et chocolat au lait.',
            'badge_class' => 'badge-new',
            'badge_text' => 'Nouveau',
            'img_key' => 'kitkat',
            'sort_order' => 7,
        ],
        [
            'id' => 'raffaello',
            'name' => 'Saveur Raffaello',
            'price' => 6.0,
            'description' => 'Crème coco-noisette, Raffaello et noix de coco. (+1€ supplément)',
            'badge_class' => 'badge-sup',
            'badge_text' => '+1€ supplément',
            'img_key' => 'raffaello',
            'sort_order' => 8,
        ],
    ];

    try {
        $insP = $pdo->prepare(
            'INSERT INTO products (id, name, price_eur, description, badge_class, badge_text, img_key, sort_order, is_active)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $insS = $pdo->prepare('INSERT INTO stock_levels (product_id, quantity) VALUES (?, 50)');

        foreach ($defs as $r) {
            $st = $pdo->prepare('SELECT 1 FROM products WHERE id = ? LIMIT 1');
            $st->execute([$r['id']]);
            if ($st->fetch(PDO::FETCH_ASSOC) === false) {
                $insP->execute([
                    $r['id'],
                    $r['name'],
                    $r['price'],
                    $r['description'],
                    $r['badge_class'],
                    $r['badge_text'],
                    $r['img_key'],
                    $r['sort_order'],
                    1,
                ]);
            }
            $st2 = $pdo->prepare('SELECT 1 FROM stock_levels WHERE product_id = ? LIMIT 1');
            $st2->execute([$r['id']]);
            if ($st2->fetch(PDO::FETCH_ASSOC) === false) {
                $insS->execute([$r['id']]);
            }
        }

        $pdo->exec("UPDATE products SET sort_order = 9 WHERE id = 'box1'");
        $pdo->exec("UPDATE products SET sort_order = 10 WHERE id = 'box_supreme'");
    } catch (Throwable) {
        /* ignore — droits MySQL ou schéma partiel */
    }
}
