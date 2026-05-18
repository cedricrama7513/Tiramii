<?php
/**
 * Helpers admin — vue livreur (tournées par jour).
 */
declare(strict_types=1);

function tiramii_admin_livreur_day_key(string $createdAt, DateTimeZone $tz): string
{
    $raw = trim($createdAt);
    if ($raw === '') {
        return (new DateTimeImmutable('now', $tz))->format('Y-m-d');
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return (new DateTimeImmutable('now', $tz))->format('Y-m-d');
    }

    return (new DateTimeImmutable('@' . $ts))->setTimezone($tz)->format('Y-m-d');
}

function tiramii_admin_livreur_parse_day(?string $jour, DateTimeZone $tz): string
{
    $jour = trim((string) $jour);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $jour) !== 1) {
        return (new DateTimeImmutable('now', $tz))->format('Y-m-d');
    }
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $jour, $tz);
    if ($dt === false) {
        return (new DateTimeImmutable('now', $tz))->format('Y-m-d');
    }

    return $dt->format('Y-m-d');
}

/** @return array{0: string, 1: string} [prev Y-m-d, next Y-m-d] */
function tiramii_admin_livreur_adjacent_days(string $dayKey, DateTimeZone $tz): array
{
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $dayKey, $tz);
    if ($dt === false) {
        $dt = new DateTimeImmutable('now', $tz);
    }

    return [
        $dt->modify('-1 day')->format('Y-m-d'),
        $dt->modify('+1 day')->format('Y-m-d'),
    ];
}

/**
 * @param array<string, mixed> $order
 */
function tiramii_admin_order_full_name(array $order): string
{
    $full = trim((string) ($order['first_name'] ?? '') . ' ' . (string) ($order['last_name'] ?? ''));

    return $full !== '' ? $full : (string) ($order['first_name'] ?? 'Client');
}

/**
 * @param array<string, mixed> $order
 */
function tiramii_admin_order_full_address(array $order): string
{
    $line = trim((string) ($order['address_line'] ?? ''));
    $zip = trim((string) ($order['zip'] ?? ''));
    $city = trim((string) ($order['city'] ?? ''));
    $tail = trim($zip . ' ' . $city);

    if ($line === '') {
        return $tail;
    }
    if ($tail === '') {
        return $line;
    }

    return $line . ', ' . $tail;
}

/**
 * @param array<string, mixed> $order
 */
function tiramii_admin_order_maps_url(array $order): string
{
    $q = tiramii_admin_order_full_address($order);
    if ($q === '') {
        return '';
    }

    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($q);
}

/**
 * Commandes d’un jour (fuseau Europe/Paris), triées par heure de commande.
 *
 * @return list<array<string, mixed>>
 */
function tiramii_admin_fetch_orders_for_livreur_day(
    PDO $pdo,
    string $dayKey,
    DateTimeZone $tz,
    bool $ordersHasValidatedAt,
    bool $ordersHasProAccountId
): array {
    $orderSelectCols = 'id, first_name, last_name, phone, address_line, zip, city, delivery_time, note,
        payment_method, total_eur, created_at';
    if ($ordersHasProAccountId) {
        $orderSelectCols .= ', pro_account_id';
    }
    if ($ordersHasValidatedAt) {
        $orderSelectCols .= ', validated_at';
    }

    $anchor = DateTimeImmutable::createFromFormat('!Y-m-d', $dayKey, $tz);
    if ($anchor === false) {
        $anchor = new DateTimeImmutable('now', $tz);
    }
    $from = $anchor->modify('-45 days')->format('Y-m-d H:i:s');

    try {
        $st = $pdo->prepare(
            'SELECT ' . $orderSelectCols . '
             FROM orders
             WHERE created_at >= ?
             ORDER BY created_at ASC, id ASC'
        );
        $st->execute([$from]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        if (tiramii_admin_livreur_day_key((string) ($row['created_at'] ?? ''), $tz) !== $dayKey) {
            continue;
        }
        if (!$ordersHasValidatedAt) {
            $row['validated_at'] = null;
        }
        $out[] = $row;
    }

    return $out;
}

/**
 * @param list<int> $orderIds
 * @return array<int, list<array<string, mixed>>>
 */
function tiramii_admin_fetch_order_items_by_ids(PDO $pdo, array $orderIds): array
{
    $orderIds = array_values(array_filter(array_map('intval', $orderIds), static fn (int $id): bool => $id > 0));
    if ($orderIds === []) {
        return [];
    }
    $inList = implode(',', $orderIds);
    try {
        $itemRows = $pdo
            ->query(
                "SELECT order_id, product_label, quantity, unit_price_eur, box_label
                 FROM order_items WHERE order_id IN ($inList) ORDER BY id ASC"
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }
    $byOrder = [];
    foreach ($itemRows as $r) {
        $oid = (int) $r['order_id'];
        if (!isset($byOrder[$oid])) {
            $byOrder[$oid] = [];
        }
        $byOrder[$oid][] = $r;
    }

    return $byOrder;
}

/**
 * @param list<array<string, mixed>> $items
 */
function tiramii_admin_order_items_summary(array $items): string
{
    if ($items === []) {
        return '—';
    }
    $parts = [];
    foreach ($items as $it) {
        $q = (int) ($it['quantity'] ?? 0);
        $label = (string) ($it['product_label'] ?? '');
        $bl = isset($it['box_label']) && (string) $it['box_label'] !== '' ? ' (' . (string) $it['box_label'] . ')' : '';
        $parts[] = $q . '× ' . $label . $bl;
    }

    return implode(' · ', $parts);
}
