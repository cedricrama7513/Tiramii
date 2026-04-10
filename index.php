<?php
/**
 * Page d’accueil boutique — catalogue rendu côté serveur, panier / réservations en JS.
 */
declare(strict_types=1);

if (function_exists('ini_set')) {
    @ini_set('memory_limit', '256M');
}

try {
    $init = __DIR__ . '/includes/init_public.php';
    if (!is_readable($init)) {
        throw new RuntimeException('includes/init_public.php introuvable (déploiement incomplet ?).');
    }
    require_once $init;

    $catalog = __DIR__ . '/includes/catalog_render.php';
    if (!is_readable($catalog)) {
        throw new RuntimeException('includes/catalog_render.php introuvable (déploiement incomplet ?).');
    }
    require_once $catalog;

    $pdo = require __DIR__ . '/config/db.php';

    if (function_exists('ini_set')) {
        @ini_set('memory_limit', '256M');
    }

    $imgsPath = __DIR__ . '/includes/product_images.php';
    if (!is_readable($imgsPath)) {
        throw new RuntimeException('includes/product_images.php introuvable ou illisible (fichier ~400 Ko requis).');
    }
    $imgs = require $imgsPath;
    if (!is_array($imgs)) {
        $imgs = [];
    }

    $retiredProductIds = ['box2'];
    $notIn = implode(',', array_fill(0, count($retiredProductIds), '?'));
    $stmtProducts = $pdo->prepare(
        "SELECT id, name, price_eur, description, badge_class, badge_text, img_key, sort_order
         FROM products WHERE is_active = 1 AND id NOT IN ($notIn) ORDER BY sort_order ASC, id ASC"
    );
    $stmtProducts->execute($retiredProductIds);
    $products = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);

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

    $shopJsPath = __DIR__ . '/assets/js/shop.js';
    $shopJsV = is_readable($shopJsPath) ? (string) filemtime($shopJsPath) : (string) time();
    $appScript = '<script type="module" src="assets/js/shop.js?v=' . h($shopJsV) . '"></script>' . "\n";
    $appScript .= '<script>window.__TIRAMII__ = ' . json_encode($tiramiiBoot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ";</script>\n";

    $tplPath = __DIR__ . '/templates/index_base.html';
    if (!is_readable($tplPath)) {
        throw new RuntimeException('templates/index_base.html manquant.');
    }

    $html = file_get_contents($tplPath);

    $boxPath = __DIR__ . '/includes/box_section_fragment.html';
    $boxHtml = is_readable($boxPath) ? (string) file_get_contents($boxPath) : '';
    if ($boxHtml !== '') {
        if (str_contains($html, '__BOX_SECTION__')) {
            $html = str_replace('__BOX_SECTION__', $boxHtml, $html);
        } else {
            $replaced = preg_replace(
                '/<section\s[^>]*\bid="box"\b[^>]*>[\s\S]*?<\/section>/iu',
                $boxHtml,
                $html,
                1,
                $boxCount
            );
            if ($boxCount > 0) {
                $html = $replaced;
            }
        }
    }

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
} catch (Throwable) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>TIRA’MII — configuration</title></head><body style="font-family:sans-serif;max-width:560px;margin:2rem auto;padding:1rem">';
    echo '<h1>Le site ne peut pas s’afficher</h1>';
    echo '<p><strong>À vérifier sur Hostinger :</strong></p><ul>';
    echo '<li>Tout le projet est dans le dossier du domaine (souvent <code>public_html</code>), pas seulement <code>index.php</code>.</li>';
    echo '<li>Fichier <code>config/config.php</code> créé sur le serveur (il n’est pas sur Git) avec les identifiants MySQL.</li>';
    echo '<li>Hôte MySQL : essayez <code>localhost</code> si <code>127.0.0.1</code> échoue (ou l’inverse).</li>';
    echo '<li>Import de <code>database.sql</code> dans la même base que dans <code>config.php</code>.</li>';
    echo '<li>Fichier <code>includes/product_images.php</code> uploadé en entier (~400 Ko).</li>';
    echo '</ul>';
    echo '<p>Uploadez aussi <code>verification-installation.php</code> depuis le dépôt, ouvrez-le une fois dans le navigateur, puis supprimez-le.</p>';
    echo '<p>Tests : <a href="ping.php">ping.php</a> → <code>ok</code> ; <a href="health.php">health.php</a> → liste des fichiers.</p>';
    echo '<p><strong>Si ping.php et health.php sont en 404</strong>, le déploiement Git (ou les fichiers) n’est pas dans le dossier racine du domaine (hPanel → Fichiers → <code>public_html</code> pour ce site, ou chemin indiqué pour tiramii.fr).</p>';
    echo '<p style="color:#555;font-size:.9rem">Logs PHP : hPanel → Avancé → journaux d’erreurs (ou équivalent).</p>';
    echo '</body></html>';
}
