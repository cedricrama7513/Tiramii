<?php
/**
 * Diagnostic déploiement Hostinger — ouvrir UNE FOIS dans le navigateur :
 *   https://tiramii.fr/verification-installation.php
 * Puis SUPPRIMER ce fichier (sécurité).
 */
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
$root = __DIR__;
$ok = true;

function line(string $label, bool $pass, string $detail = ''): void
{
    global $ok;
    if (!$pass) {
        $ok = false;
    }
    $icon = $pass ? '✅' : '❌';
    echo "<p><strong>{$icon} {$label}</strong>";
    if ($detail !== '') {
        echo '<br><span style="color:#666;font-size:.9rem">' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</span>';
    }
    echo '</p>';
}

echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Vérification Casa Dessert</title></head><body style="font-family:sans-serif;max-width:640px;margin:2rem auto;padding:0 1rem">';
echo '<h1>Vérification installation</h1>';

line(
    'Dossier racine du script',
    is_readable($root . '/index.php'),
    $root
);

$cfgPath = $root . '/config/config.php';
line(
    'Fichier config/config.php présent',
    is_readable($cfgPath),
    'Si manquant : copiez config.example.php → config.php sur le serveur (hors Git).'
);

line(
    'Template templates/index_base.html',
    is_readable($root . '/templates/index_base.html')
);

line(
    'includes/product_images.php (souvent ~400 Ko)',
    is_readable($root . '/includes/product_images.php'),
    is_readable($root . '/includes/product_images.php')
        ? 'Taille : ' . round(filesize($root . '/includes/product_images.php') / 1024) . ' Ko'
        : 'Régénérez avec : node tools/extract-imgs.mjs puis uploadez le fichier.'
);

line(
    'JavaScript boutique assets/js/shop.js',
    is_readable($root . '/assets/js/shop.js')
);

line(
    'API api/state.php',
    is_readable($root . '/api/state.php')
);

// PDO + tables
if (!is_readable($cfgPath)) {
    line('Connexion MySQL', false, 'Impossible sans config.php.');
} else {
    try {
        $cfg = require $cfgPath;
        $db = $cfg['db'];
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $db['host'],
            $db['name'],
            $db['charset'] ?? 'utf8mb4'
        );
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        line('Connexion MySQL', true, 'Base : ' . htmlspecialchars((string) $db['name'], ENT_QUOTES, 'UTF-8'));

        $need = ['products', 'stock_levels', 'stock_reservations', 'orders', 'order_items'];
        foreach ($need as $table) {
            $st = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
            $exists = $st && $st->fetch() !== false;
            line("Table « {$table} »", $exists, $exists ? '' : 'Importez database.sql dans phpMyAdmin.');
        }

        if ($pdo->query("SHOW TABLES LIKE 'products'")->fetch()) {
            $n = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
            line('Lignes dans products', $n > 0, "Nombre : {$n}");
            $hasSupreme = $pdo->query("SELECT 1 FROM products WHERE id = 'box_supreme' LIMIT 1")->fetch() !== false;
            $hasSupremeStock = $pdo->query("SELECT 1 FROM stock_levels WHERE product_id = 'box_supreme' LIMIT 1")->fetch() !== false;
            line(
                'Produit Box suprême (box_supreme) + stock',
                $hasSupreme && $hasSupremeStock,
                $hasSupreme && $hasSupremeStock
                    ? 'OK — rechargez la boutique ou admin après déploiement du correctif auto.'
                    : 'Rechargez une page du site ou admin.php : le script ensure_box_supreme.php crée les lignes manquantes. Sinon importez les INSERT depuis database.sql.'
            );
        }

        if ($pdo->query("SHOW TABLES LIKE 'orders'")->fetch()) {
            $colOk = $pdo->query("SHOW COLUMNS FROM orders LIKE 'validated_at'")->fetch() !== false;
            line(
                'Colonne orders.validated_at (bouton « validée » dans admin)',
                $colOk,
                $colOk ? '' : 'SQL : <code>ALTER TABLE orders ADD COLUMN validated_at DATETIME NULL DEFAULT NULL AFTER created_at;</code>'
            );
        }

        $adminHash = trim((string) (($cfg['admin_password_hash'] ?? '')));
        line(
            'Mot de passe admin (admin.php)',
            $adminHash !== '',
            $adminHash !== ''
                ? 'Défini dans config.php — gérer le stock sur <a href="admin.php">admin.php</a>.'
                : 'Uploadez <code>once-set-admin-password.php</code> à la racine, ouvrez-le une fois dans le navigateur, puis supprimez-le — ou renseignez <code>admin_password_hash</code> dans config.php.'
        );
    } catch (Throwable $e) {
        line(
            'Connexion MySQL',
            false,
            'Vérifiez hôte (souvent <code>localhost</code> sur Hostinger), base, utilisateur, mot de passe. Message : '
                . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        );
    }
}

echo '<hr><p><strong>PHP :</strong> ' . PHP_VERSION . '</p>';
echo '<p><strong>Extensions :</strong> pdo_mysql = ' . (extension_loaded('pdo_mysql') ? 'oui' : 'NON') . '</p>';

if ($ok) {
    echo '<p style="color:green"><strong>Tout semble correct.</strong> Testez <a href="index.php">index.php</a> puis supprimez ce fichier verification-installation.php.</p>';
} else {
    echo '<p style="color:#b00020"><strong>Corrigez les points en échec ci-dessus.</strong> Ensuite supprimez verification-installation.php.</p>';
}

echo '</body></html>';
