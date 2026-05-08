<?php
/**
 * POST JSON — enregistrement demande de devis pro + notification e-mail.
 * En-tête : X-CSRF-Token (ou champ csrf_token en form fallback non utilisé ici).
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
$linesIn = $data['lines'] ?? null;

if ($company === '' || mb_strlen($company) > 255) {
    tiramii_json_response(['ok' => false, 'error' => 'Nom d’établissement obligatoire (255 car. max).'], 422);
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
if (mb_strlen($message) > 4000) {
    tiramii_json_response(['ok' => false, 'error' => 'Message trop long.'], 422);
}
if (!is_array($linesIn) || $linesIn === []) {
    tiramii_json_response(['ok' => false, 'error' => 'Ajoutez au moins une ligne au devis.'], 422);
}
if (count($linesIn) > 80) {
    tiramii_json_response(['ok' => false, 'error' => 'Trop de lignes (max. 80).'], 422);
}

$ids = [];
foreach ($linesIn as $row) {
    if (!is_array($row)) {
        continue;
    }
    $pid = trim((string) ($row['product_id'] ?? ''));
    $qty = (int) ($row['qty'] ?? 0);
    if ($pid === '' || !preg_match('/^[a-z0-9_]{1,64}$/i', $pid)) {
        tiramii_json_response(['ok' => false, 'error' => 'Référence produit invalide.'], 422);
    }
    if ($qty < 1 || $qty > 9999) {
        tiramii_json_response(['ok' => false, 'error' => 'Quantité invalide.'], 422);
    }
    if (isset($ids[$pid])) {
        tiramii_json_response(['ok' => false, 'error' => 'Produit en double : ' . $pid], 422);
    }
    $ids[$pid] = $qty;
}

if ($ids === []) {
    tiramii_json_response(['ok' => false, 'error' => 'Lignes de devis invalides.'], 422);
}

$inClause = implode(',', array_fill(0, count($ids), '?'));
$st = $pdo->prepare(
    "SELECT id, name, price_eur, pro_price_eur FROM products WHERE is_active = 1 AND id IN ($inClause)"
);
$st->execute(array_keys($ids));
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
if (count($rows) !== count($ids)) {
    tiramii_json_response(['ok' => false, 'error' => 'Un ou plusieurs produits ne sont plus disponibles.'], 422);
}

$resolved = [];
$estimated = 0.0;
$hasSurDevis = false;
$linesTextParts = [];

foreach ($rows as $r) {
    $pid = (string) $r['id'];
    $qty = $ids[$pid];
    $name = (string) $r['name'];
    $pub = (float) $r['price_eur'];
    $proRaw = $r['pro_price_eur'];
    $unit = null;
    if ($proRaw !== null && $proRaw !== '') {
        $unit = round((float) $proRaw, 2);
    }
    $lineTotal = null;
    if ($unit !== null && $unit > 0) {
        $lineTotal = round($unit * $qty, 2);
        $estimated += $lineTotal;
    } else {
        $hasSurDevis = true;
    }

    $resolved[] = [
        'product_id' => $pid,
        'name' => $name,
        'qty' => $qty,
        'price_public_eur' => $pub,
        'price_pro_eur' => $unit,
        'line_total_eur' => $lineTotal,
    ];

    $priceLabel = $unit !== null && $unit > 0
        ? number_format($unit, 2, ',', ' ') . ' € HT × ' . $qty . ' = ' . number_format((float) $lineTotal, 2, ',', ' ') . ' €'
        : 'Sur devis × ' . $qty;
    $linesTextParts[] = '  · ' . $name . ' (' . $pid . ') — ' . $priceLabel;
}

$linesJson = json_encode($resolved, JSON_UNESCAPED_UNICODE);
if ($linesJson === false) {
    tiramii_json_response(['ok' => false, 'error' => 'Erreur d’encodage.'], 500);
}
$linesText = implode("\n", $linesTextParts);

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
    $linesText,
    $estimated,
    $hasSurDevis
);

tiramii_json_response([
    'ok' => true,
    'quote_id' => $newId,
    'estimated_total_eur' => round($estimated, 2),
    'has_sur_devis' => $hasSurDevis,
]);
