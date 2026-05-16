<?php
/**
 * POST JSON — connexion espace pro (comptes actifs uniquement).
 */
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tiramii_json_response(['ok' => false, 'error' => 'Méthode non autorisée'], 405);
}

if (!csrf_verify(csrf_token_from_request())) {
    tiramii_json_response(['ok' => false, 'error' => 'Jeton CSRF invalide'], 403);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: 'null', true);
if (!is_array($data)) {
    tiramii_json_response(['ok' => false, 'error' => 'JSON invalide'], 400);
}

$email = mb_strtolower(trim((string) ($data['email'] ?? '')));
$password = (string) ($data['password'] ?? '');

if ($email === '' || $password === '') {
    tiramii_json_response(['ok' => false, 'error' => 'E-mail et mot de passe obligatoires.'], 422);
}

require_once dirname(__DIR__) . '/includes/pro_accounts.php';
tiramii_ensure_pro_account_tables($pdo);

$st = $pdo->prepare('SELECT id, password_hash, status, restaurant_name FROM pro_accounts WHERE email = ? LIMIT 1');
$st->execute([$email]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row || !password_verify($password, (string) $row['password_hash'])) {
    tiramii_json_response(['ok' => false, 'error' => 'Identifiants incorrects.'], 401);
}

$status = (string) ($row['status'] ?? '');
if ($status === 'pending') {
    tiramii_json_response(['ok' => false, 'error' => 'Compte en attente de validation par Casa Dessert.'], 403);
}
if ($status === 'suspended') {
    tiramii_json_response(['ok' => false, 'error' => 'Compte suspendu. Contactez-nous.'], 403);
}
if ($status !== 'active') {
    tiramii_json_response(['ok' => false, 'error' => 'Compte indisponible.'], 403);
}

if (function_exists('session_regenerate_id')) {
    @session_regenerate_id(true);
}

tiramii_pro_set_session((int) $row['id']);

tiramii_json_response([
    'ok' => true,
    'account' => [
        'restaurant_name' => (string) $row['restaurant_name'],
    ],
]);
