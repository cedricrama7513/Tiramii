<?php
/**
 * POST JSON — validation commande, déstockage, enregistrement BDD.
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

if ($first === '' || $phone === '' || $address === '') {
    tiramii_json_response(['ok' => false, 'error' => 'Prénom, téléphone et adresse obligatoires.'], 422);
}

if (mb_strlen($first) > 80 || mb_strlen($last) > 80) {
    tiramii_json_response(['ok' => false, 'error' => 'Nom trop long.'], 422);
}

if (!preg_match('/^[0-9\\s+().\\-]{8,20}$/', $phone)) {
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
$lines = [];
$total = 0.0;
foreach ($cart as $row) {
    if (!is_array($row)) {
        continue;
    }
    $pid = isset($row['id']) ? (string) $row['id'] : '';
    if (in_array($pid, $retiredProductIds, true)) {
        tiramii_json_response(['ok' => false, 'error' => 'Un article du panier n’est plus disponible. Videz le panier et rechargez la page.'], 422);
    }
    $qty = isset($row['qty']) ? (int) $row['qty'] : 0;
    $price = isset($row['price']) ? (float) $row['price'] : 0.0;
    $name = isset($row['name']) ? (string) $row['name'] : '';
    if ($pid === '' || $qty < 1 || $qty > 99 || $price < 0 || $price > 500 || $name === '') {
        tiramii_json_response(['ok' => false, 'error' => 'Ligne panier invalide.'], 422);
    }
    $stockKey = isset($row['stockId']) ? (string) $row['stockId'] : $pid;
    $lines[] = [
        'id' => $pid,
        'stock_key' => $stockKey,
        'name' => $name,
        'qty' => $qty,
        'unit_price' => $price,
        'box_label' => isset($row['box_label']) ? (string) $row['box_label'] : null,
    ];
    $total += $price * $qty;
}

if ($lines === []) {
    tiramii_json_response(['ok' => false, 'error' => 'Panier invalide.'], 422);
}

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

    foreach ($lines as $line) {
        $key = $line['stock_key'];
        $need = $line['qty'];
        $cur = array_key_exists($key, $stock) ? (int) $stock[$key] : 999;
        if ($cur !== 999) {
            if ($cur < $need) {
                $pdo->rollBack();
                tiramii_json_response(['ok' => false, 'error' => 'Stock insuffisant pour ' . $line['name'] . '.'], 409);
            }
            $newQty = $cur - $need;
            $up = $pdo->prepare('UPDATE stock_levels SET quantity = ? WHERE product_id = ?');
            $up->execute([$newQty, $key]);
            $stock[$key] = $newQty;
        }
    }

    $pdo->prepare('DELETE FROM stock_reservations WHERE session_id = ?')->execute([$sessionId]);

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
        $note,
        $payment,
        round($total, 2),
    ]);
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
    tiramii_json_response(['ok' => true, 'order_id' => $orderId]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    tiramii_json_response(['ok' => false, 'error' => 'Erreur lors de la commande'], 500);
}
