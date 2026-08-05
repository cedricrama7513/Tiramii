<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
$renta = 'rentabilite-casa-dessert.xlsx';
$xlsx = 'suivi-factures-restaurants-pro.xlsx';
$xls = 'suivi-factures-restaurants-pro.xls';
$rentaPath = __DIR__ . DIRECTORY_SEPARATOR . $renta;
$xlsxPath = __DIR__ . DIRECTORY_SEPARATOR . $xlsx;
$xlsPath = __DIR__ . DIRECTORY_SEPARATOR . $xls;
$rentaOk = is_readable($rentaPath);
$xlsxOk = is_readable($xlsxPath);
$xlsOk = is_readable($xlsPath);
$rentaV = $rentaOk ? (string) filemtime($rentaPath) : '';
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
    a.dl { display: block; margin-top: 1rem; padding: 12px 20px; background: #3d2e24; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; }
    a.dl.secondary { background: #1b5e20; }
    a.dl.tertiary { background: #5a4336; }
    a.dl:hover { filter: brightness(1.1); }
    p { line-height: 1.5; color: #555; }
    .err { color: #b00020; }
    .warn { background: #fff8e1; border: 1px solid #f0e0a0; padding: 12px 14px; border-radius: 8px; font-size: .9rem; margin-top: 1rem; }
  </style>
</head>
<body>
  <h1>Outils Casa Dessert</h1>
  <?php if ($rentaOk): ?>
  <a class="dl secondary" href="<?= htmlspecialchars($renta . ($rentaV !== '' ? '?v=' . $rentaV : ''), ENT_QUOTES, 'UTF-8') ?>" download="<?= htmlspecialchars($renta, ENT_QUOTES, 'UTF-8') ?>">
    Rentabilité (.xlsx) — CA, charges, bénéfice, BEP
  </a>
  <p style="font-size:.88rem">Saisissez CA et charges mois par mois → marge, bénéfice et seuil de rentabilité calculés.</p>
  <?php endif; ?>
  <?php
  $mascaPdf = 'fournisseurs-mascarpone-casa-dessert.pdf';
  $mascaPath = __DIR__ . DIRECTORY_SEPARATOR . $mascaPdf;
  if (is_readable($mascaPath)):
    $mascaV = (string) filemtime($mascaPath);
  ?>
  <a class="dl tertiary" href="<?= htmlspecialchars($mascaPdf . '?v=' . $mascaV, ENT_QUOTES, 'UTF-8') ?>" download="<?= htmlspecialchars($mascaPdf, ENT_QUOTES, 'UTF-8') ?>">
    Fournisseurs mascarpone moins cher (PDF)
  </a>
  <p style="font-size:.88rem">Comparatif prix HT + contacts (Mozzalat, Rungis, Bellon…) pour baisser le coût matière.</p>
  <?php endif; ?>
  <?php
  $planPdf = 'plan-action-ca-casa-dessert.pdf';
  $planPath = __DIR__ . DIRECTORY_SEPARATOR . $planPdf;
  if (is_readable($planPath)):
    $planV = (string) filemtime($planPath);
  ?>
  <a class="dl tertiary" href="<?= htmlspecialchars($planPdf . '?v=' . $planV, ENT_QUOTES, 'UTF-8') ?>" download="<?= htmlspecialchars($planPdf, ENT_QUOTES, 'UTF-8') ?>">
    Plan d'action CA (PDF)
  </a>
  <?php endif; ?>
  <?php if ($xlsxOk): ?>
  <a class="dl" href="<?= htmlspecialchars($xlsx . ($xlsxV !== '' ? '?v=' . $xlsxV : ''), ENT_QUOTES, 'UTF-8') ?>" download="<?= htmlspecialchars($xlsx, ENT_QUOTES, 'UTF-8') ?>">
    Suivi factures restaurants pro (.xlsx)
  </a>
  <div class="warn">
    <strong>Excel Mac :</strong> cliquez <strong>« Activer la modification »</strong> (bannière jaune). Montant en <strong>colonne C</strong> → <strong>Entrée</strong> → solde (E) mis à jour.
  </div>
  <?php endif; ?>
  <?php if ($xlsOk): ?>
  <p style="margin-top:1rem;font-size:.85rem;color:#888">
    <a href="<?= htmlspecialchars($xls . ($xlsV !== '' ? '?v=' . $xlsV : ''), ENT_QUOTES, 'UTF-8') ?>">Ancien suivi factures (.xls)</a>
  </p>
  <?php endif; ?>
  <?php if (!$rentaOk && !$xlsxOk && !$xlsOk): ?>
  <p class="err">Fichiers introuvables. Réessayez après déploiement.</p>
  <?php endif; ?>
  <p style="margin-top:2rem;font-size:.9rem"><a href="/">← Retour au site</a></p>
</body>
</html>
