<?php
/**
 * GET — état stock + réservations actives (format compatible avec l’ancien client Firebase).
 */
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

tiramii_cleanup_reservations($pdo);

$stock = [];
foreach ($pdo->query('SELECT product_id, quantity FROM stock_levels')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $stock[$row['product_id']] = (int) $row['quantity'];
}

$reservations = [];
$stmt = $pdo->query(
    'SELECT session_id, items_json, expires_at, updated_at FROM stock_reservations WHERE expires_at >= NOW()'
);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $items = json_decode((string) $row['items_json'], true);
    if (!is_array($items)) {
        $items = [];
    }
    $expMs = (int) round((float) strtotime((string) $row['expires_at']) * 1000);
    $upMs = (int) round((float) strtotime((string) $row['updated_at']) * 1000);
    $reservations[$row['session_id']] = [
        'items' => $items,
        'expiresAt' => $expMs,
        'updatedAt' => $upMs,
    ];
}

tiramii_json_response(['stock' => $stock, 'reservations' => $reservations]);
