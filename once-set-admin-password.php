<?php
/**
 * À utiliser UNE FOIS sur le serveur si admin_password_hash est vide.
 * 1. Uploader ce fichier à la racine du site (à côté de admin.php).
 * 2. Ouvrir https://tiramii.fr/once-set-admin-password.php
 * 3. Choisir un mot de passe (8 caractères minimum), valider.
 * 4. Le fichier se supprime tout seul ; connectez-vous sur admin.php.
 *
 * Ne laissez pas ce fichier sur le serveur après utilisation.
 */
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');

$configPath = __DIR__ . '/config/config.php';
if (!is_readable($configPath) || !is_writable($configPath)) {
    echo '<p style="font-family:sans-serif;max-width:520px;margin:2rem auto">Le fichier <code>config/config.php</code> doit exister et être <strong>modifiable</strong> (droits d’écriture sur Hostinger).</p>';
    exit;
}

/** @var array $cfg */
$cfg = require $configPath;
$existing = trim((string) ($cfg['admin_password_hash'] ?? ''));
if ($existing !== '' && strlen($existing) >= 60 && strpos($existing, '$2') === 0) {
    echo '<p style="font-family:sans-serif;max-width:520px;margin:2rem auto">Le mot de passe admin est déjà configuré. Supprimez <code>once-set-admin-password.php</code> du serveur.</p>';
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = (string) ($_POST['new_password'] ?? '');
    $pw2 = (string) ($_POST['new_password_confirm'] ?? '');
    if (strlen($pw) < 8) {
        $error = 'Le mot de passe doit faire au moins 8 caractères.';
    } elseif ($pw !== $pw2) {
        $error = 'Les deux mots de passe ne correspondent pas.';
    } else {
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        $raw = file_get_contents($configPath);
        if ($raw === false) {
            $error = 'Lecture de config.php impossible.';
        } else {
            $newRaw = preg_replace_callback(
                "/'admin_password_hash'\\s*=>\\s*'[^']*'/",
                static function () use ($hash) {
                    return "'admin_password_hash' => '" . $hash . "'";
                },
                $raw,
                1,
                $count
            );
            if ($count === 0) {
                $newRaw = preg_replace(
                    '/\s*\];\s*$/',
                    "    'admin_password_hash' => '" . $hash . "',\n];",
                    $raw,
                    1,
                    $injectCount
                );
                if ($injectCount === 0) {
                    $error = 'Impossible de mettre à jour config.php (structure inattendue). Éditez le fichier à la main.';
                }
            }
            if ($error === '' && !is_string($newRaw)) {
                $error = 'Erreur lors de la mise à jour.';
            }
            if ($error === '' && is_string($newRaw) && file_put_contents($configPath, $newRaw) === false) {
                $error = 'Écriture de config.php refusée (vérifiez les permissions).';
            }
            if ($error === '') {
                @unlink(__FILE__);
                header('Location: admin.php', true, 302);
                echo '<p>Redirection… <a href="admin.php">admin.php</a></p>';
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Définir le mot de passe admin — Casa Dessert</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 420px; margin: 2rem auto; padding: 0 1rem; color: #3d1f6e; }
    label { display: block; margin-top: 1rem; font-weight: 600; }
    input { width: 100%; padding: 10px; margin-top: 6px; box-sizing: border-box; border-radius: 10px; border: 1px solid #ccc; }
    button { margin-top: 1.25rem; padding: 12px 20px; background: #3d1f6e; color: #fff; border: none; border-radius: 999px; font-weight: 700; cursor: pointer; }
    .err { color: #b00020; margin-top: 1rem; }
    .hint { font-size: .9rem; color: #666; margin-top: 1.5rem; }
  </style>
</head>
<body>
  <h1>Mot de passe admin</h1>
  <p>Cette page ne fonctionne que tant que <code>admin_password_hash</code> est vide. Elle sera supprimée après succès.</p>
  <?php if ($error !== ''): ?><p class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
  <form method="post" action="">
    <label for="new_password">Nouveau mot de passe (min. 8 caractères)</label>
    <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
    <label for="new_password_confirm">Confirmation</label>
    <input type="password" id="new_password_confirm" name="new_password_confirm" required minlength="8" autocomplete="new-password">
    <button type="submit">Enregistrer et aller à l’admin</button>
  </form>
  <p class="hint">Si l’enregistrement échoue, éditez <code>config/config.php</code> dans le gestionnaire de fichiers Hostinger et collez une ligne générée avec <code>php tools/hash-password.php</code> en local.</p>
</body>
</html>
