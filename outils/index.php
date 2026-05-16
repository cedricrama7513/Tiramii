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
    body { font-family: system-ui, sans-serif; max-width: 34rem; margin: 3rem auto; padding: 0 1rem; color: #2c241c; }
    a.dl { display: inline-block; margin-top: 1rem; padding: 12px 20px; background: #3d2e24; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; }
    a.dl:hover { background: #5a4336; }
    p { line-height: 1.5; color: #555; }
    .err { color: #b00020; }
    .warn { background: #fff8e1; border: 1px solid #f0e0a0; padding: 12px 14px; border-radius: 8px; font-size: .9rem; margin-top: 1rem; }
  </style>
</head>
<body>
  <h1>Outils Casa Dessert</h1>
  <p>Suivi factures pro : <strong>My French factory</strong> et <strong>My french Cantine</strong>. Saisissez le montant en <strong>colonne C</strong> → le <strong>solde (E)</strong> se met à jour.</p>
  <?php if ($xlsxOk): ?>
  <a class="dl" href="<?= htmlspecialchars($xlsx . ($xlsxV !== '' ? '?v=' . $xlsxV : ''), ENT_QUOTES, 'UTF-8') ?>" download="<?= htmlspecialchars($xlsx, ENT_QUOTES, 'UTF-8') ?>">
    Télécharger Excel (.xlsx) — à utiliser
  </a>
  <div class="warn">
    <strong>Important :</strong> n’utilisez pas l’ancien fichier .xls. Supprimez-le, retéléchargez le <strong>.xlsx</strong>, ouvrez avec <strong>Microsoft Excel</strong> (pas Aperçu). Montant en C → touche <strong>Entrée</strong>.
  </div>
  <?php endif; ?>
  <?php if ($xlsOk): ?>
  <p style="margin-top:1rem;font-size:.85rem;color:#888">
    <a href="<?= htmlspecialchars($xls . ($xlsV !== '' ? '?v=' . $xlsV : ''), ENT_QUOTES, 'UTF-8') ?>">Format .xls (déconseillé)</a>
  </p>
  <?php endif; ?>
  <?php if (!$xlsxOk && !$xlsOk): ?>
  <p class="err">Fichiers introuvables. Réessayez après déploiement.</p>
  <?php endif; ?>
  <p style="margin-top:2rem;font-size:.9rem"><a href="/">← Retour au site</a></p>
</body>
</html>
