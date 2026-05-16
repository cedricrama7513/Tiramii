#!/usr/bin/env php
<?php
/**
 * Exporte les clients pro (mêmes noms que admin → Pro) vers tools/pro-clients.json
 * Usage : php tools/export-pro-clients-json.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$configPath = $root . '/config/config.php';

if (!is_readable($configPath)) {
    fwrite(STDERR, "config/config.php introuvable — exécutez sur le serveur ou copiez la config.\n");
    exit(1);
}

require_once $root . '/includes/pro_b2b.php';
require_once $root . '/includes/pro_accounts.php';

try {
    $pdo = require $root . '/config/db.php';
    tiramii_ensure_pro_tables($pdo);
    tiramii_ensure_pro_account_tables($pdo);
    $clients = tiramii_pro_clients_for_excel($pdo, 2);
} catch (Throwable $e) {
    fwrite(STDERR, 'Erreur base : ' . $e->getMessage() . "\n");
    exit(1);
}

if ($clients === []) {
    $clients = tiramii_pro_clients_for_excel_from_json();
}

if (count($clients) < 1) {
    fwrite(STDERR, "Aucun client pro trouvé (CA, factures ou comptes).\n");
    exit(1);
}

$payload = [
    'generatedAt' => (new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')))->format('c'),
    'source' => 'admin Pro (pro_ca_entries, pro_invoices, pro_accounts)',
    'clients' => $clients,
];

$outPath = $root . '/tools/pro-clients.json';
file_put_contents($outPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
echo "Écrit {$outPath} (" . count($clients) . " client(s))\n";
foreach ($clients as $c) {
    echo '  - ' . $c['name'] . "\n";
}
