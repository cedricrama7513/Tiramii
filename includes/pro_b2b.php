<?php
/**
 * Espace admin « Pro » : CA par restaurant + factures PDF (hors site particuliers).
 */
declare(strict_types=1);

function tiramii_pro_data_dir(): string
{
    return dirname(__DIR__) . '/data/pro_invoices';
}

function tiramii_ensure_pro_tables(PDO $pdo): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS pro_ca_entries (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                restaurant_name VARCHAR(255) NOT NULL,
                amount_eur DECIMAL(10,2) NOT NULL,
                ca_date DATE NOT NULL,
                note VARCHAR(500) NOT NULL DEFAULT \'\',
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_pro_ca_date (ca_date),
                KEY idx_pro_ca_rest (restaurant_name(64))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS pro_invoices (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                restaurant_name VARCHAR(255) NOT NULL DEFAULT \'\',
                original_name VARCHAR(255) NOT NULL,
                stored_name VARCHAR(120) NOT NULL,
                mime_type VARCHAR(120) NOT NULL DEFAULT \'application/pdf\',
                size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
                note VARCHAR(500) NOT NULL DEFAULT \'\',
                uploaded_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_pro_inv_rest (restaurant_name(64)),
                UNIQUE KEY uq_pro_inv_stored (stored_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    } catch (Throwable) {
        /* droits CREATE refusés */
    }

    try {
        $chk = $pdo->query("SHOW COLUMNS FROM pro_invoices LIKE 'restaurant_name'");
        if ($chk && $chk->fetch() === false) {
            $pdo->exec(
                'ALTER TABLE pro_invoices ADD COLUMN restaurant_name VARCHAR(255) NOT NULL DEFAULT \'\' AFTER id'
            );
            try {
                $pdo->exec('ALTER TABLE pro_invoices ADD KEY idx_pro_inv_rest (restaurant_name(64))');
            } catch (Throwable) {
                /* index peut déjà exister */
            }
        }
    } catch (Throwable) {
        /* table absente ou droits ALTER refusés */
    }

    $dir = tiramii_pro_data_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents(
            $ht,
            "# Bloque l'accès direct aux PDF si le dossier est sous la racine web.\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
        );
    }
}

/** @return array{ok: bool, amount: float, message: string} */
function tiramii_pro_parse_amount_eur(string $raw): array
{
    $s = trim(str_replace(["\u{00A0}", ' '], '', $raw));
    $s = str_replace(',', '.', $s);
    if ($s === '' || !is_numeric($s)) {
        return ['ok' => false, 'amount' => 0.0, 'message' => 'Montant invalide.'];
    }
    $n = round((float) $s, 2);
    if ($n <= 0 || $n > 999999.99) {
        return ['ok' => false, 'amount' => 0.0, 'message' => 'Montant hors plage (0,01 à 999 999,99 €).'];
    }

    return ['ok' => true, 'amount' => $n, 'message' => ''];
}

function tiramii_admin_pro_invoice_download(PDO $pdo): void
{
    $id = (int) ($_GET['download_pro_invoice'] ?? 0);
    if ($id < 1) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Requête invalide.';
        exit;
    }

    $st = $pdo->prepare('SELECT original_name, stored_name, mime_type FROM pro_invoices WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Facture introuvable.';
        exit;
    }

    $base = basename((string) $row['stored_name']);
    if ($base === '' || preg_match('/^[a-f0-9]{32}\.pdf$/i', $base) !== 1) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Fichier non valide.';
        exit;
    }

    $path = tiramii_pro_data_dir() . '/' . $base;
    if (!is_readable($path)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Fichier absent sur le serveur.';
        exit;
    }

    $orig = (string) $row['original_name'];
    $mime = (string) $row['mime_type'];
    if ($mime === '') {
        $mime = 'application/pdf';
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $orig) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

function tiramii_admin_pro_ca_csv_export(PDO $pdo): void
{
    try {
        $st = $pdo->query(
            'SELECT ca_date, restaurant_name, amount_eur, note, created_at
             FROM pro_ca_entries ORDER BY ca_date DESC, id DESC'
        );
        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable) {
        $rows = [];
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="casa-dessert-ca-pro-' . date('Y-m-d') . '.csv"');
    header('X-Content-Type-Options: nosniff');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        http_response_code(500);
        echo 'Export impossible.';
        exit;
    }
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['date_ca', 'restaurant', 'montant_eur', 'note', 'saisi_le'], ';');
    foreach ($rows as $r) {
        fputcsv(
            $out,
            [
                (string) $r['ca_date'],
                (string) $r['restaurant_name'],
                number_format((float) $r['amount_eur'], 2, '.', ''),
                (string) $r['note'],
                (string) $r['created_at'],
            ],
            ';'
        );
    }
    fclose($out);
    exit;
}

/** URL de l’onglet Pro avec filtre client optionnel (factures). */
function tiramii_pro_admin_tab_url(string $clientFilter = ''): string
{
    $q = 'tab=pro';
    $t = trim($clientFilter);
    if ($t !== '') {
        $q .= '&pro_client=' . rawurlencode(mb_substr($t, 0, 255));
    }

    return 'admin.php?' . $q;
}

/**
 * @return list<string>
 */
function tiramii_pro_distinct_client_names(PDO $pdo): array
{
    $set = [];
    foreach (['pro_ca_entries', 'pro_invoices'] as $table) {
        try {
            $st = $pdo->query(
                'SELECT DISTINCT restaurant_name AS n FROM ' . $table . " WHERE TRIM(restaurant_name) <> '' ORDER BY n ASC"
            );
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
