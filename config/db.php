<?php
/**
 * Connexion PDO MySQL — requêtes préparées uniquement côté appelants.
 */
declare(strict_types=1);

$configFile = __DIR__ . '/config.php';
if (!is_readable($configFile)) {
    throw new RuntimeException(
        'Fichier config/config.php introuvable. Copiez config/config.example.php vers config.php et renseignez la base de données.'
    );
}
/** @var array $cfg */
$cfg = require $configFile;
$db = $cfg['db'];
foreach (['host', 'name', 'user', 'pass'] as $k) {
    if (isset($db[$k]) && is_string($db[$k])) {
        $db[$k] = trim($db[$k]);
    }
}

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    $db['host'],
    $db['name'],
    $db['charset'] ?? 'utf8mb4'
);

$pdo = new PDO($dsn, $db['user'], $db['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

return $pdo;
