<?php
/**
 * Demande de devis pro — https://casadessert.fr/devis/
 */
declare(strict_types=1);

$_GET['tab'] = 'devis';

require dirname(__DIR__) . '/includes/pro_public_page.php';
tiramii_serve_pro_public_page();
