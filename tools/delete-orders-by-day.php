<?php
/**
 * Supprime les commandes d’un jour (fuseau Europe/Paris, comme l’admin).
 *
 * Usage :
 *   php tools/delete-orders-by-day.php --date=2026-05-19 --confirm
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI uniquement.\n");
    exit(1);
}

$date = '';
$confirm = false;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--date=')) {
        $date = substr($arg, 7);
    }
    if ($arg === '--confirm') {
        $confirm = true;
    }
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    fwrite(STDERR, "Usage : php tools/delete-orders-by-day.php --date=YYYY-MM-DD --confirm\n");
    exit(1);
}

if (!$confirm) {
    fwrite(STDERR, "Ajoutez --confirm pour exécuter la suppression.\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/includes/admin_orders_delete.php';

/** @var PDO $pdo */
$pdo = require $root . '/config/db.php';
$tz = new DateTimeZone('Europe/Paris');

$ids = tiramii_admin_order_ids_for_day($pdo, $date, $tz);
if ($ids === []) {
    fwrite(STDOUT, "Aucune commande pour le {$date} (Paris).\n");
    exit(0);
}

fwrite(STDOUT, 'Commandes à supprimer : #' . implode(', #', $ids) . "\n");

try {
    $pdo->beginTransaction();
    $n = tiramii_admin_delete_orders_for_day($pdo, $date, $tz);
    $pdo->commit();
    fwrite(STDOUT, "OK — {$n} commande(s) supprimée(s) pour le {$date}.\n");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
