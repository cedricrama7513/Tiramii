<?php
/**
 * Génère admin_password_hash pour config/config.php (ligne de commande uniquement).
 * Usage : php tools/hash-password.php "VotreMotDePasse"
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'CLI uniquement.';
    exit(1);
}

$pw = $argv[1] ?? '';
if ($pw === '') {
    fwrite(STDERR, "Usage: php tools/hash-password.php \"mot_de_passe\"\n");
    exit(1);
}

echo password_hash($pw, PASSWORD_DEFAULT), PHP_EOL;
