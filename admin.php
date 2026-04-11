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

$errors = [];
$success = '';

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

$loggedIn = !empty($_SESSION['admin_ok']);

$products = $pdo->query(
    'SELECT p.id, p.name, p.price_eur, COALESCE(s.quantity, 0) AS quantity
     FROM products p
     LEFT JOIN stock_levels s ON s.product_id = p.id
     ORDER BY p.sort_order ASC, p.id ASC'
)->fetchAll(PDO::FETCH_ASSOC);

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TIRA'MII — Admin stock</title>
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
</style>
</head>
<body>

<?php if (!$loggedIn): ?>
<div class="lock">
  <div class="card">
    <h1>Admin stock</h1>
    <p class="sub">Accès réservé à TIRA'MII. Entre le mot de passe pour gérer le stock.</p>
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
  <p class="sub">Tableau de bord du stock (base MySQL).</p>
  <p class="small"><a class="btn-link secondary" href="index.php">← Site</a> &nbsp; <a class="btn-link secondary" href="admin.php?logout=1">Déconnexion</a></p>
</div>
<div class="wrap">
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
</div>
<?php endif; ?>
</body>
</html>
