<?php
/**
 * Admin stock TIRA'MII — session PHP + mot de passe (hash dans config.php).
 * Vérification de session : chaque action protégée contrôle $_SESSION['admin_ok'].
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init_public.php';

$configFile = __DIR__ . '/config/config.php';
if (!is_readable($configFile)) {
    http_response_code(500);
    echo 'Créez config/config.php à partir de config.example.php';
    exit;
}
/** @var array $appCfg */
$appCfg = require $configFile;
$adminHash = (string) ($appCfg['admin_password_hash'] ?? '');

try {
    $pdo = require __DIR__ . '/config/db.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Base de données non configurée.';
    exit;
}

/**
 * True si la migration « validation commande » a été appliquée (colonne validated_at).
 */
function tiramii_admin_orders_has_validated_at(PDO $pdo): bool
{
    try {
        $st = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'validated_at'");
        return $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
    } catch (Throwable) {
        return false;
    }
}

$errors = [];
$success = '';
if (!empty($_SESSION['admin_flash_success'])) {
    $success = (string) $_SESSION['admin_flash_success'];
    unset($_SESSION['admin_flash_success']);
}

// Déconnexion
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_ok']);
    header('Location: admin.php');
    exit;
}

// Connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    if (!csrf_verify(csrf_token_from_request())) {
        $errors[] = 'Jeton CSRF invalide.';
    } elseif ($adminHash === '') {
        $errors[] = 'Définissez admin_password_hash dans config.php (voir DEPLOYMENT.md).';
    } elseif (!password_verify((string) ($_POST['password'] ?? ''), $adminHash)) {
        $errors[] = 'Mot de passe incorrect.';
    } else {
        $_SESSION['admin_ok'] = true;
        header('Location: admin.php');
        exit;
    }
}

// Mise à jour stock (protégé)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_stock'])) {
    if (empty($_SESSION['admin_ok'])) {
        http_response_code(403);
        $errors[] = 'Non authentifié.';
    } elseif (!csrf_verify(csrf_token_from_request())) {
        $errors[] = 'Jeton CSRF invalide.';
    } else {
        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare(
                'INSERT INTO stock_levels (product_id, quantity) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)'
            );
            foreach ($_POST['stock'] ?? [] as $pid => $qty) {
                $pid = preg_replace('/[^a-z0-9_]/i', '', (string) $pid);
                if ($pid === '') {
                    continue;
                }
                $q = (int) $qty;
                if ($q < 0) {
                    $q = 0;
                }
                if ($q > 99999) {
                    $q = 99999;
                }
                $upd->execute([$pid, $q]);
            }
            $pdo->commit();
            $success = 'Stock enregistré.';
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Erreur lors de l\'enregistrement.';
        }
    }
}

// Marquer une commande comme validée (livraison / traitement terminé)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validate_order'])) {
    if (empty($_SESSION['admin_ok'])) {
        http_response_code(403);
        $errors[] = 'Non authentifié.';
    } elseif (!csrf_verify(csrf_token_from_request())) {
        $errors[] = 'Jeton CSRF invalide.';
    } else {
        $oid = (int) ($_POST['order_id'] ?? 0);
        if ($oid < 1) {
            $errors[] = 'Commande invalide.';
        } elseif (!tiramii_admin_orders_has_validated_at($pdo)) {
            $errors[] = 'La colonne SQL validated_at est absente. Dans phpMyAdmin, exécutez : ALTER TABLE orders ADD COLUMN validated_at DATETIME NULL DEFAULT NULL AFTER created_at;';
        } else {
            $st = $pdo->prepare('UPDATE orders SET validated_at = NOW() WHERE id = ? AND validated_at IS NULL');
            $st->execute([$oid]);
            if ($st->rowCount() === 0) {
                $chk = $pdo->prepare('SELECT 1 FROM orders WHERE id = ?');
                $chk->execute([$oid]);
                if (!$chk->fetch()) {
                    $errors[] = 'Commande introuvable.';
                } else {
                    $_SESSION['admin_flash_success'] = 'La commande #' . $oid . ' était déjà marquée comme validée.';
                }
            } else {
                $_SESSION['admin_flash_success'] = 'Commande #' . $oid . ' marquée comme validée.';
            }
            if ($errors === []) {
                header('Location: admin.php#order-' . $oid);
                exit;
            }
        }
    }
}

// Ajout colonne validated_at depuis l’admin (évite phpMyAdmin si les droits MySQL le permettent)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_validated_at_migration'])) {
    if (empty($_SESSION['admin_ok'])) {
        http_response_code(403);
        $errors[] = 'Non authentifié.';
    } elseif (!csrf_verify(csrf_token_from_request())) {
        $errors[] = 'Jeton CSRF invalide.';
    } elseif (tiramii_admin_orders_has_validated_at($pdo)) {
        $_SESSION['admin_flash_success'] = 'La colonne validated_at est déjà en place.';
        header('Location: admin.php');
        exit;
    } else {
        $alterOk = false;
        try {
            $pdo->exec('ALTER TABLE orders ADD COLUMN validated_at DATETIME NULL DEFAULT NULL AFTER created_at');
            $alterOk = true;
        } catch (Throwable) {
            /* droits ALTER refusés sur certains hébergeurs */
        }
        if (!$alterOk) {
            $errors[] = 'La migration automatique a échoué (droits MySQL ou hébergeur). Utilisez phpMyAdmin avec la commande ci-dessous.';
        } else {
            try {
                $pdo->exec('CREATE INDEX idx_validated ON orders (validated_at)');
            } catch (Throwable) {
                /* index peut déjà exister */
            }
            $_SESSION['admin_flash_success'] = 'Base à jour : vous pouvez marquer les commandes comme validées.';
            header('Location: admin.php');
            exit;
        }
    }
}

$loggedIn = !empty($_SESSION['admin_ok']);

$products = $pdo->query(
    'SELECT p.id, p.name, p.price_eur, COALESCE(s.quantity, 0) AS quantity
     FROM products p
     LEFT JOIN stock_levels s ON s.product_id = p.id
     ORDER BY p.sort_order ASC, p.id ASC'
)->fetchAll(PDO::FETCH_ASSOC);

$ordersList = [];
$orderItemsByOrder = [];
$ordersHasValidatedAt = false;
if ($loggedIn) {
    $ordersHasValidatedAt = tiramii_admin_orders_has_validated_at($pdo);
    $orderSelectCols = 'id, first_name, last_name, phone, address_line, zip, city, delivery_time, note,
        payment_method, total_eur, created_at';
    if ($ordersHasValidatedAt) {
        $orderSelectCols .= ', validated_at';
    }
    try {
        $ordersList = $pdo
            ->query(
                'SELECT ' . $orderSelectCols . '
                 FROM orders
                 ORDER BY created_at DESC, id DESC
                 LIMIT 200'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        $ordersList = [];
    }
    if (!$ordersHasValidatedAt) {
        foreach ($ordersList as $k => $row) {
            $ordersList[$k]['validated_at'] = null;
        }
    }

    if ($ordersList !== []) {
        $ids = array_map('intval', array_column($ordersList, 'id'));
        $inList = implode(',', $ids);
        $itemRows = $pdo
            ->query(
                "SELECT order_id, product_label, quantity, unit_price_eur, box_label
                 FROM order_items WHERE order_id IN ($inList) ORDER BY id ASC"
            )
            ->fetchAll(PDO::FETCH_ASSOC);
        foreach ($itemRows as $r) {
            $oid = (int) $r['order_id'];
            if (!isset($orderItemsByOrder[$oid])) {
                $orderItemsByOrder[$oid] = [];
            }
            $orderItemsByOrder[$oid][] = $r;
        }
    }
}

$payLabelsAdmin = [
    'cash' => 'Espèces',
    'virement' => 'Virement bancaire',
    'wero' => 'Wero',
];

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TIRA'MII — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--v:#c8a8e9;--vd:#7c4daa;--vk:#3d1f6e;--bg:#f3eafc;--card:#ffffff;--ok:#2e7d32;--danger:#d64545}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:linear-gradient(180deg,#f7f1fd 0%,#efe4fb 100%);color:var(--vk);min-height:100vh}
.header{padding:28px 20px 10px;max-width:980px;margin:0 auto}
.brand{display:flex;align-items:center;gap:12px;font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:600}
.logo{width:42px;height:42px;border:2px solid var(--vd);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--vd)}
.sub{margin-top:8px;color:#7a6488}
.wrap{max-width:980px;margin:0 auto;padding:20px}
.card{background:var(--card);border-radius:24px;box-shadow:0 18px 50px rgba(61,31,110,.08);padding:22px}
.topbar{display:flex;gap:12px;justify-content:space-between;align-items:center;flex-wrap:wrap;margin-bottom:16px}
.badge{background:#f1e7fb;color:var(--vd);padding:8px 12px;border-radius:999px;font-size:.86rem;font-weight:600}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-top:18px}
.item{border:1px solid #eee3f8;border-radius:18px;padding:16px;background:#fff}
.item h3{font-size:1rem;margin-bottom:6px}
.muted{font-size:.88rem;color:#836c91}
.row{display:flex;gap:10px;align-items:center;justify-content:space-between;margin-top:14px}
input[type=number],input[type=password]{width:100%;padding:12px 14px;border:1.5px solid #dfcff0;border-radius:14px;font-size:1rem;color:var(--vk)}
.qty{width:110px !important}
button,.btn-link{border:none;border-radius:999px;padding:13px 18px;font-weight:700;font-family:'DM Sans',sans-serif;cursor:pointer;text-decoration:none;display:inline-block}
.primary{background:var(--vk);color:#fff}
.secondary{background:#efe4fb;color:var(--vd)}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}
.helper{margin-top:14px;font-size:.9rem;color:#7a6488;line-height:1.5}
.alert-ok{color:var(--ok);font-weight:700;margin-bottom:12px}
.alert-bad{color:var(--danger);font-weight:700;margin-bottom:8px}
.lock{max-width:460px;margin:10vh auto 0;padding:20px}
.lock .card{padding:28px}
.lock h1{font-family:'Playfair Display',serif;font-size:2rem;margin-bottom:8px}
.small{font-size:.82rem;color:#836c91}
code{background:#f3ebfb;padding:2px 6px;border-radius:8px}
@media (max-width:640px){.topbar{align-items:flex-start} .actions button{width:100%} .qty{width:96px !important}}
.stack{display:flex;flex-direction:column;gap:22px}
.order-block{border:1px solid #eee3f8;border-radius:18px;margin-top:12px;overflow:hidden;background:#faf8fc}
.order-block summary{cursor:pointer;list-style:none;padding:14px 16px;font-weight:600;display:flex;flex-wrap:wrap;gap:8px 16px;align-items:center;background:#f3ebfb}
.order-block summary::-webkit-details-marker{display:none}
.order-block summary::after{content:'▾';margin-left:auto;color:var(--vd);font-size:.85rem}
.order-block[open] summary::after{content:'▴'}
.order-meta{font-size:.82rem;font-weight:500;color:#836c91}
.order-body{padding:16px 18px 18px;border-top:1px solid #eee3f8}
.dl-grid{display:grid;grid-template-columns:140px 1fr;gap:6px 14px;font-size:.9rem}
.dl-grid dt{color:#836c91;font-weight:500}
.dl-grid dd{word-break:break-word}
.order-lines{width:100%;border-collapse:collapse;margin-top:14px;font-size:.86rem}
.order-lines th,.order-lines td{padding:8px 10px;text-align:left;border-bottom:1px solid #eee3f8}
.order-lines th{color:#836c91;font-weight:600;background:#f9f5fc}
.order-lines .num{text-align:right;white-space:nowrap}
.empty-orders{color:#836c91;font-size:.92rem;padding:12px 0}
.status-pill{display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;font-size:.72rem;font-weight:700;letter-spacing:.02em;text-transform:uppercase}
.status-pending{background:#fff3e0;color:#e65100}
.status-done{background:#e8f5e9;color:#2e7d32}
.btn-small{padding:10px 16px;font-size:.86rem;border-radius:999px}
.order-actions{margin-top:16px;padding-top:14px;border-top:1px dashed #e0d4ee}
.summary-badges{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
</style>
</head>
<body>

<?php if (!$loggedIn): ?>
<div class="lock">
  <div class="card">
    <h1>Connexion admin</h1>
    <p class="sub">Accès réservé à TIRA'MII — stock et commandes.</p>
    <?php foreach ($errors as $err): ?>
      <p class="alert-bad"><?= h($err) ?></p>
    <?php endforeach; ?>
    <form method="post" action="admin.php">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="password" name="password" placeholder="Mot de passe admin" required>
      <div class="actions"><button type="submit" name="admin_login" value="1" class="primary">Entrer</button></div>
    </form>
    <p class="helper">Configure <code>admin_password_hash</code> dans <code>config/config.php</code> (voir DEPLOYMENT.md).</p>
  </div>
</div>
<?php else: ?>
<div class="header">
  <div class="brand"><div class="logo">T</div> TIRA'MII</div>
  <p class="sub">Stock et commandes clients (MySQL).</p>
  <p class="small"><a class="btn-link secondary" href="index.php">← Site</a> &nbsp; <a class="btn-link secondary" href="admin.php?logout=1">Déconnexion</a></p>
</div>
<div class="wrap stack">
  <div class="card">
    <div class="topbar">
      <div>
        <div class="badge">📦 Gestion du stock</div>
        <p class="helper">Le site client lit le même stock. Astuce : <strong>999</strong> = illimité.</p>
      </div>
    </div>
    <?php if ($success !== ''): ?><p class="alert-ok"><?= h($success) ?></p><?php endif; ?>
    <?php foreach ($errors as $err): ?><p class="alert-bad"><?= h($err) ?></p><?php endforeach; ?>
    <form method="post" action="admin.php">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <div class="grid">
        <?php foreach ($products as $p): ?>
        <div class="item">
          <h3><?= h($p['name']) ?></h3>
          <div class="muted">Prix : <?= h(number_format((float) $p['price_eur'], 2, ',', '')) ?>€ — id <?= h($p['id']) ?></div>
          <div class="muted">Stock actuel : <strong><?= (int) $p['quantity'] === 999 ? 'Illimité' : h((string) (int) $p['quantity']) ?></strong></div>
          <div class="row">
            <label class="muted" for="stock-<?= h($p['id']) ?>">Nouveau stock</label>
            <input class="qty" type="number" name="stock[<?= h($p['id']) ?>]" id="stock-<?= h($p['id']) ?>" min="0" step="1" value="<?= (int) $p['quantity'] ?>">
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="actions">
        <button type="submit" name="save_stock" value="1" class="primary">💾 Enregistrer</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="topbar">
      <div>
        <div class="badge">📋 Commandes</div>
        <p class="helper">Les <?= count($ordersList) ?> dernières commandes (max. 200), les plus récentes en premier. Clique sur une ligne pour voir le détail et les articles.</p>
      </div>
    </div>
    <?php if ($loggedIn && !$ordersHasValidatedAt): ?>
      <div class="alert-bad" style="margin-bottom:14px;padding:14px;border-radius:14px;background:#ffebee;border:1px solid #ffcdd2;font-size:.9rem;line-height:1.45;color:#b71c1c">
        <strong>Migration SQL requise</strong> pour activer le bouton « Commande validée ».
        <form method="post" action="admin.php" style="margin:12px 0 8px;display:flex;flex-wrap:wrap;gap:10px;align-items:center">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <button type="submit" name="apply_validated_at_migration" value="1" class="primary btn-small">Appliquer la mise à jour automatiquement</button>
        </form>
        <span style="opacity:.95">Sinon, dans phpMyAdmin → SQL :</span><br>
        <code style="display:block;margin-top:6px;font-size:.78rem;word-break:break-all;color:#3d1f6e;background:#fff;padding:8px 10px;border-radius:10px">ALTER TABLE orders ADD COLUMN validated_at DATETIME NULL DEFAULT NULL AFTER created_at;</code>
      </div>
    <?php endif; ?>
    <?php if ($ordersList === []): ?>
      <p class="empty-orders">Aucune commande enregistrée pour l’instant.</p>
    <?php else: ?>
      <?php foreach ($ordersList as $o):
          $oid = (int) $o['id'];
          $items = $orderItemsByOrder[$oid] ?? [];
          $pay = $payLabelsAdmin[(string) $o['payment_method']] ?? (string) $o['payment_method'];
          $when = $o['created_at'] !== '' ? date('d/m/Y à H:i', strtotime((string) $o['created_at'])) : '—';
          $fullName = trim((string) $o['first_name'] . ' ' . (string) $o['last_name']);
          $validatedRaw = $o['validated_at'] ?? null;
          $isValidated = $validatedRaw !== null && $validatedRaw !== '' && (string) $validatedRaw !== '0000-00-00 00:00:00';
          $validatedTs = $isValidated ? strtotime((string) $validatedRaw) : false;
          $whenValidated = ($validatedTs !== false && $validatedTs > 0) ? date('d/m/Y à H:i', $validatedTs) : '';
          ?>
      <details class="order-block" id="order-<?= (int) $oid ?>">
        <summary>
          <span class="summary-badges">
            <span>#<?= (int) $oid ?></span>
            <?php if ($isValidated): ?>
              <span class="status-pill status-done">Validée</span>
            <?php else: ?>
              <span class="status-pill status-pending">En attente</span>
            <?php endif; ?>
          </span>
          <span><?= h($fullName !== '' ? $fullName : (string) $o['first_name']) ?></span>
          <span class="order-meta"><?= h($when) ?></span>
          <span class="order-meta"><?= h(number_format((float) $o['total_eur'], 2, ',', ' ')) ?> €</span>
        </summary>
        <div class="order-body">
          <dl class="dl-grid">
            <dt>Prénom</dt><dd><?= h((string) $o['first_name']) ?></dd>
            <dt>Nom</dt><dd><?= h((string) $o['last_name'] !== '' ? (string) $o['last_name'] : '—') ?></dd>
            <dt>Téléphone</dt><dd><a href="tel:<?= h(preg_replace('/\s+/', '', (string) $o['phone'])) ?>"><?= h((string) $o['phone']) ?></a></dd>
            <dt>Adresse</dt><dd><?= h((string) $o['address_line']) ?></dd>
            <dt>Code postal</dt><dd><?= h((string) $o['zip'] !== '' ? (string) $o['zip'] : '—') ?></dd>
            <dt>Ville</dt><dd><?= h((string) $o['city'] !== '' ? (string) $o['city'] : '—') ?></dd>
            <dt>Créneau livraison</dt><dd><?= h((string) $o['delivery_time'] !== '' ? (string) $o['delivery_time'] : '—') ?></dd>
            <dt>Paiement</dt><dd><?= h($pay) ?></dd>
            <dt>Note client</dt><dd><?= h((string) ($o['note'] ?? '') !== '' ? (string) $o['note'] : '—') ?></dd>
            <dt>Total</dt><dd><strong><?= h(number_format((float) $o['total_eur'], 2, ',', ' ')) ?> €</strong></dd>
            <dt>État</dt><dd><?= $isValidated ? '<span class="status-pill status-done">Validée</span> le ' . h($whenValidated) : '<span class="status-pill status-pending">En attente de validation</span>' ?></dd>
          </dl>
          <?php if ($items !== []): ?>
          <table class="order-lines">
            <thead>
              <tr><th>Article</th><th class="num">Qté</th><th class="num">Prix u.</th><th class="num">Sous-total</th></tr>
            </thead>
            <tbody>
              <?php foreach ($items as $it):
                  $q = (int) $it['quantity'];
                  $up = (float) $it['unit_price_eur'];
                  $sub = $q * $up;
                  $bl = isset($it['box_label']) && $it['box_label'] !== null && (string) $it['box_label'] !== ''
                      ? ' — ' . (string) $it['box_label']
                      : '';
                  ?>
              <tr>
                <td><?= h((string) $it['product_label']) ?><?= h($bl) ?></td>
                <td class="num"><?= (int) $q ?></td>
                <td class="num"><?= h(number_format($up, 2, ',', ' ')) ?> €</td>
                <td class="num"><?= h(number_format($sub, 2, ',', ' ')) ?> €</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php else: ?>
            <p class="muted" style="margin-top:12px">Aucune ligne d’article (données anciennes ou anomalie).</p>
          <?php endif; ?>
          <?php if (!$isValidated && $ordersHasValidatedAt): ?>
          <div class="order-actions">
            <form method="post" action="admin.php#order-<?= (int) $oid ?>">
              <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
              <input type="hidden" name="order_id" value="<?= (int) $oid ?>">
              <button type="submit" name="validate_order" value="1" class="primary btn-small">✓ Commande validée (livrée / traitée)</button>
            </form>
            <p class="muted" style="margin-top:8px;font-size:.82rem">Une fois la livraison ou le retrait effectué, clique pour archiver l’état « validée ».</p>
          </div>
          <?php endif; ?>
        </div>
      </details>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
</body>
</html>
