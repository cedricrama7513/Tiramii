<?php
/**
 * Tarifs et affichage boutique pour compte pro connecté.
 */
declare(strict_types=1);

/**
 * @return array<int, array<string, mixed>>
 */
function tiramii_fetch_catalog_products(PDO $pdo, array $excludeIds, bool $useProPrices): array
{
    $notIn = implode(',', array_fill(0, count($excludeIds), '?'));
    $priceCol = $useProPrices
        ? 'COALESCE(price_pro_eur, price_eur) AS price_eur'
        : 'price_eur';

    try {
        $stmt = $pdo->prepare(
            "SELECT id, name, {$priceCol}, description, badge_class, badge_text, img_key, sort_order
             FROM products WHERE is_active = 1 AND id NOT IN ($notIn) ORDER BY sort_order ASC, id ASC"
        );
        $stmt->execute($excludeIds);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        if (!$useProPrices) {
            return [];
        }
        $stmt = $pdo->prepare(
            "SELECT id, name, price_eur, description, badge_class, badge_text, img_key, sort_order
             FROM products WHERE is_active = 1 AND id NOT IN ($notIn) ORDER BY sort_order ASC, id ASC"
        );
        $stmt->execute($excludeIds);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
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
        . ' · les prix affichés sont vos <strong>tarifs partenaires</strong>. '
        . '<a href="devis.php">Demande de devis</a>'
        . '</div>';
}
