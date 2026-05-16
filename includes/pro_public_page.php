<?php
/**
 * Espace professionnels (tarifs pro + demande de devis).
 * Servi par pro.php, index.php?page=devis ou /devis (.htaccess).
 */
declare(strict_types=1);

function tiramii_serve_pro_public_page(): void
{
    require_once __DIR__ . '/init_public.php';
    require_once __DIR__ . '/nav_render.php';

    try {
        $pdo = require dirname(__DIR__) . '/config/db.php';
    } catch (Throwable) {
        http_response_code(500);
        echo 'Base de données non configurée.';
        exit;
    }

    require_once __DIR__ . '/pro_b2b.php';
    require_once __DIR__ . '/ensure_pro_prices.php';
    require_once __DIR__ . '/ensure_box_supreme.php';
    require_once __DIR__ . '/ensure_new_flavors.php';
    require_once __DIR__ . '/ensure_stock_levels.php';

    tiramii_ensure_pro_tables($pdo);
    tiramii_ensure_pro_price_column($pdo);
    tiramii_ensure_new_flavors($pdo);
    tiramii_ensure_box_supreme($pdo);
    tiramii_ensure_stock_levels_for_all_products($pdo);

    try {
        $proRows = $pdo
            ->query(
                'SELECT id, name, price_eur, pro_price_eur, sort_order
                 FROM products WHERE is_active = 1
                 ORDER BY sort_order ASC, id ASC'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        $proRows = [];
    }

    $catalog = [];
    foreach ($proRows as $p) {
        $proRaw = $p['pro_price_eur'] ?? null;
        $pro = ($proRaw !== null && $proRaw !== '') ? round((float) $proRaw, 2) : null;
        $catalog[] = [
            'id' => (string) $p['id'],
            'name' => (string) $p['name'],
            'price_public' => round((float) $p['price_eur'], 2),
            'price_pro' => $pro,
        ];
    }

    $excludeFromCatalog = ['box1', 'box2', 'box_supreme'];
    $notIn = implode(',', array_fill(0, count($excludeFromCatalog), '?'));
    $stmtGrid = $pdo->prepare(
        "SELECT id, name, price_eur, description, badge_class, badge_text, img_key, sort_order
         FROM products WHERE is_active = 1 AND id NOT IN ($notIn) ORDER BY sort_order ASC, id ASC"
    );
    $stmtGrid->execute($excludeFromCatalog);
    $gridProducts = $stmtGrid->fetchAll(PDO::FETCH_ASSOC);

    $productsJson = [];
    foreach ($gridProducts as $p) {
        $productsJson[] = [
            'id' => $p['id'],
            'name' => $p['name'],
            'price' => (float) $p['price_eur'],
            'desc' => $p['description'],
            'badge' => $p['badge_class'],
            'badgeText' => $p['badge_text'],
            'img' => $p['img_key'],
        ];
    }

    $csrf = csrf_token();
    $pageTitle = 'Espace pro — Casa Dessert';
    $appBoot = [
        'csrf' => $csrf,
        'products' => $productsJson,
    ];
    $root = dirname(__DIR__);
    $shopJsPath = $root . '/assets/js/shop.js';
    $shopJsV = is_readable($shopJsPath) ? (string) filemtime($shopJsPath) : (string) time();
    $appScript = '<script type="module" src="assets/js/shop.js?v=' . h($shopJsV) . '"></script>' . "\n";
    $appScript .= '<script>window.__CASA_DESSERT__ = ' . json_encode($appBoot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ";</script>\n";

    $tplPath = $root . '/templates/index_base.html';
    if (!is_readable($tplPath)) {
        http_response_code(500);
        echo 'Template manquant.';
        exit;
    }

    $proNavAccount = null;
    try {
        require_once __DIR__ . '/pro_accounts.php';
        $proNavAccount = tiramii_pro_current_account($pdo);
    } catch (Throwable) {
        $proNavAccount = null;
    }
    $navHtml = tiramii_render_nav_html($proNavAccount);
    if (str_contains($navHtml, '__LOGO_MARK__')) {
        $navHtml = str_replace('__LOGO_MARK__', brand_logo_markup('nav'), $navHtml);
    }
    $footerMainHtml = is_readable($root . '/includes/footer_main_fragment.html')
        ? (string) file_get_contents($root . '/includes/footer_main_fragment.html')
        : '';

    ob_start();
    ?>
<style>
.pro-b2b-page { padding:5.5rem 1.25rem 4rem; max-width:920px; margin:0 auto; }
.pro-b2b-page h1 { font-family:'Playfair Display',serif; font-size:clamp(1.6rem,4vw,2.15rem); margin-bottom:.5rem; }
.pro-b2b-page .lead { color:#333; font-size:.95rem; max-width:44rem; margin-bottom:1.75rem; }
.pro-b2b-page .notice {
  background:#fff; border:1px solid rgba(166,137,102,.28); border-radius:14px; padding:1rem 1.15rem; margin-bottom:1.75rem;
  font-size:.88rem; color:#333;
}
.pro-b2b-page .notice strong { color:var(--vk); }
.pro-b2b-page table.tarifs { width:100%; border-collapse:collapse; font-size:.88rem; background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(26,26,26,.06); }
.pro-b2b-page table.tarifs th, .pro-b2b-page table.tarifs td { padding:12px 14px; text-align:left; border-bottom:1px solid #e8e4dc; }
.pro-b2b-page table.tarifs th { background:#f5f3ef; font-weight:600; font-size:.76rem; text-transform:uppercase; letter-spacing:.08em; color:#5c574f; }
.pro-b2b-page table.tarifs tr:last-child td { border-bottom:none; }
.pro-b2b-page table.tarifs .pub { color:#888; text-decoration:line-through; font-size:.82rem; }
.pro-b2b-page table.tarifs .num { text-align:right; white-space:nowrap; }
.pro-b2b-page .devis h2 { font-family:'Playfair Display',serif; font-size:1.35rem; margin-bottom:1rem; }
.pro-b2b-page .devis-grid { display:grid; gap:12px; margin-bottom:14px; }
@media(min-width:560px){ .pro-b2b-page .devis-grid.cols-2 { grid-template-columns:1fr 1fr; } }
.pro-b2b-page .devis label { display:block; font-size:.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:var(--vd); margin-bottom:4px; }
.pro-b2b-page .devis input, .pro-b2b-page .devis textarea, .pro-b2b-page .devis select {
  width:100%; padding:10px 12px; border:1.5px solid #d8d0c4; border-radius:10px; font-family:inherit; font-size:.92rem;
}
.pro-b2b-page .devis textarea { min-height:88px; resize:vertical; }
.pro-b2b-page .lines-wrap { margin:1rem 0; }
.pro-b2b-page .line-row { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-bottom:8px; }
.pro-b2b-page .line-row select { flex:1; min-width:200px; }
.pro-b2b-page .line-row input[type="number"] { width:88px; }
.pro-b2b-page .btn-outline { background:transparent; border:2px solid var(--vd); color:var(--vk); padding:8px 14px; border-radius:999px; font-weight:600; cursor:pointer; font-size:.82rem; }
.pro-b2b-page .btn-outline:hover { background:var(--vd); color:#fff; }
.pro-b2b-page .btn-primary { background:var(--vk); color:#f9f9f7; border:none; padding:14px 22px; border-radius:999px; font-weight:600; cursor:pointer; font-size:.95rem; width:100%; margin-top:8px; }
.pro-b2b-page .btn-primary:hover { background:var(--vd); }
.pro-b2b-page .btn-primary:disabled { opacity:.55; cursor:not-allowed; }
.pro-b2b-page .quote-lines { font-size:.86rem; background:#f5f3ef; border-radius:12px; padding:12px; margin-top:10px; }
.pro-b2b-page .quote-lines ul { margin:8px 0 0 1.1rem; }
.pro-b2b-page .msg { margin-top:12px; padding:12px; border-radius:12px; font-size:.88rem; display:none; }
.pro-b2b-page .msg.ok { display:block; background:#e8f5e9; color:#1b5e20; }
.pro-b2b-page .msg.err { display:block; background:#ffebee; color:#b71c1c; }
.pro-b2b-page .hint { font-size:.8rem; color:#5c5650; margin-top:6px; }
.pro-b2b-page .pro-tabs { margin-top:2rem; }
.pro-b2b-page .pro-tablist {
  display:flex; flex-wrap:wrap; gap:8px; border-bottom:2px solid rgba(166,137,102,.28); padding-bottom:0; margin-bottom:0;
}
.pro-b2b-page .pro-tab {
  appearance:none; border:none; background:transparent;
  font-family:inherit; font-size:.88rem; font-weight:600; letter-spacing:.06em; text-transform:uppercase;
  color:#5c5650; padding:12px 18px; cursor:pointer; border-radius:12px 12px 0 0;
  border:1px solid transparent; border-bottom:none; margin-bottom:-2px;
}
.pro-b2b-page .pro-tab:hover { color:var(--vk); background:rgba(166,137,102,.08); }
.pro-b2b-page .pro-tab[aria-selected="true"] {
  color:var(--vk); background:#fff; border-color:rgba(166,137,102,.28); border-bottom-color:#fff;
}
.pro-b2b-page .pro-panel {
  display:none; background:#fff; border:1px solid rgba(166,137,102,.28); border-top:none;
  border-radius:0 0 20px 20px; padding:1.5rem 1.25rem 1.75rem; box-shadow:0 8px 32px rgba(26,26,26,.06);
}
.pro-b2b-page .pro-panel.is-active { display:block; }
.pro-b2b-page .pro-panel--order .order-card p { margin-bottom:1rem; color:#333; font-size:.95rem; }
.pro-b2b-page .btn-boutique {
  display:inline-flex; align-items:center; justify-content:center; gap:8px;
  background:var(--vk); color:#f9f9f7; text-decoration:none; font-weight:600;
  padding:14px 26px; border-radius:999px; font-size:.95rem; transition:background .2s;
}
.pro-b2b-page .btn-boutique:hover { background:var(--vd); color:#fff; }
.pro-b2b-page .devis { margin-top:0; background:transparent; border-radius:0; padding:0; border:none; box-shadow:none; }
</style>
<section class="pro-b2b-page">
  <h1>Commandes &amp; devis professionnels</h1>
  <p class="lead">
    Retrouvez les <strong>tarifs unitaires pro (HT)</strong> dans le tableau. Ensuite, choisissez un onglet :
    <strong>Commander en ligne</strong> pour la boutique (panier, livraison) ou <strong>Demande de devis</strong> pour un volume pro — nous recevons la demande par e-mail avec une estimation automatique
    (les articles « sur devis » sont listés séparément). Les prix publics sont indiqués à titre de comparaison uniquement.
  </p>

  <div class="notice">
    <strong>Compte &amp; boutique pro :</strong>
    <a href="pro-register.php">Créer un compte pro</a> ·
    <a href="pro-login.php">Connexion</a> ·
    <a href="pro-boutique.php">Boutique tarifs partenaires</a>
    (validation manuelle dans l’admin avant accès).
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

  <div class="pro-tabs">
    <div class="pro-tablist" role="tablist" aria-label="Espace pro">
      <button type="button" class="pro-tab" role="tab" id="tab-order" aria-controls="panel-order" aria-selected="true">Commander en ligne</button>
      <button type="button" class="pro-tab" role="tab" id="tab-devis" aria-controls="panel-devis" aria-selected="false" tabindex="-1">Demande de devis</button>
    </div>

    <div class="pro-panel pro-panel--order is-active" role="tabpanel" id="panel-order" aria-labelledby="tab-order">
      <div class="order-card">
        <h2 style="font-family:'Playfair Display',serif;font-size:1.35rem;margin-bottom:.75rem">Passer commande</h2>
        <p>
          Les commandes avec <strong>panier</strong>, <strong>livraison</strong> (Paris 75 et 91–94) et choix du créneau se font sur la <strong>boutique en ligne</strong>.
          Ajoutez vos barquettes ou box au panier puis finalisez votre commande comme un client classique.
        </p>
        <a class="btn-boutique" href="index.php#catalogue">Ouvrir la boutique et commander →</a>
      </div>
    </div>

    <div class="pro-panel" role="tabpanel" id="panel-devis" aria-labelledby="tab-devis" hidden>
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
              <label for="pro_quote_company">Établissement</label>
              <input type="text" id="pro_quote_company" name="company_name" required maxlength="255" autocomplete="organization" placeholder="Restaurant, salon de thé…">
            </div>
            <div>
              <label for="pro_quote_contact">Contact</label>
              <input type="text" id="pro_quote_contact" name="contact_name" required maxlength="160" autocomplete="name" placeholder="Prénom Nom">
            </div>
          </div>
          <div class="devis-grid cols-2">
            <div>
              <label for="pro_quote_email">E-mail</label>
              <input type="email" id="pro_quote_email" name="email" required maxlength="180" autocomplete="email">
            </div>
            <div>
              <label for="pro_quote_phone">Téléphone</label>
              <input type="tel" id="pro_quote_phone" name="phone" required maxlength="22" autocomplete="tel" placeholder="06…">
            </div>
          </div>
          <div>
            <label for="pro_quote_message">Message (optionnel)</label>
            <textarea id="pro_quote_message" name="message" maxlength="4000" placeholder="Délai souhaité, adresse de livraison, références internes…"></textarea>
          </div>
          <button type="submit" class="btn-primary" id="submitBtn">Envoyer la demande</button>
        </form>
        <div class="msg" id="formMsg" role="status"></div>
      </section>
    </div>
  </div>
</section>

<script>
(function () {
  const tabs = Array.from(document.querySelectorAll('.pro-b2b-page .pro-tab'));
  const panels = {
    'tab-order': document.getElementById('panel-order'),
    'tab-devis': document.getElementById('panel-devis'),
  };
  function activate(tabId) {
    tabs.forEach((t) => {
      const on = t.id === tabId;
      t.setAttribute('aria-selected', on ? 'true' : 'false');
      t.tabIndex = on ? 0 : -1;
      const p = panels[t.id];
      if (p) {
        p.classList.toggle('is-active', on);
        p.hidden = !on;
      }
    });
  }
  tabs.forEach((t) => {
    t.addEventListener('click', () => activate(t.id));
  });
  const params = new URLSearchParams(location.search);
  const hash = (location.hash || '').replace(/^#/, '');
  if (params.get('tab') === 'devis' || params.get('page') === 'devis' || hash === 'devis') {
    activate('tab-devis');
  } else {
    activate('tab-order');
  }
})();

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
        company_name: document.getElementById('pro_quote_company').value.trim(),
        contact_name: document.getElementById('pro_quote_contact').value.trim(),
        email: document.getElementById('pro_quote_email').value.trim(),
        phone: document.getElementById('pro_quote_phone').value.trim(),
        message: document.getElementById('pro_quote_message').value.trim(),
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
    <?php
    $proMainHtml = ob_get_clean();

    $html = (string) file_get_contents($tplPath);
    $html = str_replace('__HOME_MAIN__', $proMainHtml, $html);
    $html = str_replace(
        ['__PAGE_TITLE__', '__CSRF__', '__NAV__', '__FOOTER_MAIN__', '__APP_SCRIPT__'],
        [h($pageTitle), h($csrf), $navHtml, $footerMainHtml, $appScript],
        $html
    );

    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
}
