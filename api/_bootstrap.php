<?php
/**
 * Initialisation commune des endpoints API (session + PDO).
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init_public.php';

header('X-Content-Type-Options: nosniff');

try {
    $pdo = require dirname(__DIR__) . '/config/db.php';
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Configuration base de données invalide.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once dirname(__DIR__) . '/includes/ensure_box_supreme.php';
tiramii_ensure_box_supreme($pdo);

/**
 * @param mixed $data
 */
function tiramii_json_response($data, int $code = 200): void
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function tiramii_cleanup_reservations(PDO $pdo): void
{
    $pdo->exec('DELETE FROM stock_reservations WHERE expires_at < NOW()');
}
