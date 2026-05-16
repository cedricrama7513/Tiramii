<?php
/**
 * Utilitaires globaux (échappement, CSRF).
 */
declare(strict_types=1);

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Lit le jeton CSRF depuis en-tête (API JSON) ou POST classique.
 */
function csrf_token_from_request(): ?string
{
    $h = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (is_string($h) && $h !== '') {
        return $h;
    }
    $p = $_POST['csrf_token'] ?? null;
    return is_string($p) ? $p : null;
}

/**
 * Logo marque : fichier dans assets/img/logo-casa-dessert.{svg,png,webp}, sinon initiale C (sauf mentions légales : rien).
 *
 * @param 'nav'|'admin'|'legal' $variant
 */
function brand_logo_markup(string $variant = 'nav'): string
{
    $root = dirname(__DIR__);
    $names = ['logo-casa-dessert.svg', 'logo-casa-dessert.png', 'logo-casa-dessert.webp'];
    foreach ($names as $base) {
        $path = $root . '/assets/img/' . $base;
        if (!is_readable($path)) {
            continue;
        }
        $v = (string) filemtime($path);
        $rel = 'assets/img/' . $base;
        $classes = 'brand-logo-img';
        if ($variant === 'admin') {
            $classes .= ' brand-logo-img--admin';
        } elseif ($variant === 'legal') {
            $classes .= ' brand-logo-img--legal';
        }
        return '<span class="brand-logo-wrap"><img src="' . h($rel) . '?v=' . h($v) . '" alt="Casa Dessert" class="' . h($classes) . '" decoding="async"></span>';
    }
    if ($variant === 'admin') {
        return '<div class="logo">C</div>';
    }
    if ($variant === 'legal') {
        return '';
    }
    return '<div class="logo-circle"><span>C</span></div>';
}
