<?php
/**
 * POST JSON — synchronise la réservation de panier (5 min, même logique qu’avant).
 * Corps : { "session_id": "uuid", "items": { "oreo": 2, "box1": 1 } }
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

$items = $data['items'] ?? [];
if (!is_array($items)) {
    tiramii_json_response(['ok' => false, 'error' => 'items invalide'], 400);
}

$retiredIds = ['box2'];
$clean = [];
foreach ($items as $k => $v) {
    $key = preg_replace('/[^a-z0-9_]/i', '', (string) $k);
    if ($key === '' || in_array($key, $retiredIds, true)) {
        continue;
    }
    $n = (int) $v;
    if ($n > 0) {
        $clean[$key] = $n;
    }
}

try {
    $pdo->beginTransaction();
    tiramii_cleanup_reservations($pdo);

    $del = $pdo->prepare('DELETE FROM stock_reservations WHERE session_id = ?');
    $del->execute([$sessionId]);

    if ($clean === []) {
        $pdo->commit();
        tiramii_json_response(['ok' => true]);
    }

    // Stock courant
    $stock = [];
    foreach ($pdo->query('SELECT product_id, quantity FROM stock_levels')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $stock[$r['product_id']] = (int) $r['quantity'];
    }

    // Réservations actives (hors cette session)
    $stmt = $pdo->prepare(
        'SELECT session_id, items_json FROM stock_reservations WHERE expires_at >= NOW() AND session_id <> ?'
    );
    $stmt->execute([$sessionId]);
    $reservedByOthers = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $decoded = json_decode((string) $row['items_json'], true);
        if (!is_array($decoded)) {
            continue;
        }
        foreach ($decoded as $pid => $q) {
            $pid = (string) $pid;
            $reservedByOthers[$pid] = ($reservedByOthers[$pid] ?? 0) + (int) $q;
        }
    }

    foreach ($clean as $pid => $want) {
        $sqty = array_key_exists($pid, $stock) ? (int) $stock[$pid] : 999;
        if ($sqty === 999) {
            continue;
        }
        $others = (int) ($reservedByOthers[$pid] ?? 0);
        $avail = max(0, $sqty - $others);
        if ($want > $avail) {
            $pdo->rollBack();
            tiramii_json_response(['ok' => false, 'error' => 'Stock réservé/insuffisant.'], 409);
        }
    }

    $expires = (new DateTimeImmutable('+5 minutes'))->format('Y-m-d H:i:s');
    $updated = (new DateTimeImmutable())->format('Y-m-d H:i:s');
    $ins = $pdo->prepare(
        'INSERT INTO stock_reservations (session_id, items_json, expires_at, updated_at) VALUES (?,?,?,?)'
    );
    $ins->execute([$sessionId, json_encode($clean, JSON_UNESCAPED_UNICODE), $expires, $updated]);

    $pdo->commit();
    tiramii_json_response(['ok' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    tiramii_json_response(['ok' => false, 'error' => 'Erreur serveur'], 500);
}
