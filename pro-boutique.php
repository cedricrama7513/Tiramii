<?php
/**
 * Boutique réservée aux comptes pro actifs (tarifs COALESCE(price_pro_eur, price_eur)).
 */
declare(strict_types=1);

if (function_exists('ini_set')) {
    @ini_set('memory_limit', '256M');
}

require_once __DIR__ . '/includes/init_public.php';
require_once __DIR__ . '/includes/catalog_render.php';

$pdo = require __DIR__ . '/config/db.php';

require_once __DIR__ . '/includes/ensure_box_supreme.php';
require_once __DIR__ . '/includes/ensure_new_flavors.php';
require_once __DIR__ . '/includes/ensure_stock_levels.php';
require_once __DIR__ . '/includes/pro_accounts.php';

tiramii_ensure_new_flavors($pdo);
tiramii_ensure_box_supreme($pdo);
tiramii_ensure_stock_levels_for_all_products($pdo);
tiramii_ensure_pro_account_tables($pdo);

$account = tiramii_pro_current_account($pdo);
if ($account === null) {
    header('Location: pro-login.php?next=boutique', true, 302);
    exit;
}

$csrf = csrf_token();

$imgsPath = __DIR__ . '/includes/product_images.php';
if (!is_readable($imgsPath)) {
    http_response_code(500);
    echo 'Fichier product_images.php manquant.';
    exit;
}
$imgs = require $imgsPath;
if (!is_array($imgs)) {
    $imgs = [];
}

$excludeFromCatalog = ['box1', 'box2', 'box_supreme'];
$notIn = implode(',', array_fill(0, count($excludeFromCatalog), '?'));

try {
    $stmtProducts = $pdo->prepare(
        "SELECT id, name, COALESCE(price_pro_eur, price_eur) AS price_eur, description, badge_class, badge_text, img_key, sort_order
         FROM products WHERE is_active = 1 AND id NOT IN ($notIn) ORDER BY sort_order ASC, id ASC"
    );
    $stmtProducts->execute($excludeFromCatalog);
    $products = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable) {
    $stmtProducts = $pdo->prepare(
        "SELECT id, name, price_eur, description, badge_class, badge_text, img_key, sort_order
         FROM products WHERE is_active = 1 AND id NOT IN ($notIn) ORDER BY sort_order ASC, id ASC"
    );
    $stmtProducts->execute($excludeFromCatalog);
    $products = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);
}

$stockMap = [];
foreach ($pdo->query('SELECT product_id, quantity FROM stock_levels')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $stockMap[$r['product_id']] = (int) $r['quantity'];
}

$productGridHtml = tiramii_render_product_grid($products, $stockMap, $imgs, true);

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

$boxPrices = ['box1' => 10.0, 'box_supreme' => 10.0];
try {
    $stB = $pdo->query(
        "SELECT id, COALESCE(price_pro_eur, price_eur) AS p FROM products WHERE id IN ('box1','box_supreme') AND is_active = 1"
    );
    if ($stB) {
        while ($br = $stB->fetch(PDO::FETCH_ASSOC)) {
            $boxPrices[(string) $br['id']] = round((float) $br['p'], 2);
        }
    }
} catch (Throwable) {
    /* */
}

foreach (['box1', 'box_supreme'] as $bid) {
    $stOne = $pdo->prepare('SELECT id, name, COALESCE(price_pro_eur, price_eur) AS price_eur, description, badge_class, badge_text, img_key FROM products WHERE id = ? LIMIT 1');
    $stOne->execute([$bid]);
    $bp = $stOne->fetch(PDO::FETCH_ASSOC);
    if ($bp) {
        $productsJson[] = [
            'id' => $bp['id'],
            'name' => $bp['name'],
            'price' => (float) $bp['price_eur'],
            'desc' => $bp['description'],
            'badge' => $bp['badge_class'],
            'badgeText' => $bp['badge_text'],
            'img' => $bp['img_key'],
        ];
    }
}

$priceBox1 = number_format($boxPrices['box1'], 2, ',', '') . '€';
$priceBox2 = number_format($boxPrices['box_supreme'], 2, ',', '') . '€';

$boxHtml = is_readable(__DIR__ . '/includes/box_section_pro_fragment.html')
    ? str_replace(
        ['__PRICE_BOX1__', '__PRICE_BOX2__'],
        [$priceBox1, $priceBox2],
        (string) file_get_contents(__DIR__ . '/includes/box_section_pro_fragment.html')
    )
    : '';

$restaurant = h((string) $account['restaurant_name']);
$navHtml = <<<NAV
<nav>
  <a href="pro-boutique.php" class="nav-logo"><div class="logo-circle"><span>T</span></div>TIRA'MII Pro</a>
  <ul class="nav-links">
    <li style="list-style:none;font-size:.8rem;color:var(--vd);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{$restaurant}">{$restaurant}</li>
    <li><a href="pro.php">Espace pro</a></li>
    <li><a href="index.php">Site public</a></li>
    <li><button type="button" class="cart-btn" id="proLogoutBtn" style="background:#555">Déconnexion</button></li>
  </ul>
  <button class="cart-btn" onclick="toggleCart()">🛒 Panier <span class="cart-count" id="cartCount">0</span></button>
</nav>
NAV;

$footerMainHtml = '<p style="text-align:center;padding:2rem;color:#8a7090;font-size:.88rem">Une question ? Écrivez-nous depuis <a href="pro.php">l’espace pro</a> ou par les coordonnées habituelles.</p>';

$shopProPath = __DIR__ . '/assets/js/shop_pro.js';
$shopProV = is_readable($shopProPath) ? (string) filemtime($shopProPath) : (string) time();
$tiramiiProBoot = [
    'csrf' => $csrf,
    'products' => $productsJson,
    'account' => [
        'restaurant_name' => (string) $account['restaurant_name'],
        'first_name' => (string) $account['first_name'],
        'last_name' => (string) $account['last_name'],
        'phone' => (string) $account['phone'],
        'address_line' => (string) $account['address_line'],
        'zip' => (string) $account['zip'],
        'city' => (string) $account['city'],
    ],
];
$appScript = '<script type="module" src="assets/js/shop_pro.js?v=' . h($shopProV) . '"></script>' . "\n";
$appScript .= '<script>window.__TIRAMII_PRO__ = ' . json_encode($tiramiiProBoot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ";</script>\n";
$appScript .= '<script>
document.getElementById("proLogoutBtn")?.addEventListener("click", function() {
  fetch("api/pro_logout.php", { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json", "X-CSRF-Token": ' . json_encode($csrf) . ' }, body: "{}" })
    .finally(function() { window.location.href = "pro-login.php"; });
});
document.addEventListener("DOMContentLoaded", function() {
  var a = ' . json_encode((string) $account['first_name']) . ';
  var b = ' . json_encode((string) $account['last_name']) . ';
  var ph = ' . json_encode((string) $account['phone']) . ';
  var ad = ' . json_encode((string) $account['address_line']) . ';
  var z = ' . json_encode((string) $account['zip']) . ';
  var c = ' . json_encode((string) $account['city']) . ';
  var fn = document.getElementById("firstName"); if (fn && !fn.value) fn.value = a;
  var ln = document.getElementById("lastName"); if (ln && !ln.value) ln.value = b;
  var p = document.getElementById("phone"); if (p && !p.value) p.value = ph;
  var addr = document.getElementById("address"); if (addr && !addr.value) addr.value = ad;
  var zp = document.getElementById("zip"); if (zp && !zp.value) zp.value = z;
  var ct = document.getElementById("city"); if (ct && !ct.value) ct.value = c;
});
</script>';

$tplPath = __DIR__ . '/templates/pro_shop_base.html';
if (!is_readable($tplPath)) {
    http_response_code(500);
    echo 'Template pro_shop_base.html manquant.';
    exit;
}

$html = file_get_contents($tplPath);
$html = str_replace(
    ['__CSRF__', '__PRODUCT_GRID__', '__APP_SCRIPT__', '__NAV__', '__FOOTER_MAIN__', '__BOX_SECTION__'],
    [h($csrf), $productGridHtml, $appScript, $navHtml, $footerMainHtml, $boxHtml],
    $html
);

header('Content-Type: text/html; charset=UTF-8');
echo $html;
