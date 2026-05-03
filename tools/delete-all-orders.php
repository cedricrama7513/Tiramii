<?php
/**
 * Supprime toutes les commandes (tests) + lignes order_items (CASCADE MySQL).
 * Vide aussi stock_reservations (sessions panier).
 *
 * Usage (ligne de commande uniquement) :
 *   php tools/delete-all-orders.php --confirm
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script doit être exécuté en CLI (pas depuis le navigateur).\n");
    exit(1);
}

if (!in_array('--confirm', $argv, true)) {
    fwrite(STDERR, "Usage : php tools/delete-all-orders.php --confirm\n");
    fwrite(STDERR, "Effet : DELETE sur orders (cascade order_items) + DELETE sur stock_reservations.\n");
    exit(1);
}

$root = dirname(__DIR__);
$config = $root . '/config/config.php';
if (!is_readable($config)) {
    fwrite(STDERR, "config/config.php introuvable.\n");
    exit(1);
}

/** @var PDO $pdo */
$pdo = require $root . '/config/db.php';

try {
    $before = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $pdo->beginTransaction();
    $pdo->exec('DELETE FROM stock_reservations');
    $pdo->exec('DELETE FROM orders');
    $pdo->commit();
    fwrite(STDOUT, "OK — {$before} commande(s) supprimée(s). order_items supprimées en cascade.\n");
    fwrite(STDOUT, "stock_reservations vidé.\n");
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
