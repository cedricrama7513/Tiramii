<?php
/**
 * Page d’accueil boutique — catalogue rendu côté serveur, panier / réservations en JS.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init_public.php';
require_once __DIR__ . '/includes/catalog_render.php';

try {
    $pdo = require __DIR__ . '/config/db.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Configuration</title></head><body>';
    echo '<p>Configurer <code>config/config.php</code> (copie de <code>config.example.php</code>) et importer <code>database.sql</code>.</p>';
    echo '</body></html>';
    exit;
}

try {
    $imgs = require __DIR__ . '/includes/product_images.php';
    if (!is_array($imgs)) {
        $imgs = [];
    }

    $products = $pdo->query(
        'SELECT id, name, price_eur, description, badge_class, badge_text, img_key, sort_order
         FROM products WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
    )->fetchAll(PDO::FETCH_ASSOC);

    $stockMap = [];
    foreach ($pdo->query('SELECT product_id, quantity FROM stock_levels')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $stockMap[$r['product_id']] = (int) $r['quantity'];
    }

    $productGridHtml = tiramii_render_product_grid($products, $stockMap, $imgs);

    $productsJson = [];
    foreach ($products as $p) {
        $productsJson[] = [
            'id' => $p['id'],
            'name' => $p['name'],
            'price' => (float) $p['price_eur'],
            'desc' => $p['description'],
            'badge' => $p['badge_class'],
            'badgeText' => $p['badge_text'],
            'img' => $p['img_key'],
        ];
    }

    $csrf = csrf_token();
    $tiramiiBoot = [
        'csrf' => $csrf,
        'products' => $productsJson,
    ];

    $appScript = '<script type="module" src="assets/js/shop.js"></script>' . "\n";
    $appScript .= '<script>window.__TIRAMII__ = ' . json_encode($tiramiiBoot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ";</script>\n";

    $tplPath = __DIR__ . '/templates/index_base.html';
    if (!is_readable($tplPath)) {
        throw new RuntimeException('Template manquant');
    }

    $html = file_get_contents($tplPath);
    $navHtml = is_readable(__DIR__ . '/includes/nav_fragment.html')
        ? (string) file_get_contents(__DIR__ . '/includes/nav_fragment.html')
        : '';
    $footerMainHtml = is_readable(__DIR__ . '/includes/footer_main_fragment.html')
        ? (string) file_get_contents(__DIR__ . '/includes/footer_main_fragment.html')
        : '';
    $html = str_replace(
        ['__CSRF__', '__PRODUCT_GRID__', '__APP_SCRIPT__', '__NAV__', '__FOOTER_MAIN__'],
        [h($csrf), $productGridHtml, $appScript, $navHtml, $footerMainHtml],
        $html
    );

    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
} catch (Throwable $e) {
    // Erreur fréquente sur Hostinger : tables absentes, mauvaise base, product_images.php manquant ou tronqué.
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Erreur TIRA’MII</title></head><body style="font-family:sans-serif;max-width:560px;margin:2rem auto;padding:1rem">';
    echo '<h1>Site en cours de configuration</h1>';
    echo '<p>Une erreur empêche l’affichage de la boutique. Vérifiez :</p><ul>';
    echo '<li>Import de <code>database.sql</code> dans la <strong>même</strong> base que celle indiquée dans <code>config/config.php</code></li>';
    echo '<li>Présence et taille de <code>includes/product_images.php</code> (upload complet, ~400 Ko)</li>';
    echo '<li>Dossiers <code>templates/</code>, <code>includes/</code>, <code>assets/</code> au bon niveau (souvent tout dans <code>public_html</code>)</li>';
    echo '</ul>';
    echo '<p>Ouvrez une fois <a href="verification-installation.php">verification-installation.php</a> pour un diagnostic, puis <strong>supprimez ce fichier</strong> sur le serveur.</p>';
    echo '</body></html>';
}
