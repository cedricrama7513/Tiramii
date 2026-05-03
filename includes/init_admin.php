<?php
/**
 * Espace admin : exiger une session authentifiée.
 * À inclure en tête de chaque script PHP protégé (hors page de login).
 */
declare(strict_types=1);

require_once __DIR__ . '/init_public.php';

if (empty($_SESSION['admin_ok']) || $_SESSION['admin_ok'] !== true) {
    http_response_code(403);
    header('Location: admin.php');
    exit;
}
