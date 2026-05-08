<?php
/**
 * Rendu HTML du catalogue (même structure que le template JS d’origine).
 */
declare(strict_types=1);

/**
 * @param array<int, array<string, mixed>> $products
 * @param array<string, int> $stockMap product_id => quantity (999 = illimité ; clé absente = 0)
 * @param array<string, string> $imgs img_key => data URI
 */
function tiramii_render_product_grid(array $products, array $stockMap, array $imgs): string
{
    $html = '';
    foreach ($products as $p) {
        $id = (string) $p['id'];
        $qty = array_key_exists($id, $stockMap) ? (int) $stockMap[$id] : 0;
        $available = ($qty === 999 || $qty > 0);
        $low = ($qty !== 999 && $qty <= 3 && $qty > 0);
        $imgKey = (string) ($p['img_key'] ?? 'oreo');
        $imgSrc = $imgs[$imgKey] ?? ($imgs['oreo'] ?? '');
        if ($imgSrc === '') {
            $imgSrc = 'data:image/svg+xml,' . rawurlencode(
                '<svg xmlns="http://www.w3.org/2000/svg" width="560" height="360" viewBox="0 0 560 360">'
                . '<rect fill="#ece6de" width="560" height="360"/>'
                . '<text x="280" y="175" text-anchor="middle" fill="#A68966" font-family="system-ui,sans-serif" font-size="15">Casa Dessert</text>'
                . '<text x="280" y="202" text-anchor="middle" fill="#5c5650" font-family="system-ui,sans-serif" font-size="12">Photo à configurer</text>'
                . '</svg>'
            );
        }
        $price = number_format((float) $p['price_eur'], 2, ',', '');
        $name = (string) $p['name'];
        $badgeClass = (string) $p['badge_class'];
        $badgeText = (string) $p['badge_text'];

        $sold = $available ? '' : '<div class="sold-overlay">Épuisé</div>';
        $stockBadge = $low ? '<div class="stock-badge">⚠️ Plus que ' . h((string) $qty) . '</div>' : '';
        $disabled = $available ? '' : ' disabled';
        $btnLabel = $available ? '🛒 Ajouter' : '❌ Épuisé';

        $html .= '<div class="product-card reveal" data-product-id="' . h($id) . '">';
        $html .= '<div class="product-img">';
        $html .= '<img src="' . h($imgSrc) . '" alt="' . h($name) . '" loading="lazy">';
        $html .= '<div class="product-img-overlay"></div>';
        $html .= '<span class="badge ' . h($badgeClass) . '">' . h($badgeText) . '</span>';
        $html .= $sold;
        $html .= $stockBadge;
        $html .= '</div>';
        $html .= '<div class="product-info">';
        $html .= '<div class="product-name">' . h($name) . '</div>';
        $html .= '<div class="product-footer">';
        $html .= '<div class="product-price">' . h($price) . '€</div>';
        $html .= '<button class="add-to-cart" id="btn-' . h($id) . '" onclick="addToCart(\'' . h($id) . '\')"' . $disabled . '>';
        $html .= $btnLabel;
        $html .= '</button>';
        $html .= '</div></div></div>';
    }
    return $html;
}
