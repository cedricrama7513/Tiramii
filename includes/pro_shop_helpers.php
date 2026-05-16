<?php
/**
 * Tarifs et affichage boutique pour compte pro connecté.
 */
declare(strict_types=1);

/** Condition SQL : produit pro commandable (prix pro renseigné). */
function tiramii_pro_catalog_sql_condition(): string
{
    return 'price_pro_eur IS NOT NULL AND price_pro_eur > 0';
}

/**
 * @return array<int, array<string, mixed>>
 */
function tiramii_fetch_catalog_products(PDO $pdo, array $excludeIds, bool $useProPrices): array
{
    $notIn = implode(',', array_fill(0, count($excludeIds), '?'));
    $params = $excludeIds;

    if ($useProPrices) {
        $proWhere = tiramii_pro_catalog_sql_condition();
        try {
            $stmt = $pdo->prepare(
                "SELECT id, name, price_pro_eur AS price_eur, description, badge_class, badge_text, img_key, sort_order
                 FROM products
                 WHERE is_active = 1 AND id NOT IN ($notIn) AND {$proWhere}
                 ORDER BY sort_order ASC, id ASC"
            );
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT id, name, price_eur, description, badge_class, badge_text, img_key, sort_order
             FROM products WHERE is_active = 1 AND id NOT IN ($notIn) ORDER BY sort_order ASC, id ASC"
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }
}

/**
 * Catalogue JSON devis / tarifs pro (uniquement articles avec prix pro).
 *
 * @return array<int, array{id: string, name: string, price_public: float, price_pro: float}>
 */
function tiramii_fetch_pro_devis_catalog(PDO $pdo): array
{
    $catalog = [];
    try {
        $proRows = $pdo
            ->query(
                'SELECT id, name, price_eur, pro_price_eur
                 FROM products
                 WHERE is_active = 1 AND ' . tiramii_pro_catalog_sql_condition() . '
                 ORDER BY sort_order ASC, id ASC'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }

    foreach ($proRows as $p) {
        $catalog[] = [
            'id' => (string) $p['id'],
            'name' => (string) $p['name'],
            'price_public' => round((float) $p['price_eur'], 2),
            'price_pro' => round((float) $p['pro_price_eur'], 2),
        ];
    }

    return $catalog;
}

/**
 * @param array<string, float> $priceById product_id => unit price pro
 */
function tiramii_validate_pro_cart_prices(array $lines, array $priceById): ?string
{
    $boxIds = ['box1', 'box2', 'box_supreme'];
    foreach ($lines as $line) {
        $pid = (string) ($line['id'] ?? '');
        if ($pid === '' || in_array($pid, $boxIds, true)) {
            return 'Les box particuliers ne sont pas disponibles en commande pro.';
        }
        if (!isset($priceById[$pid])) {
            $name = (string) ($line['name'] ?? $pid);

            return 'Produit non disponible en tarif pro : ' . $name;
        }
        $expected = round($priceById[$pid], 2);
        $got = round((float) ($line['unit_price'] ?? 0), 2);
        if (abs($expected - $got) > 0.02) {
            return 'Prix invalide pour ' . (string) ($line['name'] ?? $pid) . '. Rechargez la page.';
        }
    }

    return null;
}

function tiramii_render_pro_shop_banner(?array $proAccount): string
{
    if ($proAccount === null) {
        return '';
    }
    $name = trim((string) ($proAccount['restaurant_name'] ?? ''));
    $who = $name !== '' ? h($name) : 'votre établissement';

    return '<div class="pro-shop-banner" role="status">'
        . '<strong>Compte pro connecté</strong> — '
        . $who
        . ' · catalogue <strong>tarifs partenaires</strong> uniquement. '
        . '<a href="devis.php">Demande de devis</a>'
        . '</div>';
}
