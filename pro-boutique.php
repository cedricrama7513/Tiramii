<?php
/**
 * Redirection : la boutique pro est sur index.php (tarifs pro si connecté).
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init_public.php';
require_once __DIR__ . '/includes/pro_accounts.php';

$pdo = require __DIR__ . '/config/db.php';
tiramii_ensure_pro_account_tables($pdo);

if (tiramii_pro_current_account($pdo) === null) {
    header('Location: pro-login.php', true, 302);
    exit;
}

header('Location: index.php', true, 302);
exit;
