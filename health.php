<?php
/**
 * Diagnostic sans dépendance — doit répondre en texte brut.
 * Si cette URL est en 404, les fichiers ne sont pas dans la racine web du domaine.
 * Supprimez ce fichier après mise au point si vous le souhaitez.
 */
declare(strict_types=1);
header('Content-Type: text/plain; charset=UTF-8');

$root = __DIR__;
echo "casa-dessert-health\n";
echo 'PHP ' . PHP_VERSION . "\n";
echo 'memory_limit=' . ini_get('memory_limit') . "\n";
echo '__DIR__=' . $root . "\n\n";

$paths = [
    'index.php',
    'ping.php',
    'health.php',
    'includes/init_public.php',
    'includes/catalog_render.php',
    'includes/product_images.php',
    'config/config.php',
    'templates/index_base.html',
    'assets/js/shop.js',
];

foreach ($paths as $rel) {
    $p = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (is_readable($p) && is_file($p)) {
        echo "OK  {$rel}  (" . filesize($p) . " octets)\n";
    } else {
        echo "MANQUANT  {$rel}\n";
    }
}
