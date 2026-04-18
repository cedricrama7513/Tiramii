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

require_once __DIR__ . '/includes/ensure_box_supreme.php';
require_once __DIR__ . '/includes/ensure_new_flavors.php';
require_once __DIR__ . '/includes/ensure_stock_levels.php';
tiramii_ensure_new_flavors($pdo);
tiramii_ensure_box_supreme($pdo);
tiramii_ensure_stock_levels_for_all_products($pdo);

require_once __DIR__ . '/includes/pro_b2b.php';
tiramii_ensure_pro_tables($pdo);

if (!empty($_SESSION['admin_ok']) && isset($_GET['download_pro_invoice'])) {
    tiramii_admin_pro_invoice_download($pdo);
}
if (!empty($_SESSION['admin_ok']) && isset($_GET['export_pro_ca_csv'])) {
    tiramii_admin_pro_ca_csv_export($pdo);
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

function tiramii_admin_day_heading(string $dayKey): string
{
    $tz = new DateTimeZone('Europe/Paris');
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $dayKey, $tz);
    if ($dt === false) {
        return $dayKey;
    }
    $names = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];

    return ucfirst($names[(int) $dt->format('w')]) . ' ' . $dt->format('d/m/Y');
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

// ——— Espace Pro (CA restaurants + factures) ———
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_pro_ca'])) {
    if (empty($_SESSION['admin_ok'])) {
        http_response_code(403);
        $errors[] = 'Non authentifié.';
    } elseif (!csrf_verify(csrf_token_from_request())) {
        $errors[] = 'Jeton CSRF invalide.';
    } else {
        $rest = mb_substr(trim((string) ($_POST['pro_restaurant'] ?? '')), 0, 255);
        if ($rest === '') {
            $errors[] = 'Indiquez le nom du restaurant (ou du client pro).';
        } else {
            $parsed = tiramii_pro_parse_amount_eur((string) ($_POST['pro_amount'] ?? ''));
            if (!$parsed['ok']) {
                $errors[] = $parsed['message'];
            } else {
                $d = trim((string) ($_POST['pro_ca_date'] ?? ''));
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                    $errors[] = 'Date invalide.';
                } else {
                    $note = mb_substr(trim((string) ($_POST['pro_note'] ?? '')), 0, 500);
                    try {
                        $ins = $pdo->prepare(
                            'INSERT INTO pro_ca_entries (restaurant_name, amount_eur, ca_date, note, created_at)
                             VALUES (?,?,?,?, NOW())'
                        );
                        $ins->execute([$rest, $parsed['amount'], $d, $note]);
                        $_SESSION['admin_flash_success'] = 'CA pro enregistré.';
                        header('Location: admin.php?tab=pro');
                        exit;
                    } catch (Throwable) {
                        $errors[] = 'Impossible d’enregistrer (vérifiez que les tables « pro » existent).';
                    }
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_pro_ca'])) {
    if (empty($_SESSION['admin_ok'])) {
        http_response_code(403);
        $errors[] = 'Non authentifié.';
    } elseif (!csrf_verify(csrf_token_from_request())) {
        $errors[] = 'Jeton CSRF invalide.';
    } else {
        $id = (int) ($_POST['pro_ca_id'] ?? 0);
        if ($id < 1) {
            $errors[] = 'Ligne invalide.';
        } else {
            try {
                $pdo->prepare('DELETE FROM pro_ca_entries WHERE id = ?')->execute([$id]);
                $_SESSION['admin_flash_success'] = 'Ligne CA supprimée.';
                header('Location: admin.php?tab=pro');
                exit;
            } catch (Throwable) {
                $errors[] = 'Suppression impossible.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_pro_invoice'])) {
    if (empty($_SESSION['admin_ok'])) {
        http_response_code(403);
        $errors[] = 'Non authentifié.';
    } elseif (!csrf_verify(csrf_token_from_request())) {
        $errors[] = 'Jeton CSRF invalide.';
    } else {
        $note = mb_substr(trim((string) ($_POST['invoice_note'] ?? '')), 0, 500);
        $f = $_FILES['invoice_pdf'] ?? null;
        if (!is_array($f) || !isset($f['error']) || (int) $f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Choisis un fichier PDF valide (max. 10 Mo).';
        } elseif ((int) ($f['size'] ?? 0) > 10 * 1024 * 1024) {
            $errors[] = 'Fichier trop volumineux (max. 10 Mo).';
        } else {
            $orig = mb_substr((string) ($f['name'] ?? 'facture.pdf'), 0, 255);
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                $errors[] = 'Seuls les fichiers PDF sont acceptés.';
            } else {
                $mime = 'application/pdf';
                if (class_exists('finfo')) {
                    $fi = new finfo(FILEINFO_MIME_TYPE);
                    $detected = $fi->file((string) $f['tmp_name']);
                    if (is_string($detected) && strpos($detected, 'application/') === 0) {
                        $mime = $detected;
                    }
                }
                $stored = bin2hex(random_bytes(16)) . '.pdf';
                $dir = tiramii_pro_data_dir();
                if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
                    $errors[] = 'Impossible de créer le dossier de stockage.';
                } else {
                    $dest = $dir . '/' . $stored;
                    if (!@move_uploaded_file((string) $f['tmp_name'], $dest)) {
                        $errors[] = 'Échec de l’enregistrement du fichier.';
                    } else {
                        try {
                            $ins = $pdo->prepare(
                                'INSERT INTO pro_invoices (original_name, stored_name, mime_type, size_bytes, note, uploaded_at)
                                 VALUES (?,?,?,?,?, NOW())'
                            );
                            $ins->execute([$orig, $stored, $mime, (int) $f['size'], $note]);
                            $_SESSION['admin_flash_success'] = 'Facture enregistrée.';
                            header('Location: admin.php?tab=pro');
                            exit;
                        } catch (Throwable) {
                            @unlink($dest);
                            $errors[] = 'Erreur base de données à l’enregistrement.';
                        }
                    }
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_pro_invoice'])) {
    if (empty($_SESSION['admin_ok'])) {
        http_response_code(403);
        $errors[] = 'Non authentifié.';
    } elseif (!csrf_verify(csrf_token_from_request())) {
        $errors[] = 'Jeton CSRF invalide.';
    } else {
        $id = (int) ($_POST['pro_invoice_id'] ?? 0);
        if ($id < 1) {
            $errors[] = 'Facture invalide.';
        } else {
            try {
                $st = $pdo->prepare('SELECT stored_name FROM pro_invoices WHERE id = ?');
                $st->execute([$id]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    $errors[] = 'Facture introuvable.';
                } else {
                    $base = basename((string) $row['stored_name']);
                    if (preg_match('/^[a-f0-9]{32}\.pdf$/i', $base) === 1) {
                        $path = tiramii_pro_data_dir() . '/' . $base;
                        if (is_file($path)) {
                            @unlink($path);
                        }
                    }
                    $pdo->prepare('DELETE FROM pro_invoices WHERE id = ?')->execute([$id]);
                    $_SESSION['admin_flash_success'] = 'Facture supprimée.';
                    header('Location: admin.php?tab=pro');
                    exit;
                }
            } catch (Throwable) {
                $errors[] = 'Suppression impossible.';
            }
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

/** @var array<string, array{orders: list<array<string, mixed>>, ca: float, count: int}> */
$ordersGroupedByDay = [];
$adminTzParis = new DateTimeZone('Europe/Paris');
if ($ordersList !== []) {
    foreach ($ordersList as $o) {
        $raw = (string) ($o['created_at'] ?? '');
        $ts = strtotime($raw);
        if ($ts === false) {
            $ts = time();
        }
        $d = (new DateTimeImmutable('@' . $ts))->setTimezone($adminTzParis);
        $dayKey = $d->format('Y-m-d');
        if (!isset($ordersGroupedByDay[$dayKey])) {
            $ordersGroupedByDay[$dayKey] = ['orders' => [], 'ca' => 0.0, 'count' => 0];
        }
        $ordersGroupedByDay[$dayKey]['orders'][] = $o;
        $ordersGroupedByDay[$dayKey]['ca'] += (float) $o['total_eur'];
        $ordersGroupedByDay[$dayKey]['count']++;
    }
}

$ordersPeriodCa = 0.0;
foreach ($ordersGroupedByDay as $bucket) {
    $ordersPeriodCa += $bucket['ca'];
}

$adminTab = (isset($_GET['tab']) && (string) $_GET['tab'] === 'pro') ? 'pro' : 'particulier';

$proCaEntries = [];
$proCaSummaryByMonth = [];
$proInvoices = [];
if ($loggedIn) {
    try {
        $proCaEntries = $pdo
            ->query(
                'SELECT id, restaurant_name, amount_eur, ca_date, note, created_at
                 FROM pro_ca_entries ORDER BY ca_date DESC, id DESC LIMIT 150'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        $proCaEntries = [];
    }
    try {
        $proCaSummaryByMonth = $pdo
            ->query(
                'SELECT DATE_FORMAT(ca_date, \'%Y-%m\') AS ym, restaurant_name, SUM(amount_eur) AS total_eur
                 FROM pro_ca_entries
                 GROUP BY ym, restaurant_name
                 ORDER BY ym DESC, restaurant_name ASC'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        $proCaSummaryByMonth = [];
    }
    try {
        $proInvoices = $pdo
            ->query(
                'SELECT id, original_name, size_bytes, note, uploaded_at
                 FROM pro_invoices ORDER BY uploaded_at DESC, id DESC LIMIT 80'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        $proInvoices = [];
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
.order-day-group{margin-top:20px}
.order-day-group:first-of-type{margin-top:4px}
.order-day-head{display:flex;flex-wrap:wrap;align-items:baseline;gap:8px 16px;padding:12px 16px;background:linear-gradient(90deg,#ede5f9,#f7f3fc);border-radius:16px;border:1px solid #e0d4ee;margin-bottom:12px}
.order-day-head h3{font-size:1.08rem;font-weight:700;color:var(--vk);font-family:'Playfair Display',serif;margin:0}
.order-day-head .day-count{font-size:.86rem;color:#836c91}
.order-day-head .day-ca{margin-left:auto;font-size:.95rem;color:var(--vd)}
.order-period-ca{margin:0 0 14px;padding:12px 16px;background:#f9f5fc;border-radius:14px;border:1px solid #eee3f8;font-size:.95rem;color:var(--vk)}
@media (max-width:640px){.order-day-head .day-ca{margin-left:0;width:100%}}
.admin-tabs{display:flex;gap:8px;margin-top:14px;padding:5px;background:#ede5f9;border-radius:999px;width:fit-content;max-width:100%;flex-wrap:wrap}
.admin-tabs a{padding:10px 20px;border-radius:999px;text-decoration:none;font-weight:600;color:var(--vd);font-size:.9rem}
.admin-tabs a.active{background:var(--vk);color:#fff}
.admin-tabs a:not(.active):hover{background:rgba(255,255,255,.65)}
.alerts-wrap{margin-top:4px}
.pro-table{width:100%;border-collapse:collapse;margin-top:12px;font-size:.86rem}
.pro-table th,.pro-table td{padding:8px 10px;text-align:left;border-bottom:1px solid #eee3f8;vertical-align:top}
.pro-table th{background:#f9f5fc;color:#836c91;font-weight:600}
.pro-table .num{text-align:right;white-space:nowrap}
.pro-month-total td{background:#f3ebfb;font-weight:600}
.form-row-pro{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:700px){.form-row-pro{grid-template-columns:1fr}}
.form-group-pro{margin-bottom:12px}
.form-group-pro label{display:block;font-size:.78rem;font-weight:600;color:var(--vd);text-transform:uppercase;letter-spacing:.04em;margin-bottom:5px}
.form-group-pro input,.form-group-pro textarea{width:100%;padding:10px 12px;border:1.5px solid #dfcff0;border-radius:12px;font-size:.95rem;color:var(--vk)}
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
  <p class="sub">Particuliers (site) et suivi <strong>Pro</strong> (restaurants / B2B).</p>
  <nav class="admin-tabs" aria-label="Sections admin">
    <a href="admin.php" class="<?= $adminTab === 'particulier' ? 'active' : '' ?>">Particuliers</a>
    <a href="admin.php?tab=pro" class="<?= $adminTab === 'pro' ? 'active' : '' ?>">Pro</a>
  </nav>
  <p class="small"><a class="btn-link secondary" href="index.php">← Site</a> &nbsp; <a class="btn-link secondary" href="admin.php?logout=1">Déconnexion</a></p>
</div>
<div class="wrap alerts-wrap">
  <?php if ($success !== ''): ?><p class="alert-ok"><?= h($success) ?></p><?php endif; ?>
  <?php foreach ($errors as $err): ?><p class="alert-bad"><?= h($err) ?></p><?php endforeach; ?>
</div>
<?php if ($adminTab === 'particulier'): ?>
<div class="wrap stack">
  <div class="card">
    <div class="topbar">
      <div>
        <div class="badge">📦 Gestion du stock</div>
        <p class="helper">Le site client lit le même stock. <strong>999</strong> = illimité : dans ce cas le stock <strong>ne diminue pas</strong> à la commande (réservé aux démos ou au lancement). Pour un décompte réel, mets une quantité <strong>strictement inférieure à 999</strong> (ex. 20, 50).</p>
      </div>
    </div>
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
        <p class="helper">Les <?= count($ordersList) ?> dernières commandes (max. 200), regroupées <strong>par jour</strong> (fuseau Europe/Paris). Le <strong>CA du jour</strong> est la somme des totaux des commandes de ce jour. Clique sur une commande pour le détail.</p>
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
      <p class="order-period-ca">
        <strong>CA cumulé</strong> (commandes affichées) : <?= h(number_format($ordersPeriodCa, 2, ',', ' ')) ?> €
        — <?= (int) count($ordersList) ?> commande<?= count($ordersList) > 1 ? 's' : '' ?> sur <?= (int) count($ordersGroupedByDay) ?> jour<?= count($ordersGroupedByDay) > 1 ? 's' : '' ?>
      </p>
      <?php foreach ($ordersGroupedByDay as $dayKey => $bucket): ?>
      <div class="order-day-group">
        <div class="order-day-head">
          <h3><?= h(tiramii_admin_day_heading($dayKey)) ?></h3>
          <span class="day-count"><?= (int) $bucket['count'] ?> commande<?= $bucket['count'] > 1 ? 's' : '' ?></span>
          <span class="day-ca">CA jour : <strong><?= h(number_format($bucket['ca'], 2, ',', ' ')) ?> €</strong></span>
        </div>
      <?php foreach ($bucket['orders'] as $o):
          $oid = (int) $o['id'];
          $items = $orderItemsByOrder[$oid] ?? [];
          $pay = $payLabelsAdmin[(string) $o['payment_method']] ?? (string) $o['payment_method'];
          $when = '—';
          if ((string) ($o['created_at'] ?? '') !== '') {
              $ts = strtotime((string) $o['created_at']);
              if ($ts !== false) {
                  $when = (new DateTimeImmutable('@' . $ts))->setTimezone($adminTzParis)->format('d/m/Y \à H:i');
              }
          }
          $fullName = trim((string) $o['first_name'] . ' ' . (string) $o['last_name']);
          $validatedRaw = $o['validated_at'] ?? null;
          $isValidated = $validatedRaw !== null && $validatedRaw !== '' && (string) $validatedRaw !== '0000-00-00 00:00:00';
          $validatedTs = $isValidated ? strtotime((string) $validatedRaw) : false;
          $whenValidated = ($validatedTs !== false && $validatedTs > 0)
              ? (new DateTimeImmutable('@' . $validatedTs))->setTimezone($adminTzParis)->format('d/m/Y \à H:i')
              : '';
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
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php else:
$todayPro = (new DateTimeImmutable('now', $adminTzParis))->format('Y-m-d');
$proMonthTotals = [];
foreach ($proCaSummaryByMonth as $sr) {
    $ym = (string) $sr['ym'];
    if (!isset($proMonthTotals[$ym])) {
        $proMonthTotals[$ym] = 0.0;
    }
    $proMonthTotals[$ym] += (float) $sr['total_eur'];
}
?>
<div class="wrap stack">
  <div class="card">
    <div class="topbar">
      <div>
        <div class="badge">📊 CA par restaurant (pro)</div>
        <p class="helper">Saisie manuelle pour tes ventes B2B (hors commandes du site particuliers). Utilise la date du CA ou de fin de période. La synthèse regroupe par mois calendaire (Europe/Paris).</p>
      </div>
      <a class="btn-link secondary" href="admin.php?export_pro_ca_csv=1">Exporter CSV</a>
    </div>
    <h3 style="font-size:1rem;margin:18px 0 10px;font-family:'Playfair Display',serif;color:var(--vk)">Nouvelle ligne</h3>
    <form method="post" action="admin.php?tab=pro">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <div class="form-row-pro">
        <div class="form-group-pro">
          <label for="pro_restaurant">Restaurant / client pro</label>
          <input type="text" name="pro_restaurant" id="pro_restaurant" required maxlength="255" placeholder="Ex. Bistrot du Coin">
        </div>
        <div class="form-group-pro">
          <label for="pro_amount">Montant TTC (€)</label>
          <input type="text" name="pro_amount" id="pro_amount" required inputmode="decimal" placeholder="Ex. 240,50">
        </div>
      </div>
      <div class="form-row-pro">
        <div class="form-group-pro">
          <label for="pro_ca_date">Date du CA</label>
          <input type="date" name="pro_ca_date" id="pro_ca_date" required value="<?= h($todayPro) ?>">
        </div>
        <div class="form-group-pro">
          <label for="pro_note">Note (optionnel)</label>
          <input type="text" name="pro_note" id="pro_note" maxlength="500" placeholder="Facture n°, règlement…">
        </div>
      </div>
      <div class="actions" style="margin-top:8px">
        <button type="submit" name="add_pro_ca" value="1" class="primary">➕ Enregistrer le CA</button>
      </div>
    </form>

    <h3 style="font-size:1rem;margin:28px 0 10px;font-family:'Playfair Display',serif;color:var(--vk)">Synthèse par mois et restaurant</h3>
    <?php if ($proCaSummaryByMonth === []): ?>
      <p class="muted">Aucune ligne CA pro pour l’instant.</p>
    <?php else: ?>
      <table class="pro-table">
        <thead><tr><th>Mois</th><th>Restaurant</th><th class="num">CA (€)</th></tr></thead>
        <tbody>
          <?php
          $lastYm = null;
          foreach ($proCaSummaryByMonth as $sr):
              $ym = (string) $sr['ym'];
              if ($lastYm !== null && $ym !== $lastYm):
                  ?>
          <tr class="pro-month-total"><td colspan="2">Total <?= h($lastYm) ?></td><td class="num"><?= h(number_format($proMonthTotals[$lastYm] ?? 0, 2, ',', ' ')) ?> €</td></tr>
              <?php
              endif;
              $lastYm = $ym;
              ?>
          <tr>
            <td><?= h($ym) ?></td>
            <td><?= h((string) $sr['restaurant_name']) ?></td>
            <td class="num"><?= h(number_format((float) $sr['total_eur'], 2, ',', ' ')) ?> €</td>
          </tr>
          <?php endforeach; ?>
          <?php if ($lastYm !== null): ?>
          <tr class="pro-month-total"><td colspan="2">Total <?= h($lastYm) ?></td><td class="num"><?= h(number_format($proMonthTotals[$lastYm] ?? 0, 2, ',', ' ')) ?> €</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <h3 style="font-size:1rem;margin:28px 0 10px;font-family:'Playfair Display',serif;color:var(--vk)">Dernières saisies</h3>
    <?php if ($proCaEntries === []): ?>
      <p class="muted">Aucune entrée.</p>
    <?php else: ?>
      <table class="pro-table">
        <thead><tr><th>Date</th><th>Restaurant</th><th class="num">Montant</th><th>Note</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($proCaEntries as $e): ?>
          <tr>
            <td><?= h((string) $e['ca_date']) ?></td>
            <td><?= h((string) $e['restaurant_name']) ?></td>
            <td class="num"><?= h(number_format((float) $e['amount_eur'], 2, ',', ' ')) ?> €</td>
            <td class="muted" style="font-size:.82rem"><?= h((string) $e['note'] !== '' ? (string) $e['note'] : '—') ?></td>
            <td class="num">
              <form method="post" action="admin.php?tab=pro" style="display:inline" onsubmit="return confirm('Supprimer cette ligne ?');">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="pro_ca_id" value="<?= (int) $e['id'] ?>">
                <button type="submit" name="delete_pro_ca" value="1" class="secondary btn-small" style="padding:6px 12px">Supprimer</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="topbar">
      <div>
        <div class="badge">📎 Factures pro (PDF)</div>
        <p class="helper">Dépôt de factures émises ou reçues (PDF uniquement, 10 Mo max). Les fichiers ne sont pas publics sur le site.</p>
      </div>
    </div>
    <form method="post" action="admin.php?tab=pro" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <div class="form-group-pro">
        <label for="invoice_pdf">Fichier PDF</label>
        <input type="file" name="invoice_pdf" id="invoice_pdf" accept="application/pdf,.pdf" required>
      </div>
      <div class="form-group-pro">
        <label for="invoice_note">Note (optionnel)</label>
        <input type="text" name="invoice_note" id="invoice_note" maxlength="500" placeholder="Client, mois, n° facture…">
      </div>
      <div class="actions">
        <button type="submit" name="upload_pro_invoice" value="1" class="primary">📤 Envoyer la facture</button>
      </div>
    </form>

    <h3 style="font-size:1rem;margin:28px 0 10px;font-family:'Playfair Display',serif;color:var(--vk)">Factures enregistrées</h3>
    <?php if ($proInvoices === []): ?>
      <p class="muted">Aucune facture.</p>
    <?php else: ?>
      <table class="pro-table">
        <thead><tr><th>Dépôt</th><th>Fichier</th><th class="num">Taille</th><th>Note</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($proInvoices as $inv): ?>
          <tr>
            <td><?= h((string) $inv['uploaded_at']) ?></td>
            <td><a href="admin.php?download_pro_invoice=<?= (int) $inv['id'] ?>"><?= h((string) $inv['original_name']) ?></a></td>
            <td class="num"><?php
              $sz = (int) $inv['size_bytes'];
              echo h($sz >= 1048576 ? number_format($sz / 1048576, 1, ',', ' ') . ' Mo' : ($sz >= 1024 ? (string) (int) round($sz / 1024) . ' Ko' : (string) $sz . ' o'));
              ?></td>
            <td class="muted" style="font-size:.82rem"><?= h((string) $inv['note'] !== '' ? (string) $inv['note'] : '—') ?></td>
            <td class="num">
              <form method="post" action="admin.php?tab=pro" style="display:inline" onsubmit="return confirm('Supprimer cette facture ?');">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="pro_invoice_id" value="<?= (int) $inv['id'] ?>">
                <button type="submit" name="delete_pro_invoice" value="1" class="secondary btn-small" style="padding:6px 12px">Supprimer</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>
</body>
</html>
