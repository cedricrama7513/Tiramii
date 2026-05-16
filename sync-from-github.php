<?php
/**
 * Synchronise le site depuis GitHub (si Git Hostinger ne déploie pas).
 *
 * 1. Dans config/config.php ajoutez : 'github_sync_token' => 'votre-secret-long',
 * 2. Uploadez CE fichier seul dans public_html (Gestionnaire de fichiers Hostinger).
 * 3. Ouvrez : https://casadessert.fr/sync-from-github.php?token=votre-secret-long
 * 4. Supprimez sync-from-github.php du serveur après succès.
 */
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');

$root = __DIR__;
$cfgPath = $root . '/config/config.php';
if (!is_readable($cfgPath)) {
    http_response_code(500);
    echo '<p>config/config.php introuvable.</p>';
    exit;
}

/** @var array<string, mixed> $cfg */
$cfg = require $cfgPath;
$expected = trim((string) ($cfg['github_sync_token'] ?? ''));
$given = trim((string) ($_GET['token'] ?? ''));

if ($expected === '' || $given === '' || !hash_equals($expected, $given)) {
    http_response_code(403);
    echo '<p>Accès refusé. Définissez <code>github_sync_token</code> dans config.php et appelez <code>?token=…</code></p>';
    exit;
}

$branch = 'main';
$repo = 'cedricrama7513/Tiramii';
$baseUrl = 'https://raw.githubusercontent.com/' . $repo . '/' . $branch . '/';

$files = [
    'index.php',
    'devis.php',
    'pro.php',
    'pro-login.php',
    'pro-register.php',
    'pro-boutique.php',
    'deploy-version.txt',
    'health.php',
    '.htaccess',
    'api/order.php',
    'api/pro-quote.php',
    'api/pro_login.php',
    'api/pro_logout.php',
    'api/pro_register.php',
    'assets/js/shop.js',
    'includes/nav_render.php',
    'includes/pro_shop_helpers.php',
    'includes/pro_public_page.php',
    'includes/pro_quote_notify.php',
    'includes/pro_b2b.php',
    'includes/ensure_pro_prices.php',
    'includes/home_main_fragment.html',
    'templates/index_base.html',
];

$ok = [];
$fail = [];

foreach ($files as $rel) {
    $dest = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $dir = dirname($dest);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $url = $baseUrl . str_replace(' ', '%20', $rel);
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 60,
            'user_agent' => 'CasaDessertSync/1.0',
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false || $body === '') {
        $fail[] = $rel . ' (téléchargement impossible)';
        continue;
    }
    if (@file_put_contents($dest, $body) === false) {
        $fail[] = $rel . ' (écriture impossible)';
        continue;
    }
    $ok[] = $rel . ' (' . strlen($body) . ' o)';
}

echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Sync GitHub</title></head><body style="font-family:sans-serif;max-width:640px;margin:2rem auto;padding:1rem">';
echo '<h1>Synchronisation GitHub → Hostinger</h1>';
echo '<p><strong>' . count($ok) . '</strong> fichier(s) mis à jour, <strong>' . count($fail) . '</strong> échec(s).</p>';
if ($ok !== []) {
    echo '<h2>OK</h2><ul>';
    foreach ($ok as $line) {
        echo '<li>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    echo '</ul>';
}
if ($fail !== []) {
    echo '<h2>Échecs</h2><ul>';
    foreach ($fail as $line) {
        echo '<li>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    echo '</ul>';
}
echo '<p>Testez : <a href="health.php">health.php</a> · <a href="devis.php">devis.php</a> · <a href="pro-login.php">pro-login.php</a> · <a href="index.php">boutique</a></p>';
echo '<p style="color:#b00020"><strong>Supprimez sync-from-github.php</strong> du serveur maintenant.</p>';
echo '</body></html>';
