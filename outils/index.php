<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
$factures = 'suivi-factures-restaurants-pro.xls';
$renta = 'rentabilite-casa-dessert.xlsx';
$facturesPath = __DIR__ . DIRECTORY_SEPARATOR . $factures;
$rentaPath = __DIR__ . DIRECTORY_SEPARATOR . $renta;
$facturesOk = is_readable($facturesPath);
$rentaOk = is_readable($rentaPath);
$facturesV = $facturesOk ? (string) filemtime($facturesPath) : '';
$rentaV = $rentaOk ? (string) filemtime($rentaPath) : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Téléchargements — Casa Dessert</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 34rem; margin: 3rem auto; padding: 0 1rem; color: #2c241c; }
    a.dl { display: block; margin-top: 1rem; padding: 12px 20px; background: #3d2e24; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; }
    a.dl.secondary { background: #1b5e20; }
    a.dl:hover { filter: brightness(1.1); }
    p { line-height: 1.5; color: #555; }
    .err { color: #b00020; }
  </style>
</head>
<body>
  <h1>Outils Casa Dessert</h1>
  <?php if ($rentaOk): ?>
  <a class="dl secondary" href="<?= htmlspecialchars($renta . ($rentaV !== '' ? '?v=' . $rentaV : ''), ENT_QUOTES, 'UTF-8') ?>" download="<?= htmlspecialchars($renta, ENT_QUOTES, 'UTF-8') ?>">
    Tableur rentabilité (.xlsx) — CA, charges, bénéfice, BEP
  </a>
  <p style="font-size:.88rem">Saisissez votre CA et vos charges mois par mois. La synthèse calcule marge, bénéfice et seuil de rentabilité.</p>
  <?php endif; ?>
  <?php if ($facturesOk): ?>
  <a class="dl" href="<?= htmlspecialchars($factures . ($facturesV !== '' ? '?v=' . $facturesV : ''), ENT_QUOTES, 'UTF-8') ?>" download="<?= htmlspecialchars($factures, ENT_QUOTES, 'UTF-8') ?>">
    Suivi factures restaurants pro (.xls)
  </a>
  <?php endif; ?>
  <?php if (!$facturesOk && !$rentaOk): ?>
  <p class="err">Fichiers introuvables. Réessayez après déploiement.</p>
  <?php endif; ?>
  <p style="margin-top:2rem;font-size:.9rem"><a href="/">← Retour au site</a></p>
</body>
</html>
