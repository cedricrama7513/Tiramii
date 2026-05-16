<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
$xlsx = 'suivi-factures-restaurants-pro.xlsx';
$xlsxPath = __DIR__ . DIRECTORY_SEPARATOR . $xlsx;
$xlsxOk = is_readable($xlsxPath);
$xlsxV = $xlsxOk ? (string) filemtime($xlsxPath) : '';
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
    Télécharger Excel (.xlsx)
  </a>
  <div class="warn">
    <strong>Excel Mac :</strong> cliquez <strong>« Activer la modification »</strong> (bannière jaune), sinon les formules ne calculent pas.<br>
    Montant en <strong>colonne C</strong> → <strong>Entrée</strong> → <strong>solde (E)</strong> mis à jour.
  </div>
  <?php else: ?>
  <p class="err">Fichier introuvable. Réessayez après le prochain déploiement.</p>
  <?php endif; ?>
  <p style="margin-top:2rem;font-size:.9rem"><a href="/">← Retour au site</a></p>
</body>
</html>
