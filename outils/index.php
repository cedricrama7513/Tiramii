<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
$xlsx = 'suivi-factures-restaurants-pro.xlsx';
$xls = 'suivi-factures-restaurants-pro.xls';
$xlsxPath = __DIR__ . DIRECTORY_SEPARATOR . $xlsx;
$xlsPath = __DIR__ . DIRECTORY_SEPARATOR . $xls;
$xlsxOk = is_readable($xlsxPath);
$xlsOk = is_readable($xlsPath);
$xlsxV = $xlsxOk ? (string) filemtime($xlsxPath) : '';
$xlsV = $xlsOk ? (string) filemtime($xlsPath) : '';
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
  <p>Suivi factures pro : onglets <strong>My French factory</strong> et <strong>My french Cantine</strong>. Colonne C = montant facture → colonne E = solde recalculé automatiquement.</p>
  <?php if ($xlsxOk): ?>
  <a class="dl" href="<?= htmlspecialchars($xlsx . ($xlsxV !== '' ? '?v=' . $xlsxV : ''), ENT_QUOTES, 'UTF-8') ?>" download="<?= htmlspecialchars($xlsx, ENT_QUOTES, 'UTF-8') ?>">
    Télécharger le tableur Excel (.xlsx) — recommandé
  </a>
  <?php endif; ?>
  <?php if ($xlsOk): ?>
  <p style="margin-top:1rem;font-size:.9rem">
    <a href="<?= htmlspecialchars($xls . ($xlsV !== '' ? '?v=' . $xlsV : ''), ENT_QUOTES, 'UTF-8') ?>" download="<?= htmlspecialchars($xls, ENT_QUOTES, 'UTF-8') ?>">Ancien format .xls</a>
    (les formules marchent mieux en .xlsx)
  </p>
  <?php endif; ?>
  <?php if (!$xlsxOk && !$xlsOk): ?>
  <p class="err">Fichier Excel introuvable sur le serveur. Réessayez après le prochain déploiement.</p>
  <?php elseif ($xlsxOk): ?>
  <p style="font-size:.88rem;margin-top:.75rem">Ouvrez avec <strong>Microsoft Excel</strong>. Saisissez le montant en colonne C puis Entrée : le solde (E) se met à jour. Supprimez l’ancien fichier dans Téléchargements avant de retélécharger.</p>
  <?php endif; ?>
  <p style="margin-top:2rem;font-size:.9rem"><a href="/">← Retour au site</a></p>
</body>
</html>
