<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init_public.php';
require_once __DIR__ . '/includes/pro_accounts.php';

$pdo = require __DIR__ . '/config/db.php';
tiramii_ensure_pro_account_tables($pdo);

if (tiramii_pro_current_account($pdo) !== null) {
    header('Location: pro-boutique.php', true, 302);
    exit;
}

$csrf = csrf_token();
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= h($csrf) ?>">
<title>Inscription pro — <?= h(brand_name()) ?></title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--ink:#111;--line:#ddd;--muted:#666}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:#fafafa;color:var(--ink);padding:2rem 1rem}
.wrap{max-width:480px;margin:0 auto;background:#fff;border:1px solid var(--line);border-radius:14px;padding:1.75rem}
h1{font-size:1.35rem;margin-bottom:.25rem}
.sub{color:var(--muted);font-size:.88rem;margin-bottom:1.25rem;line-height:1.5}
label{display:block;font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:.35rem}
input{width:100%;padding:.65rem .85rem;border:1px solid var(--line);border-radius:8px;font:inherit;margin-bottom:1rem}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
@media(max-width:500px){.row2{grid-template-columns:1fr}}
button{width:100%;background:var(--ink);color:#fff;border:none;border-radius:999px;padding:.85rem;font:inherit;font-weight:600;cursor:pointer;margin-top:.25rem}
button:disabled{opacity:.55}
.hp{position:absolute;left:-9999px;opacity:0;height:0;width:0}
.msg{margin-top:1rem;font-size:.88rem;display:none;padding:.75rem;border-radius:8px}
.msg.err{display:block;background:#fef2f2;color:#991b1b}
.msg.ok{display:block;background:#f0fdf4;color:#166534}
.links{margin-top:1.25rem;font-size:.85rem;color:var(--muted)}
.links a{color:var(--ink)}
</style>
</head>
<body>
<div class="wrap">
  <h1>Créer un compte pro</h1>
  <p class="sub">Après inscription, votre compte est <strong>en attente</strong> jusqu’à validation par <?= h(brand_name()) ?>. Vous recevrez un e-mail si nécessaire.</p>
  <form id="reg">
    <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
    <label for="restaurant_name">Nom de l’établissement *</label>
    <input id="restaurant_name" required maxlength="255" autocomplete="organization">
    <div class="row2">
      <div>
        <label for="first_name">Prénom *</label>
        <input id="first_name" required maxlength="80" autocomplete="given-name">
      </div>
      <div>
        <label for="last_name">Nom</label>
        <input id="last_name" maxlength="80" autocomplete="family-name">
      </div>
    </div>
    <label for="email">E-mail *</label>
    <input id="email" type="email" required autocomplete="email">
    <label for="password">Mot de passe * (8 caractères min.)</label>
    <input id="password" type="password" required minlength="8" autocomplete="new-password">
    <label for="phone">Téléphone *</label>
    <input id="phone" required autocomplete="tel">
    <label for="address">Adresse *</label>
    <input id="address" required maxlength="255" autocomplete="street-address">
    <div class="row2">
      <div>
        <label for="zip">Code postal *</label>
        <input id="zip" required maxlength="12" autocomplete="postal-code">
      </div>
      <div>
        <label for="city">Ville *</label>
        <input id="city" required maxlength="80" autocomplete="address-level2">
      </div>
    </div>
    <button type="submit" id="btn">Envoyer la demande</button>
  </form>
  <div class="msg err" id="err"></div>
  <div class="msg ok" id="ok"></div>
  <div class="links">
    <a href="pro-login.php">Déjà un compte ? Connexion</a> · <a href="pro.php">Espace pro</a>
  </div>
</div>
<script>
(function(){
  document.getElementById('reg').addEventListener('submit', function(e) {
    e.preventDefault();
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var err = document.getElementById('err');
    var ok = document.getElementById('ok');
    var btn = document.getElementById('btn');
    err.style.display = 'none'; ok.style.display = 'none';
    btn.disabled = true;
    fetch('api/pro_register.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
      body: JSON.stringify({
        restaurant_name: document.getElementById('restaurant_name').value.trim(),
        first_name: document.getElementById('first_name').value.trim(),
        last_name: document.getElementById('last_name').value.trim(),
        email: document.getElementById('email').value.trim(),
        password: document.getElementById('password').value,
        phone: document.getElementById('phone').value.trim(),
        address: document.getElementById('address').value.trim(),
        zip: document.getElementById('zip').value.trim(),
        city: document.getElementById('city').value.trim(),
        website: document.querySelector('[name="website"]').value
      })
    }).then(function(r) { return r.json().then(function(j) { return { r: r, j: j }; }); })
      .then(function(x) {
        btn.disabled = false;
        if (x.j && x.j.ok) {
          ok.textContent = x.j.message || 'Demande enregistrée.';
          ok.style.display = 'block';
          document.getElementById('reg').reset();
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
