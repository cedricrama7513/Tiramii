<?php
/**
 * POST JSON — enregistrement demande de devis pro + notification e-mail.
 * En-tête : X-CSRF-Token
 */
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/includes/pro_quote_notify.php';

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

$company = trim((string) ($data['company_name'] ?? ''));
$contact = trim((string) ($data['contact_name'] ?? ''));
$email = trim((string) ($data['email'] ?? ''));
$phone = trim((string) ($data['phone'] ?? ''));
$message = trim((string) ($data['message'] ?? ''));

if ($company === '' || mb_strlen($company) > 255) {
    tiramii_json_response(['ok' => false, 'error' => 'Nom d’établissement obligatoire.'], 422);
}
if ($contact === '' || mb_strlen($contact) > 160) {
    tiramii_json_response(['ok' => false, 'error' => 'Nom du contact obligatoire.'], 422);
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
    tiramii_json_response(['ok' => false, 'error' => 'E-mail invalide.'], 422);
}
if ($phone === '' || !preg_match('/^[0-9\s+().\-]{8,22}$/', $phone)) {
    tiramii_json_response(['ok' => false, 'error' => 'Téléphone invalide.'], 422);
}
if ($message === '' || mb_strlen($message) < 10) {
    tiramii_json_response(['ok' => false, 'error' => 'Décrivez votre projet en quelques lignes.'], 422);
}
if (mb_strlen($message) > 4000) {
    tiramii_json_response(['ok' => false, 'error' => 'Description du projet trop longue.'], 422);
}

$linesJson = '[]';
$estimated = 0.0;
$hasSurDevis = false;

$configFile = dirname(__DIR__) . '/config/config.php';
if (!is_readable($configFile)) {
    tiramii_json_response(['ok' => false, 'error' => 'Configuration absente.'], 500);
}
/** @var array<string, mixed> $appCfg */
$appCfg = require $configFile;

try {
    $ins = $pdo->prepare(
        'INSERT INTO pro_quote_requests (
            company_name, contact_name, email, phone, message, lines_json,
            estimated_total_eur, has_sur_devis, created_at
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $ins->execute([
        $company,
        $contact,
        $email,
        $phone,
        $message,
        $linesJson,
        round($estimated, 2),
        $hasSurDevis ? 1 : 0,
    ]);
    $newId = (int) $pdo->lastInsertId();
} catch (Throwable) {
    tiramii_json_response(['ok' => false, 'error' => 'Enregistrement impossible. Réessayez plus tard.'], 500);
}

tiramii_notify_pro_quote_request(
    $appCfg,
    $newId,
    $company,
    $contact,
    $email,
    $phone,
    $message,
    '',
    $estimated,
    $hasSurDevis
);

tiramii_json_response([
    'ok' => true,
    'quote_id' => $newId,
    'estimated_total_eur' => round($estimated, 2),
    'has_sur_devis' => $hasSurDevis,
]);
