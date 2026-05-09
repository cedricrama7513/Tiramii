<?php
/**
 * POST JSON — inscription compte pro (statut : en attente de validation admin).
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

$hp = isset($data['website']) ? trim((string) $data['website']) : '';
if ($hp !== '') {
    tiramii_json_response(['ok' => true]);
}

$email = mb_strtolower(trim((string) ($data['email'] ?? '')));
$password = (string) ($data['password'] ?? '');
$restaurant = mb_substr(trim((string) ($data['restaurant_name'] ?? '')), 0, 255);
$first = mb_substr(trim((string) ($data['first_name'] ?? '')), 0, 80);
$last = mb_substr(trim((string) ($data['last_name'] ?? '')), 0, 80);
$phone = trim((string) ($data['phone'] ?? ''));
$address = mb_substr(trim((string) ($data['address'] ?? '')), 0, 255);
$zip = trim((string) ($data['zip'] ?? ''));
$city = mb_substr(trim((string) ($data['city'] ?? '')), 0, 80);

if ($restaurant === '' || $first === '' || $email === '' || $password === '') {
    tiramii_json_response(['ok' => false, 'error' => 'Établissement, prénom, e-mail et mot de passe obligatoires.'], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    tiramii_json_response(['ok' => false, 'error' => 'E-mail invalide.'], 422);
}

if (strlen($password) < 8) {
    tiramii_json_response(['ok' => false, 'error' => 'Mot de passe : 8 caractères minimum.'], 422);
}

if ($phone === '' || !preg_match('/^[0-9\\s+().\\-]{8,22}$/', $phone)) {
    tiramii_json_response(['ok' => false, 'error' => 'Téléphone invalide.'], 422);
}

if ($address === '' || $zip === '') {
    tiramii_json_response(['ok' => false, 'error' => 'Adresse et code postal obligatoires.'], 422);
}

require_once dirname(__DIR__) . '/includes/pro_accounts.php';
tiramii_ensure_pro_account_tables($pdo);

$hash = password_hash($password, PASSWORD_DEFAULT);
if ($hash === false) {
    tiramii_json_response(['ok' => false, 'error' => 'Erreur technique. Réessayez.'], 500);
}

try {
    $ins = $pdo->prepare(
        'INSERT INTO pro_accounts (email, password_hash, restaurant_name, first_name, last_name, phone, address_line, zip, city, status, created_at)
         VALUES (?,?,?,?,?,?,?,?,?,\'pending\', NOW())'
    );
    $ins->execute([$email, $hash, $restaurant, $first, $last, $phone, $address, $zip, $city]);
} catch (Throwable) {
    tiramii_json_response(['ok' => false, 'error' => 'Cet e-mail est déjà utilisé ou l’enregistrement a échoué.'], 409);
}

$configFile = dirname(__DIR__) . '/config/config.php';
if (is_readable($configFile)) {
    /** @var array<string, mixed> $appCfg */
    $appCfg = require $configFile;
    require_once dirname(__DIR__) . '/includes/pro_register_notify.php';
    try {
        tiramii_notify_pro_registration_pending($appCfg, [
            'email' => $email,
            'restaurant' => $restaurant,
            'contact' => trim($first . ' ' . $last),
            'phone' => $phone,
            'city' => $city,
        ]);
    } catch (Throwable) {
        /* */
    }
}

tiramii_json_response([
    'ok' => true,
    'message' => 'Demande enregistrée. Votre compte sera activé après validation (vous recevrez un e-mail si besoin).',
]);
