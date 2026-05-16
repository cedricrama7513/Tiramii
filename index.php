<?php
/**
 * Page d’accueil boutique — catalogue rendu côté serveur, panier / réservations en JS.
 */
declare(strict_types=1);

$routePage = isset($_GET['page']) ? strtolower(trim((string) $_GET['page'])) : '';
if ($routePage === 'devis' || $routePage === 'pro') {
    $proPage = __DIR__ . '/includes/pro_public_page.php';
    if (is_readable($proPage)) {
        require_once $proPage;
        tiramii_serve_pro_public_page();
        exit;
    }
    if ($routePage === 'devis' && is_readable(__DIR__ . '/devis.php')) {
        header('Location: devis.php', true, 302);
        exit;
    }
}

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

    require_once __DIR__ . '/includes/pro_accounts.php';
    require_once __DIR__ . '/includes/ensure_pro_prices.php';
    require_once __DIR__ . '/includes/pro_shop_helpers.php';
    require_once __DIR__ . '/includes/nav_render.php';

    require_once __DIR__ . '/includes/reviews_render.php';
    $reviewsData = require __DIR__ . '/includes/reviews_data.php';
    if (!is_array($reviewsData)) {
        $reviewsData = [];
    }
    $reviewsSectionHtml = tiramii_render_reviews_section($reviewsData);

    $pdo = require __DIR__ . '/config/db.php';

    require_once __DIR__ . '/includes/ensure_box_supreme.php';
    require_once __DIR__ . '/includes/ensure_new_flavors.php';
    require_once __DIR__ . '/includes/ensure_stock_levels.php';
    tiramii_ensure_new_flavors($pdo);
    tiramii_ensure_box_supreme($pdo);
    tiramii_ensure_stock_levels_for_all_products($pdo);
    tiramii_ensure_pro_account_tables($pdo);
    tiramii_ensure_pro_price_column($pdo);

    $proAccount = tiramii_pro_current_account($pdo);
    $isProShop = $proAccount !== null;

    if (function_exists('ini_set')) {
        @ini_set('memory_limit', '256M');
    }

    $imgsPath = __DIR__ . '/includes/product_images.php';
    $imgs = [];
    if (is_readable($imgsPath)) {
        /** @var mixed $loaded */
        $loaded = require $imgsPath;
        if (is_array($loaded)) {
            $imgs = $loaded;
        }
    }

    // box1 / box2 : vendues via la section « Box », pas comme barquettes dans la grille catalogue.
    $excludeFromCatalog = ['box1', 'box2', 'box_supreme'];
    $products = tiramii_fetch_catalog_products($pdo, $excludeFromCatalog, $isProShop);

    $stockMap = [];
    foreach ($pdo->query('SELECT product_id, quantity FROM stock_levels')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $stockMap[$r['product_id']] = (int) $r['quantity'];
    }

    $productGridHtml = tiramii_render_product_grid($products, $stockMap, $imgs, $isProShop);

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
    $appBoot = [
        'csrf' => $csrf,
        'products' => $productsJson,
        'isPro' => $isProShop,
        'proAccount' => $isProShop ? [
            'restaurant_name' => (string) ($proAccount['restaurant_name'] ?? ''),
            'first_name' => (string) ($proAccount['first_name'] ?? ''),
            'last_name' => (string) ($proAccount['last_name'] ?? ''),
            'phone' => (string) ($proAccount['phone'] ?? ''),
            'address_line' => (string) ($proAccount['address_line'] ?? ''),
            'zip' => (string) ($proAccount['zip'] ?? ''),
            'city' => (string) ($proAccount['city'] ?? ''),
        ] : null,
    ];

    $shopJsPath = __DIR__ . '/assets/js/shop.js';
    $shopJsV = is_readable($shopJsPath) ? (string) filemtime($shopJsPath) : (string) time();
    $appScript = '<script type="module" src="assets/js/shop.js?v=' . h($shopJsV) . '"></script>' . "\n";
    $appScript .= '<script>window.__CASA_DESSERT__ = ' . json_encode($appBoot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ";</script>\n";

    $tplPath = __DIR__ . '/templates/index_base.html';
    if (!is_readable($tplPath)) {
        throw new RuntimeException('templates/index_base.html manquant.');
    }

    $html = file_get_contents($tplPath);
    $homeMainPath = __DIR__ . '/includes/home_main_fragment.html';
    if (!is_readable($homeMainPath)) {
        throw new RuntimeException('includes/home_main_fragment.html manquant.');
    }
    $homeMainHtml = (string) file_get_contents($homeMainPath);
    $proBanner = tiramii_render_pro_shop_banner($proAccount);
    if ($proBanner !== '' && str_contains($homeMainHtml, '<section class="hero">')) {
        $homeMainHtml = preg_replace(
            '/(<section class="hero">)/',
            $proBanner . '$1',
            $homeMainHtml,
            1
        ) ?? $homeMainHtml;
    }
    $html = str_replace('__HOME_MAIN__', $homeMainHtml, $html);

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

    $navHtml = tiramii_render_nav_html($proAccount);
    if (str_contains($navHtml, '__LOGO_MARK__')) {
        $navHtml = str_replace('__LOGO_MARK__', brand_logo_markup('nav'), $navHtml);
    }
    $footerMainHtml = is_readable(__DIR__ . '/includes/footer_main_fragment.html')
        ? (string) file_get_contents(__DIR__ . '/includes/footer_main_fragment.html')
        : '';
    $pageTitle = $isProShop
        ? 'Casa Dessert — Tarifs pro'
        : 'Casa Dessert — Les desserts qui régalent !';
    $html = str_replace(
        ['__PAGE_TITLE__', '__CSRF__', '__PRODUCT_GRID__', '__APP_SCRIPT__', '__NAV__', '__FOOTER_MAIN__', '__REVIEWS_SECTION__'],
        [h($pageTitle), h($csrf), $productGridHtml, $appScript, $navHtml, $footerMainHtml, $reviewsSectionHtml],
        $html
    );

    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
} catch (Throwable) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Casa Dessert — configuration</title></head><body style="font-family:sans-serif;max-width:560px;margin:2rem auto;padding:1rem">';
    echo '<h1>Le site ne peut pas s’afficher</h1>';
    echo '<p><strong>À vérifier sur Hostinger :</strong></p><ul>';
    echo '<li>Tout le projet est dans le dossier du domaine (souvent <code>public_html</code>), pas seulement <code>index.php</code>.</li>';
    echo '<li>Fichier <code>config/config.php</code> créé sur le serveur (il n’est pas sur Git) avec les identifiants MySQL.</li>';
    echo '<li>Hôte MySQL : essayez <code>localhost</code> si <code>127.0.0.1</code> échoue (ou l’inverse).</li>';
    echo '<li>Import de <code>database.sql</code> dans la même base que dans <code>config.php</code>.</li>';
    echo '<li>Fichier <code>includes/product_images.php</code> présent sur le serveur (sinon le catalogue affiche des placeholders sans photos réelles).</li>';
    echo '</ul>';
    echo '<p>Uploadez aussi <code>verification-installation.php</code> depuis le dépôt, ouvrez-le une fois dans le navigateur, puis supprimez-le.</p>';
    echo '<p>Tests : <a href="ping.php">ping.php</a> → <code>ok</code> ; <a href="health.php">health.php</a> → liste des fichiers.</p>';
    echo '<p><strong>Si ping.php et health.php sont en 404</strong>, le déploiement Git (ou les fichiers) n’est pas dans le dossier racine du domaine (hPanel → Fichiers → <code>public_html</code> pour ce site, ou chemin indiqué pour casadessert.fr).</p>';
    echo '<p style="color:#555;font-size:.9rem">Logs PHP : hPanel → Avancé → journaux d’erreurs (ou équivalent).</p>';
    echo '</body></html>';
}
