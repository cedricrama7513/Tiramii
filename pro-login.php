<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init_public.php';
require_once __DIR__ . '/includes/pro_accounts.php';

$pdo = require __DIR__ . '/config/db.php';
tiramii_ensure_pro_account_tables($pdo);

if (tiramii_pro_current_account($pdo) !== null) {
    header('Location: index.php', true, 302);
    exit;
}

$csrf = csrf_token();
$next = isset($_GET['next']) ? (string) $_GET['next'] : '';
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= h($csrf) ?>">
<title>Connexion pro — <?= h(brand_name()) ?></title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--ink:#111;--line:#ddd;--muted:#666}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:#fafafa;color:var(--ink);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem}
.card{max-width:400px;width:100%;background:#fff;border:1px solid var(--line);border-radius:14px;padding:1.75rem}
h1{font-size:1.35rem;margin-bottom:.25rem}
p{color:var(--muted);font-size:.9rem;margin-bottom:1.25rem}
label{display:block;font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:.35rem}
input{width:100%;padding:.65rem .85rem;border:1px solid var(--line);border-radius:8px;font:inherit;margin-bottom:1rem}
button{width:100%;background:var(--ink);color:#fff;border:none;border-radius:999px;padding:.85rem;font:inherit;font-weight:600;cursor:pointer}
button:disabled{opacity:.55}
.msg{margin-top:1rem;font-size:.88rem;display:none}
.msg.err{color:#b91c1c}
.msg.ok{color:#166534}
.links{margin-top:1.25rem;font-size:.85rem;color:var(--muted)}
.links a{color:var(--ink)}
</style>
</head>
<body>
<div class="card">
  <h1>Connexion pro</h1>
  <p>Compte validé par <?= h(brand_name()) ?> uniquement.</p>
  <form id="f">
    <label for="email">E-mail</label>
    <input id="email" name="email" type="email" required autocomplete="email">
    <label for="password">Mot de passe</label>
    <input id="password" name="password" type="password" required autocomplete="current-password">
    <button type="submit" id="btn">Se connecter</button>
  </form>
  <div class="msg err" id="err"></div>
  <div class="msg ok" id="ok"></div>
  <div class="links">
    <a href="pro-register.php">Créer un compte pro</a> · <a href="pro.php">Espace pro</a> · <a href="index.php">Boutique publique</a>
  </div>
</div>
<script>
(function(){
  var next = <?= json_encode($next === 'boutique' ? 'boutique' : '') ?>;
  document.getElementById('f').addEventListener('submit', function(e) {
    e.preventDefault();
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var err = document.getElementById('err');
    var ok = document.getElementById('ok');
    var btn = document.getElementById('btn');
    err.style.display = 'none'; ok.style.display = 'none';
    btn.disabled = true;
    fetch('api/pro_login.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
      body: JSON.stringify({
        email: document.getElementById('email').value.trim(),
        password: document.getElementById('password').value
      })
    }).then(function(r) { return r.json().then(function(j) { return { r: r, j: j }; }); })
      .then(function(x) {
        btn.disabled = false;
        if (x.j && x.j.ok) {
          window.location.href = 'index.php';
        } else {
          err.textContent = (x.j && x.j.error) ? x.j.error : 'Erreur';
          err.style.display = 'block';
        }
      })
      .catch(function() {
        btn.disabled = false;
        err.textContent = 'Réseau indisponible';
        err.style.display = 'block';
      });
  });
})();
</script>
</body>
</html>
