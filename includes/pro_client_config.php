<?php
/**
 * Clients pro (noms alignés admin → Pro : CA, factures, comptes).
 */
declare(strict_types=1);

/**
 * @return list<string>
 */
function tiramii_pro_distinct_client_names(PDO $pdo): array
{
    $set = [];
    foreach (['pro_ca_entries', 'pro_invoices', 'pro_accounts'] as $table) {
        try {
            if ($table === 'pro_accounts') {
                $st = $pdo->query(
                    "SELECT DISTINCT restaurant_name AS n FROM pro_accounts
                     WHERE TRIM(restaurant_name) <> '' AND status IN ('active', 'pending')
                     ORDER BY n ASC"
                );
            } else {
                $st = $pdo->query(
                    'SELECT DISTINCT restaurant_name AS n FROM ' . $table . " WHERE TRIM(restaurant_name) <> '' ORDER BY n ASC"
                );
            }
            $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable) {
            $rows = [];
        }
        foreach ($rows as $r) {
            $n = trim((string) ($r['n'] ?? ''));
            if ($n !== '') {
                $set[$n] = true;
            }
        }
    }
    $names = array_keys($set);
    sort($names, SORT_STRING);

    return $names;
}

function tiramii_pro_invoice_prefix(string $restaurantName): string
{
    $slug = preg_replace('/[^A-Z0-9]/u', '', mb_strtoupper(mb_substr($restaurantName, 0, 12)));
    if ($slug === '') {
        $slug = 'CLI';
    }

    return 'FAC-' . $slug;
}

function tiramii_pro_excel_sheet_name(string $restaurantName): string
{
    $name = trim($restaurantName);
    if (mb_strlen($name) <= 31) {
        return $name;
    }

    return mb_substr($name, 0, 31);
}

/**
 * Montants TTC par mois (index 0 = janvier) pour une année.
 *
 * @return list<float>
 */
function tiramii_pro_monthly_ca_amounts(PDO $pdo, string $restaurantName, int $year = 2026): array
{
    $amounts = array_fill(0, 12, 0.0);
    try {
        $st = $pdo->prepare(
            'SELECT MONTH(ca_date) AS m, SUM(amount_eur) AS total
             FROM pro_ca_entries
             WHERE restaurant_name = ? AND YEAR(ca_date) = ?
             GROUP BY MONTH(ca_date)'
        );
        $st->execute([$restaurantName, $year]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $m = (int) ($row['m'] ?? 0);
            if ($m >= 1 && $m <= 12) {
                $amounts[$m - 1] = round((float) ($row['total'] ?? 0), 2);
            }
        }
    } catch (Throwable) {
        /* table absente */
    }

    return $amounts;
}

/**
 * @param list<float> $amounts
 * @return list<float>
 */
function tiramii_pro_fill_monthly_amount_defaults(array $amounts, int $clientIndex): array
{
    $templates = [
        [320, 280, 395, 410, 365, 342, 388, 425, 298, 372, 356, 401],
        [150, 165, 142, 178, 155, 188, 172, 195, 160, 184, 148, 176],
    ];
    $fallback = $templates[$clientIndex] ?? $templates[0];

    return array_map(
        static function (float $v, int $i) use ($fallback): float {
            if ($v > 0) {
                return $v;
            }

            return (float) ($fallback[$i] ?? 200);
        },
        $amounts,
        array_keys($amounts)
    );
}

/**
 * Définitions clients pour le tableur Excel (2 premiers noms pro connus).
 *
 * @return list<array{name: string, sheetName: string, invoicePrefix: string, monthlyAmounts: list<float>, paidMonths: list<int>}>
 */
function tiramii_pro_clients_for_excel(PDO $pdo, int $maxClients = 2): array
{
    $names = tiramii_pro_distinct_client_names($pdo);
    if ($names === []) {
        return tiramii_pro_clients_for_excel_from_json();
    }

    $clients = [];
    foreach (array_slice($names, 0, $maxClients) as $i => $name) {
        $monthly = tiramii_pro_fill_monthly_amount_defaults(
            tiramii_pro_monthly_ca_amounts($pdo, $name),
            $i
        );
        $clients[] = [
            'name' => $name,
            'sheetName' => tiramii_pro_excel_sheet_name($name),
            'invoicePrefix' => tiramii_pro_invoice_prefix($name),
            'monthlyAmounts' => $monthly,
            'paidMonths' => [],
        ];
    }

    return $clients;
}

/**
 * @return list<array{name: string, sheetName: string, invoicePrefix: string, monthlyAmounts: list<float>, paidMonths: list<int>}>
 */
function tiramii_pro_clients_for_excel_from_json(): array
{
    $path = dirname(__DIR__) . '/tools/pro-clients.json';
    if (!is_readable($path)) {
        return [];
    }
    $raw = json_decode((string) file_get_contents($path), true);
    if (!is_array($raw) || !isset($raw['clients']) || !is_array($raw['clients'])) {
        return [];
    }
    $out = [];
    foreach ($raw['clients'] as $c) {
        if (!is_array($c)) {
            continue;
        }
        $name = trim((string) ($c['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $amounts = $c['monthlyAmounts'] ?? [];
        if (!is_array($amounts)) {
            $amounts = [];
        }
        $monthly = [];
        for ($i = 0; $i < 12; $i++) {
            $monthly[] = isset($amounts[$i]) ? (float) $amounts[$i] : 0.0;
        }
        $paid = $c['paidMonths'] ?? [];
        if (!is_array($paid)) {
            $paid = [];
        }
        $out[] = [
            'name' => $name,
            'sheetName' => trim((string) ($c['sheetName'] ?? '')) ?: tiramii_pro_excel_sheet_name($name),
            'invoicePrefix' => trim((string) ($c['invoicePrefix'] ?? '')) ?: tiramii_pro_invoice_prefix($name),
            'monthlyAmounts' => tiramii_pro_fill_monthly_amount_defaults($monthly, count($out)),
            'paidMonths' => array_map('intval', $paid),
        ];
    }

    return $out;
}
