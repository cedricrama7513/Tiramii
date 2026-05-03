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
