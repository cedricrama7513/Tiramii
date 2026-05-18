<?php
/**
 * Suppression de commandes par jour calendaire (Europe/Paris).
 */
declare(strict_types=1);

require_once __DIR__ . '/admin_livreur_helpers.php';

/**
 * @return list<int>
 */
function tiramii_admin_order_ids_for_day(PDO $pdo, string $dayKey, DateTimeZone $tz): array
{
    $anchor = DateTimeImmutable::createFromFormat('!Y-m-d', $dayKey, $tz);
    if ($anchor === false) {
        return [];
    }
    $from = $anchor->modify('-60 days')->format('Y-m-d H:i:s');

    try {
        $st = $pdo->prepare('SELECT id, created_at FROM orders WHERE created_at >= ? ORDER BY id ASC');
        $st->execute([$from]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }

    $ids = [];
    foreach ($rows as $row) {
        if (tiramii_admin_livreur_day_key((string) ($row['created_at'] ?? ''), $tz) === $dayKey) {
            $ids[] = (int) $row['id'];
        }
    }

    return $ids;
}

/**
 * Supprime les commandes du jour (order_items en CASCADE).
 */
function tiramii_admin_delete_orders_for_day(PDO $pdo, string $dayKey, DateTimeZone $tz): int
{
    $ids = tiramii_admin_order_ids_for_day($pdo, $dayKey, $tz);
    if ($ids === []) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("DELETE FROM orders WHERE id IN ($placeholders)");
    $st->execute($ids);

    return count($ids);
}
