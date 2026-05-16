<?php
/**
 * POST JSON — commande espace pro (tarifs COALESCE(price_pro_eur, price_eur), pas de règle livraison particuliers).
 * En-tête : X-CSRF-Token — session compte pro active requise.
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
require_once dirname(__DIR__) . '/includes/ensure_pro_prices.php';
require_once dirname(__DIR__) . '/includes/pro_shop_helpers.php';
tiramii_ensure_pro_account_tables($pdo);
tiramii_ensure_pro_price_column($pdo);

$account = tiramii_pro_current_account($pdo);
if ($account === null) {
    tiramii_json_response(['ok' => false, 'error' => 'Connexion pro requise.'], 401);
}

$proAccountId = (int) $account['id'];
$proRestaurant = (string) $account['restaurant_name'];

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: 'null', true);
if (!is_array($data)) {
    tiramii_json_response(['ok' => false, 'error' => 'JSON invalide'], 400);
}

$sessionId = isset($data['session_id']) ? (string) $data['session_id'] : '';
if (!preg_match('/^[a-f0-9\\-]{36}$/i', $sessionId)) {
    tiramii_json_response(['ok' => false, 'error' => 'session_id invalide'], 400);
}

$first = trim((string) ($data['first_name'] ?? ''));
$last = trim((string) ($data['last_name'] ?? ''));
$phone = trim((string) ($data['phone'] ?? ''));
$address = trim((string) ($data['address'] ?? ''));
$zip = trim((string) ($data['zip'] ?? ''));
$city = trim((string) ($data['city'] ?? ''));
$deliveryTime = trim((string) ($data['delivery_time'] ?? ''));
$note = trim((string) ($data['note'] ?? ''));
$payment = trim((string) ($data['payment_method'] ?? ''));

if ($first === '' || $phone === '' || $address === '' || trim($zip) === '') {
    tiramii_json_response(['ok' => false, 'error' => 'Prénom, téléphone, adresse et code postal obligatoires.'], 422);
}

if (mb_strlen($first) > 80 || mb_strlen($last) > 80) {
    tiramii_json_response(['ok' => false, 'error' => 'Nom trop long.'], 422);
}

if (!preg_match('/^[0-9\\s+().\\-]{8,22}$/', $phone)) {
    tiramii_json_response(['ok' => false, 'error' => 'Téléphone invalide.'], 422);
}

if (mb_strlen($address) > 255 || mb_strlen($zip) > 12 || mb_strlen($city) > 80) {
    tiramii_json_response(['ok' => false, 'error' => 'Adresse invalide.'], 422);
}

$allowedPay = ['cash', 'virement', 'wero'];
if (!in_array($payment, $allowedPay, true)) {
    tiramii_json_response(['ok' => false, 'error' => 'Mode de paiement invalide.'], 422);
}

$cart = $data['cart'] ?? null;
if (!is_array($cart) || $cart === []) {
    tiramii_json_response(['ok' => false, 'error' => 'Panier vide.'], 422);
}

$retiredProductIds = ['box2'];
$rawLines = [];
foreach ($cart as $row) {
    if (!is_array($row)) {
        continue;
    }
    $pid = isset($row['id']) ? (string) $row['id'] : '';
    if (in_array($pid, $retiredProductIds, true)) {
        tiramii_json_response(['ok' => false, 'error' => 'Un article du panier n’est plus disponible.'], 422);
    }
    $qty = isset($row['qty']) ? (int) $row['qty'] : 0;
    $name = isset($row['name']) ? (string) $row['name'] : '';
    if ($pid === '' || $qty < 1 || $qty > 99 || $name === '') {
        tiramii_json_response(['ok' => false, 'error' => 'Ligne panier invalide.'], 422);
    }
    $stockKey = isset($row['stockId']) ? (string) $row['stockId'] : $pid;
    $rawLines[] = [
        'id' => $pid,
        'stock_key' => $stockKey,
        'name' => $name,
        'qty' => $qty,
        'box_label' => isset($row['box_label']) ? (string) $row['box_label'] : null,
    ];
}

if ($rawLines === []) {
    tiramii_json_response(['ok' => false, 'error' => 'Panier invalide.'], 422);
}

$pids = array_values(array_unique(array_map(static fn (array $l): string => $l['id'], $rawLines)));
$placeholders = implode(',', array_fill(0, count($pids), '?'));
try {
    $stP = $pdo->prepare(
        "SELECT id, name, price_pro_eur AS eff_price
         FROM products WHERE id IN ($placeholders) AND is_active = 1 AND " . tiramii_pro_catalog_sql_condition()
    );
    $stP->execute($pids);
    $priceMap = [];
    while ($pr = $stP->fetch(PDO::FETCH_ASSOC)) {
        $priceMap[(string) $pr['id']] = [
            'name' => (string) $pr['name'],
            'price' => round((float) $pr['eff_price'], 2),
        ];
    }
} catch (Throwable) {
    tiramii_json_response(['ok' => false, 'error' => 'Catalogue indisponible.'], 500);
}

$lines = [];
$total = 0.0;
foreach ($rawLines as $line) {
    $pid = $line['id'];
    if (!isset($priceMap[$pid])) {
        tiramii_json_response(['ok' => false, 'error' => 'Produit inconnu ou inactif : ' . $line['name']], 422);
    }
    $unit = $priceMap[$pid]['price'];
    $lines[] = [
        'id' => $pid,
        'stock_key' => $line['stock_key'],
        'name' => $priceMap[$pid]['name'],
        'qty' => $line['qty'],
        'unit_price' => $unit,
        'box_label' => $line['box_label'],
    ];
    $total += $unit * $line['qty'];
}

$notePro = 'COMMANDE PRO — ' . $proRestaurant . "\n\n" . $note;
$noteToStore = mb_substr($notePro, 0, 65000);

$hasProCol = tiramii_orders_has_pro_account_id($pdo);

try {
    $pdo->beginTransaction();
    tiramii_cleanup_reservations($pdo);

    $stmt = $pdo->prepare('SELECT items_json FROM stock_reservations WHERE session_id = ? AND expires_at >= NOW()');
    $stmt->execute([$sessionId]);
    $resRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$resRow) {
        $pdo->rollBack();
        tiramii_json_response(['ok' => false, 'error' => 'La réservation de stock a expiré. Merci de recommencer.'], 409);
    }
    $reservedItems = json_decode((string) $resRow['items_json'], true);
    if (!is_array($reservedItems)) {
        $reservedItems = [];
    }

    foreach ($lines as $line) {
        $key = $line['stock_key'];
        $need = $line['qty'];
        $have = (int) ($reservedItems[$key] ?? 0);
        if ($have < $need) {
            $pdo->rollBack();
            tiramii_json_response(['ok' => false, 'error' => 'Réservation invalide pour ' . $line['name'] . '.'], 409);
        }
    }

    $stock = [];
    $q = $pdo->query('SELECT product_id, quantity FROM stock_levels FOR UPDATE');
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $stock[$r['product_id']] = (int) $r['quantity'];
    }

    $upStock = $pdo->prepare(
        'UPDATE stock_levels SET quantity = quantity - :need WHERE product_id = :pid AND quantity < 999 AND quantity >= :need2'
    );
    $readQty = $pdo->prepare('SELECT quantity FROM stock_levels WHERE product_id = ?');
    foreach ($lines as $line) {
        $key = $line['stock_key'];
        $need = $line['qty'];
        if (!array_key_exists($key, $stock)) {
            $pdo->rollBack();
            tiramii_json_response(
                ['ok' => false, 'error' => 'Stock non configuré pour « ' . $line['name'] . ' ».'],
                500
            );
        }
        $cur = (int) $stock[$key];
        if ($cur === 999) {
            continue;
        }
        $expected = $cur - $need;
        $upStock->execute(['need' => $need, 'pid' => $key, 'need2' => $need]);
        $readQty->execute([$key]);
        $rowQ = $readQty->fetch(PDO::FETCH_ASSOC);
        $readQty->closeCursor();
        if ($rowQ === false || (int) $rowQ['quantity'] !== $expected) {
            $pdo->rollBack();
            tiramii_json_response(['ok' => false, 'error' => 'Stock insuffisant pour ' . $line['name'] . '.'], 409);
        }
        $stock[$key] = $expected;
    }

    $pdo->prepare('DELETE FROM stock_reservations WHERE session_id = ?')->execute([$sessionId]);

    if ($hasProCol) {
        $insO = $pdo->prepare(
            'INSERT INTO orders (pro_account_id, first_name, last_name, phone, address_line, zip, city, delivery_time, note, payment_method, total_eur, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?, NOW())'
        );
        $insO->execute([
            $proAccountId,
            $first,
            $last,
            $phone,
            $address,
            $zip,
            $city,
            $deliveryTime,
            $noteToStore,
            $payment,
            round($total, 2),
        ]);
    } else {
        $insO = $pdo->prepare(
            'INSERT INTO orders (first_name, last_name, phone, address_line, zip, city, delivery_time, note, payment_method, total_eur, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?, NOW())'
        );
        $insO->execute([
            $first,
            $last,
            $phone,
            $address,
            $zip,
            $city,
            $deliveryTime,
            $noteToStore,
            $payment,
            round($total, 2),
        ]);
    }
    $orderId = (int) $pdo->lastInsertId();

    $insL = $pdo->prepare(
        'INSERT INTO order_items (order_id, product_id, product_label, quantity, unit_price_eur, box_label) VALUES (?,?,?,?,?,?)'
    );
    foreach ($lines as $line) {
        $insL->execute([
            $orderId,
            $line['id'],
            $line['name'],
            $line['qty'],
            $line['unit_price'],
            $line['box_label'],
        ]);
    }

    $pdo->commit();

    $cfgPath = dirname(__DIR__) . '/config/config.php';
    $appCfg = [];
    if (is_readable($cfgPath)) {
        /** @var array $loaded */
        $loaded = require $cfgPath;
        $appCfg = is_array($loaded) ? $loaded : [];
    }
    require_once dirname(__DIR__) . '/includes/order_notify.php';
    try {
        tiramii_notify_new_order(
            $appCfg,
            $orderId,
            $first,
            $last,
            $phone,
            $address,
            $zip,
            $city,
            $deliveryTime,
            $noteToStore,
            $payment,
            round($total, 2),
            $lines,
            true,
            $proRestaurant
        );
    } catch (Throwable) {
        /* */
    }

    tiramii_json_response(['ok' => true, 'order_id' => $orderId]);
} catch (Throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    tiramii_json_response(['ok' => false, 'error' => 'Erreur lors de la commande'], 500);
}
