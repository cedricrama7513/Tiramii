<?php
/**
 * POST JSON — demande depuis l’espace pro (restaurants / B2B).
 * En-tête : X-CSRF-Token
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

// Honeypot anti-spam
$hp = isset($data['website']) ? trim((string) $data['website']) : '';
if ($hp !== '') {
    tiramii_json_response(['ok' => true]);
}

$restaurant = mb_substr(trim((string) ($data['restaurant_name'] ?? '')), 0, 255);
$contact = mb_substr(trim((string) ($data['contact_name'] ?? '')), 0, 160);
$email = mb_substr(trim((string) ($data['email'] ?? '')), 0, 255);
$phone = trim((string) ($data['phone'] ?? ''));
$city = mb_substr(trim((string) ($data['city'] ?? '')), 0, 120);
$intent = mb_substr(trim((string) ($data['intent'] ?? '')), 0, 80);
$message = mb_substr(trim((string) ($data['message'] ?? '')), 0, 4000);

$allowedIntent = ['ouverture', 'commande', 'infos'];
if ($intent === '') {
    $intent = 'infos';
}
if (!in_array($intent, $allowedIntent, true)) {
    tiramii_json_response(['ok' => false, 'error' => 'Type de demande invalide.'], 422);
}

if ($restaurant === '' || $contact === '' || $email === '' || $phone === '') {
    tiramii_json_response(['ok' => false, 'error' => 'Établissement, contact, e-mail et téléphone obligatoires.'], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    tiramii_json_response(['ok' => false, 'error' => 'Adresse e-mail invalide.'], 422);
}

if (!preg_match('/^[0-9\\s+().\\-]{8,22}$/', $phone)) {
    tiramii_json_response(['ok' => false, 'error' => 'Téléphone invalide.'], 422);
}

require_once dirname(__DIR__) . '/includes/pro_b2b.php';
tiramii_ensure_pro_tables($pdo);

$intentLabel = [
    'ouverture' => 'Ouvrir un compte / devenir client',
    'commande' => 'Première commande / devis',
    'infos' => 'Informations / autre',
][$intent] ?? ($intent !== '' ? $intent : '—');

try {
    $ins = $pdo->prepare(
        'INSERT INTO pro_leads (restaurant_name, contact_name, email, phone, city, intent, message, created_at)
         VALUES (?,?,?,?,?,?,?, NOW())'
    );
    $ins->execute([$restaurant, $contact, $email, $phone, $city, $intent, $message]);
} catch (Throwable) {
    tiramii_json_response(['ok' => false, 'error' => 'Enregistrement impossible. Réessayez plus tard.'], 500);
}

$configFile = dirname(__DIR__) . '/config/config.php';
if (is_readable($configFile)) {
    /** @var array<string, mixed> $appCfg */
    $appCfg = require $configFile;
    require_once dirname(__DIR__) . '/includes/pro_lead_notify.php';
    try {
        tiramii_notify_pro_lead($appCfg, [
            'restaurant' => $restaurant,
            'contact' => $contact,
            'email' => $email,
            'phone' => $phone,
            'city' => $city,
            'intent' => $intentLabel,
            'message' => $message,
        ]);
    } catch (Throwable) {
        /* ne pas bloquer le visiteur */
    }
}

tiramii_json_response(['ok' => true, 'message' => 'Demande enregistrée. Nous vous recontactons rapidement.']);
