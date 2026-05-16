<?php
/**
 * Demande de devis pro — URL directe (sans .htaccess).
 * https://casadessert.fr/devis.php
 */
declare(strict_types=1);

$_GET['tab'] = 'devis';

require_once __DIR__ . '/includes/pro_public_page.php';
tiramii_serve_pro_public_page();
