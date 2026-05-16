<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
$xls = 'suivi-factures-restaurants-pro.xls';
$xlsOk = is_readable(__DIR__ . DIRECTORY_SEPARATOR . $xls);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Téléchargements — Casa Dessert</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 32rem; margin: 3rem auto; padding: 0 1rem; color: #2c241c; }
    a.dl { display: inline-block; margin-top: 1rem; padding: 12px 20px; background: #3d2e24; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; }
    a.dl:hover { background: #5a4336; }
    p { line-height: 1.5; color: #555; }
    .err { color: #b00020; }
  </style>
</head>
<body>
  <h1>Outils Casa Dessert</h1>
  <p>Suivi des factures restaurants pro : alerte automatique au-delà de 500&nbsp;€ (facture ou encours impayé).</p>
  <?php if ($xlsOk): ?>
  <a class="dl" href="<?= htmlspecialchars($xls, ENT_QUOTES, 'UTF-8') ?>" download="<?= htmlspecialchars($xls, ENT_QUOTES, 'UTF-8') ?>">
    Télécharger le tableur Excel (.xls)
  </a>
  <?php else: ?>
  <p class="err">Fichier Excel introuvable sur le serveur. Réessayez après le prochain déploiement.</p>
  <?php endif; ?>
  <p style="margin-top:2rem;font-size:.9rem"><a href="/">← Retour au site</a></p>
</body>
</html>
