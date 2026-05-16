<?php
/**
 * Menu principal (particuliers + état compte pro).
 */
declare(strict_types=1);

/**
 * @param array<string, mixed>|null $proAccount Compte pro actif (session)
 */
function tiramii_render_nav_html(?array $proAccount = null): string
{
    $isPro = $proAccount !== null;
    $restaurant = $isPro ? trim((string) ($proAccount['restaurant_name'] ?? '')) : '';

    $proLinks = '';
    if ($isPro) {
        $label = $restaurant !== '' ? h($restaurant) : 'Compte pro';
        $proLinks = '<li><a href="index.php#catalogue" title="Tarifs pro actifs">Tarifs pro</a></li>'
            . '<li><a href="devis.php">Devis</a></li>'
            . '<li><a href="#" id="navProLogout" title="Se déconnecter">Déconnexion</a></li>';
    } else {
        $proLinks = '<li><a href="index.php?page=pro">Espace pro</a></li>'
            . '<li><a href="devis.php">Devis</a></li>'
            . '<li><a href="pro-login.php">Connexion pro</a></li>';
    }

    $mobilePro = $isPro
        ? '<a href="index.php#catalogue" class="nav-pro-mobile nav-pro-mobile--on" title="' . h($restaurant !== '' ? $restaurant : 'Tarifs pro') . '">Pro ✓</a>'
        : '<a href="pro-login.php" class="nav-pro-mobile" title="Connexion professionnels">Pro</a>';

    return '<nav>
  <a href="index.php" class="nav-logo">__LOGO_MARK__</a>
  <ul class="nav-links">
    <li><a href="index.php#catalogue">Catalogue</a></li>
    <li><a href="index.php#box">Box</a></li>
    <li><a href="index.php#avis">Avis</a></li>
    <li><a href="index.php#contact">Contact</a></li>
    ' . $proLinks . '
  </ul>
  <div class="nav-actions">
    ' . $mobilePro . '
    <button class="cart-btn" onclick="toggleCart()">🛒 Panier <span class="cart-count" id="cartCount">0</span></button>
  </div>
</nav>';
}
