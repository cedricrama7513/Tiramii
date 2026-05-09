<?php
/**
 * POST JSON — déconnexion espace pro.
 */
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tiramii_json_response(['ok' => false, 'error' => 'Méthode non autorisée'], 405);
}

if (!csrf_verify(csrf_token_from_request())) {
    tiramii_json_response(['ok' => false, 'error' => 'Jeton CSRF invalide'], 403);
}

require_once dirname(__DIR__) . '/includes/pro_accounts.php';
tiramii_pro_clear_session();

tiramii_json_response(['ok' => true]);
