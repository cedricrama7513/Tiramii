<?php
/**
 * Espace professionnels : grille tarifs pro + demande de devis.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init_public.php';

try {
    $pdo = require __DIR__ . '/config/db.php';
} catch (Throwable) {
    http_response_code(500);
    echo 'Base de données non configurée.';
    exit;
}

require_once __DIR__ . '/includes/pro_b2b.php';
require_once __DIR__ . '/includes/ensure_pro_prices.php';
tiramii_ensure_pro_tables($pdo);
tiramii_ensure_pro_price_column($pdo);

try {
    $products = $pdo
        ->query(
            'SELECT id, name, price_eur, pro_price_eur, sort_order
             FROM products WHERE is_active = 1
             ORDER BY sort_order ASC, id ASC'
        )
        ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable) {
    $products = [];
}

$catalog = [];
foreach ($products as $p) {
    $proRaw = $p['pro_price_eur'] ?? null;
    $pro = ($proRaw !== null && $proRaw !== '') ? round((float) $proRaw, 2) : null;
    $catalog[] = [
        'id' => (string) $p['id'],
        'name' => (string) $p['name'],
        'price_public' => round((float) $p['price_eur'], 2),
        'price_pro' => $pro,
    ];
}

$csrf = csrf_token();
$pageTitle = 'Espace pro — Casa Dessert';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= h($csrf) ?>">
  <title><?= h($pageTitle) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Playfair+Display:wght@600&display=swap" rel="stylesheet">
  <style>
    :root { --vk:#1a1a1a; --vd:#A68966; --v:#d4c4b0; --bg:#F9F9F7; --line:rgba(166,137,102,.28); }
    * { box-sizing: border-box; margin:0; padding:0; }
    body { font-family:'Montserrat',sans-serif; background:var(--bg); color:var(--vk); line-height:1.6; }
    header.top {
      position:sticky; top:0; z-index:10;
      background:rgba(249,249,247,.96); backdrop-filter:blur(12px);
      border-bottom:1px solid var(--line);
      padding:.85rem 1.25rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.75rem;
    }
    header.top a.home { font-weight:600; color:var(--vk); text-decoration:none; letter-spacing:.06em; text-transform:uppercase; font-size:.85rem; }
    header.top a.home:hover { color:var(--vd); }
    .wrap { max-width:920px; margin:0 auto; padding:2rem 1.25rem 4rem; }
    h1 { font-family:'Playfair Display',serif; font-size:clamp(1.6rem,4vw,2.15rem); margin-bottom:.5rem; }
    .lead { color:#333; font-size:.95rem; max-width:44rem; margin-bottom:1.75rem; }
    .notice {
      background:#fff; border:1px solid var(--line); border-radius:14px; padding:1rem 1.15rem; margin-bottom:1.75rem;
      font-size:.88rem; color:#333;
    }
    .notice strong { color:var(--vk); }
    table.tarifs { width:100%; border-collapse:collapse; font-size:.88rem; background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(26,26,26,.06); }
    table.tarifs th, table.tarifs td { padding:12px 14px; text-align:left; border-bottom:1px solid #e8e4dc; }
    table.tarifs th { background:#f5f3ef; font-weight:600; font-size:.76rem; text-transform:uppercase; letter-spacing:.08em; color:#5c574f; }
    table.tarifs tr:last-child td { border-bottom:none; }
    table.tarifs .pub { color:#888; text-decoration:line-through; font-size:.82rem; }
    table.tarifs .num { text-align:right; white-space:nowrap; }
    .devis { margin-top:2.5rem; background:#fff; border-radius:20px; padding:1.5rem 1.25rem 1.75rem; border:1px solid var(--line); box-shadow:0 8px 32px rgba(26,26,26,.06); }
    .devis h2 { font-family:'Playfair Display',serif; font-size:1.35rem; margin-bottom:1rem; }
    .devis-grid { display:grid; gap:12px; margin-bottom:14px; }
    @media(min-width:560px){ .devis-grid.cols-2 { grid-template-columns:1fr 1fr; } }
    label { display:block; font-size:.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:var(--vd); margin-bottom:4px; }
    input, textarea, select {
      width:100%; padding:10px 12px; border:1.5px solid #d8d0c4; border-radius:10px; font-family:inherit; font-size:.92rem;
    }
    textarea { min-height:88px; resize:vertical; }
    .lines-wrap { margin:1rem 0; }
    .line-row { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-bottom:8px; }
    .line-row select { flex:1; min-width:200px; }
    .line-row input[type="number"] { width:88px; }
    .btn-outline { background:transparent; border:2px solid var(--vd); color:var(--vk); padding:8px 14px; border-radius:999px; font-weight:600; cursor:pointer; font-size:.82rem; }
    .btn-outline:hover { background:var(--vd); color:#fff; }
    .btn-primary { background:var(--vk); color:#f9f9f7; border:none; padding:14px 22px; border-radius:999px; font-weight:600; cursor:pointer; font-size:.95rem; width:100%; margin-top:8px; }
    .btn-primary:hover { background:var(--vd); }
    .btn-primary:disabled { opacity:.55; cursor:not-allowed; }
    .quote-lines { font-size:.86rem; background:#f5f3ef; border-radius:12px; padding:12px; margin-top:10px; }
    .quote-lines ul { margin:8px 0 0 1.1rem; }
    .msg { margin-top:12px; padding:12px; border-radius:12px; font-size:.88rem; display:none; }
    .msg.ok { display:block; background:#e8f5e9; color:#1b5e20; }
    .msg.err { display:block; background:#ffebee; color:#b71c1c; }
    .hint { font-size:.8rem; color:#5c5650; margin-top:6px; }
  </style>
</head>
<body>
  <header class="top">
    <a class="home" href="index.php">← Boutique</a>
    <span style="font-size:.78rem;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--vd)">Espace pro</span>
  </header>

  <main class="wrap">
    <h1>Commandes &amp; devis professionnels</h1>
    <p class="lead">
      Retrouvez les <strong>tarifs unitaires pro (HT)</strong> de la gamme. Composez votre demande ci-dessous : nous la recevons par e-mail avec une estimation automatique
      (les articles « sur devis » sont listés séparément). Les prix publics sont indiqués à titre de comparaison uniquement.
    </p>

    <div class="notice">
      <strong>Tarifs pro en base de données :</strong> colonne <code>pro_price_eur</code> sur chaque produit (phpMyAdmin ou outil SQL).
      Laissez vide ou <code>NULL</code> pour afficher « Sur devis » pour une référence.
    </div>

    <?php if ($catalog === []): ?>
      <p>Catalogue indisponible.</p>
    <?php else: ?>
      <table class="tarifs">
        <thead>
          <tr>
            <th>Produit</th>
            <th class="num">Public TTC</th>
            <th class="num">Pro HT</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($catalog as $c): ?>
          <tr>
            <td><?= h($c['name']) ?><span style="color:#888;font-size:.8rem"> · <?= h($c['id']) ?></span></td>
            <td class="num"><span class="pub"><?= h(number_format($c['price_public'], 2, ',', ' ')) ?> €</span></td>
            <td class="num"><?= $c['price_pro'] !== null ? h(number_format($c['price_pro'], 2, ',', ' ')) . ' €' : '<em>Sur devis</em>' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <section class="devis">
      <h2>Votre demande de devis</h2>
      <p class="hint">Ajoutez des lignes (produit + quantité), renseignez vos coordonnées, puis envoyez.</p>

      <div class="lines-wrap">
        <div id="lineRows"></div>
        <button type="button" class="btn-outline" id="addLine">+ Ajouter une ligne</button>
      </div>

      <div class="quote-lines" id="quoteSummary" style="display:none">
        <strong>Récapitulatif (aperçu)</strong>
        <ul id="quoteList"></ul>
        <p id="quoteTotal" style="margin-top:8px;font-weight:600"></p>
      </div>

      <form id="quoteForm" class="devis-grid" style="margin-top:1.25rem">
        <div class="devis-grid cols-2">
          <div>
            <label for="company_name">Établissement</label>
            <input type="text" id="company_name" name="company_name" required maxlength="255" autocomplete="organization" placeholder="Restaurant, salon de thé…">
          </div>
          <div>
            <label for="contact_name">Contact</label>
            <input type="text" id="contact_name" name="contact_name" required maxlength="160" autocomplete="name" placeholder="Prénom Nom">
          </div>
        </div>
        <div class="devis-grid cols-2">
          <div>
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" required maxlength="180" autocomplete="email">
          </div>
          <div>
            <label for="phone">Téléphone</label>
            <input type="tel" id="phone" name="phone" required maxlength="22" autocomplete="tel" placeholder="06…">
          </div>
        </div>
        <div>
          <label for="message">Message (optionnel)</label>
          <textarea id="message" name="message" maxlength="4000" placeholder="Délai souhaité, adresse de livraison, références internes…"></textarea>
        </div>
        <button type="submit" class="btn-primary" id="submitBtn">Envoyer la demande</button>
      </form>
      <div class="msg" id="formMsg" role="status"></div>
    </section>
  </main>

  <script>
  const CATALOG = <?= json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  function addLineRow() {
    const wrap = document.getElementById('lineRows');
    const row = document.createElement('div');
    row.className = 'line-row';
    const sel = document.createElement('select');
    sel.required = true;
    const opt0 = document.createElement('option');
    opt0.value = '';
    opt0.textContent = '— Produit —';
    sel.appendChild(opt0);
    CATALOG.forEach(p => {
      const o = document.createElement('option');
      o.value = p.id;
      o.textContent = p.name;
      sel.appendChild(o);
    });
    const qty = document.createElement('input');
    qty.type = 'number';
    qty.min = 1;
    qty.max = 9999;
    qty.value = 1;
    qty.required = true;
    const rm = document.createElement('button');
    rm.type = 'button';
    rm.className = 'btn-outline';
    rm.style.padding = '6px 12px';
    rm.textContent = 'Retirer';
    rm.onclick = () => { row.remove(); updateSummary(); };
    row.appendChild(sel);
    row.appendChild(qty);
    row.appendChild(rm);
    wrap.appendChild(row);
    sel.addEventListener('change', updateSummary);
    qty.addEventListener('input', updateSummary);
  }

  function updateSummary() {
    const rows = document.querySelectorAll('#lineRows .line-row');
    const list = document.getElementById('quoteList');
    const sumEl = document.getElementById('quoteSummary');
    const totalEl = document.getElementById('quoteTotal');
    let html = '';
    let total = 0;
    let surDevis = [];
    const seen = new Set();
    rows.forEach(r => {
      const sel = r.querySelector('select');
      const q = parseInt(r.querySelector('input[type=number]').value, 10) || 0;
      const id = sel.value;
      if (!id || q < 1) return;
      if (seen.has(id)) return;
      seen.add(id);
      const p = CATALOG.find(x => x.id === id);
      if (!p) return;
      if (p.price_pro != null && p.price_pro > 0) {
        const line = p.price_pro * q;
        total += line;
        html += '<li>'+p.name.replace(/</g,'')+' × '+q+' → '+line.toFixed(2).replace('.', ',')+' € HT</li>';
      } else {
        surDevis.push(p.name.replace(/</g,'')+' × '+q);
      }
    });
    if (html === '' && surDevis.length === 0) {
      sumEl.style.display = 'none';
      return;
    }
    sumEl.style.display = 'block';
    list.innerHTML = html + (surDevis.length ? '<li><em>Sur devis : '+surDevis.join(', ')+'</em></li>' : '');
    totalEl.textContent = total > 0
      ? 'Estimation HT (hors lignes sur devis) : ' + total.toFixed(2).replace('.', ',') + ' €'
      : (surDevis.length ? 'Montant sur devis — nous vous recontactons.' : '');
  }

  document.getElementById('addLine').addEventListener('click', () => { addLineRow(); updateSummary(); });

  document.getElementById('quoteForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const msg = document.getElementById('formMsg');
    const btn = document.getElementById('submitBtn');
    msg.className = 'msg';
    msg.textContent = '';
    const lines = [];
    const merged = new Map();
    document.querySelectorAll('#lineRows .line-row').forEach(r => {
      const id = r.querySelector('select').value;
      const qty = parseInt(r.querySelector('input[type=number]').value, 10) || 0;
      if (!id || qty < 1) return;
      merged.set(id, (merged.get(id) || 0) + qty);
    });
    merged.forEach((qty, id) => lines.push({ product_id: id, qty }));
    if (lines.length === 0) {
      msg.className = 'msg err';
      msg.textContent = 'Ajoutez au moins une ligne avec un produit.';
      return;
    }
    btn.disabled = true;
    try {
      const res = await fetch('api/pro-quote.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({
          company_name: document.getElementById('company_name').value.trim(),
          contact_name: document.getElementById('contact_name').value.trim(),
          email: document.getElementById('email').value.trim(),
          phone: document.getElementById('phone').value.trim(),
          message: document.getElementById('message').value.trim(),
          lines
        })
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Erreur');
      msg.className = 'msg ok';
      msg.textContent = 'Demande #' + data.quote_id + ' envoyée. Nous vous recontactons rapidement.';
      document.getElementById('quoteForm').reset();
      document.getElementById('lineRows').innerHTML = '';
      addLineRow();
      updateSummary();
    } catch (err) {
      msg.className = 'msg err';
      msg.textContent = err.message || 'Envoi impossible.';
    }
    btn.disabled = false;
  });

  if (CATALOG.length) addLineRow();
  </script>
</body>
</html>
