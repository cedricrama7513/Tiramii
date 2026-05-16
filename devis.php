<?php
/**
 * Devis pro — fichier autonome pour Hostinger (un seul upload si Git ne déploie pas).
 * https://casadessert.fr/devis.php
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init_public.php';

try {
    $pdo = require __DIR__ . '/config/db.php';
} catch (Throwable) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Base de données non configurée.';
    exit;
}

/** @return void */
function tiramii_devis_ensure_schema(PDO $pdo): void
{
    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS pro_quote_requests (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                company_name VARCHAR(255) NOT NULL,
                contact_name VARCHAR(160) NOT NULL,
                email VARCHAR(180) NOT NULL,
                phone VARCHAR(40) NOT NULL,
                message TEXT,
                lines_json TEXT NOT NULL,
                estimated_total_eur DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                has_sur_devis TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_pro_quote_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    } catch (Throwable) {
        /* droits CREATE refusés */
    }
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM products LIKE 'pro_price_eur'");
        if ($chk && $chk->fetch() === false) {
            $pdo->exec(
                "ALTER TABLE products ADD COLUMN pro_price_eur DECIMAL(6,2) NULL DEFAULT NULL COMMENT 'Prix pro HT' AFTER price_eur"
            );
        }
    } catch (Throwable) {
        /* colonne peut exister */
    }
}

/**
 * @param array<string, mixed> $cfg
 */
function tiramii_devis_notify_owner(
    array $cfg,
    int $requestId,
    string $company,
    string $contact,
    string $email,
    string $phone,
    string $message,
    string $linesText,
    float $estimatedTotal,
    bool $hasSurDevis
): void {
    $n = $cfg['notify'] ?? null;
    if (!is_array($n)) {
        return;
    }
    $ownerEmail = trim((string) ($n['owner_email'] ?? ''));
    if ($ownerEmail === '' || !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $subject = "Demande de devis pro #{$requestId} — {$company}";
    $totalStr = number_format($estimatedTotal, 2, ',', ' ') . ' € HT';
    if ($hasSurDevis) {
        $totalStr .= ' (estimation partielle — lignes « sur devis » en sus)';
    }
    $body = 'Nouvelle demande de devis — ' . brand_name() . "\n\n";
    $body .= "Réf. #{$requestId}\nÉtablissement : {$company}\nContact : {$contact}\n";
    $body .= "E-mail : {$email}\nTéléphone : {$phone}\n\n";
    $body .= "Estimation : {$totalStr}\n\nDétail :\n{$linesText}\n\n";
    $body .= 'Message : ' . ($message !== '' ? $message : '—') . "\n";
    $fromEmail = trim((string) ($n['from_email'] ?? ''));
    $fromName = trim((string) ($n['from_name'] ?? brand_name()));
    $headers = ['MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8'];
    if ($fromEmail !== '' && filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'From: ' . ($fromName !== '' ? "{$fromName} <{$fromEmail}>" : $fromEmail);
    }
    $subjHdr = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    @mail($ownerEmail, $subjHdr, $body, implode("\r\n", $headers));
}

tiramii_devis_ensure_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    if (!csrf_verify(csrf_token_from_request())) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Jeton CSRF invalide. Rechargez la page.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: 'null', true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'JSON invalide'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $company = trim((string) ($data['company_name'] ?? ''));
    $contact = trim((string) ($data['contact_name'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $phone = trim((string) ($data['phone'] ?? ''));
    $message = trim((string) ($data['message'] ?? ''));
    $linesIn = $data['lines'] ?? null;
    if ($company === '' || $contact === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Coordonnées incomplètes ou e-mail invalide.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($phone === '' || !preg_match('/^[0-9\s+().\-]{8,22}$/', $phone)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Téléphone invalide.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!is_array($linesIn) || $linesIn === []) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Ajoutez au moins une ligne au devis.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $ids = [];
    foreach ($linesIn as $row) {
        if (!is_array($row)) {
            continue;
        }
        $pid = trim((string) ($row['product_id'] ?? ''));
        $qty = (int) ($row['qty'] ?? 0);
        if ($pid === '' || $qty < 1 || $qty > 9999) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Ligne invalide.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $ids[$pid] = ($ids[$pid] ?? 0) + $qty;
    }
    if ($ids === []) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Lignes de devis invalides.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $inClause = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare(
        "SELECT id, name, price_eur, pro_price_eur FROM products WHERE is_active = 1 AND id IN ($inClause)"
    );
    $st->execute(array_keys($ids));
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== count($ids)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Produit indisponible.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $resolved = [];
    $estimated = 0.0;
    $hasSurDevis = false;
    $linesTextParts = [];
    foreach ($rows as $r) {
        $pid = (string) $r['id'];
        $qty = $ids[$pid];
        $name = (string) $r['name'];
        $proRaw = $r['pro_price_eur'];
        $unit = ($proRaw !== null && $proRaw !== '') ? round((float) $proRaw, 2) : null;
        $lineTotal = null;
        if ($unit !== null && $unit > 0) {
            $lineTotal = round($unit * $qty, 2);
            $estimated += $lineTotal;
        } else {
            $hasSurDevis = true;
        }
        $resolved[] = ['product_id' => $pid, 'name' => $name, 'qty' => $qty, 'price_pro_eur' => $unit, 'line_total_eur' => $lineTotal];
        $priceLabel = $lineTotal !== null
            ? number_format($unit, 2, ',', ' ') . ' € HT × ' . $qty . ' = ' . number_format($lineTotal, 2, ',', ' ') . ' €'
            : 'Sur devis × ' . $qty;
        $linesTextParts[] = '  · ' . $name . ' — ' . $priceLabel;
    }
    $linesJson = json_encode($resolved, JSON_UNESCAPED_UNICODE);
    if ($linesJson === false) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Erreur serveur.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    try {
        $ins = $pdo->prepare(
            'INSERT INTO pro_quote_requests (
                company_name, contact_name, email, phone, message, lines_json,
                estimated_total_eur, has_sur_devis, created_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $ins->execute([
            $company, $contact, $email, $phone, $message, $linesJson,
            round($estimated, 2), $hasSurDevis ? 1 : 0,
        ]);
        $newId = (int) $pdo->lastInsertId();
    } catch (Throwable) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Enregistrement impossible.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $cfgFile = __DIR__ . '/config/config.php';
    if (is_readable($cfgFile)) {
        /** @var array<string, mixed> $appCfg */
        $appCfg = require $cfgFile;
        if (is_readable(__DIR__ . '/includes/pro_quote_notify.php')) {
            require_once __DIR__ . '/includes/pro_quote_notify.php';
            tiramii_notify_pro_quote_request(
                $appCfg, $newId, $company, $contact, $email, $phone, $message,
                implode("\n", $linesTextParts), $estimated, $hasSurDevis
            );
        } else {
            tiramii_devis_notify_owner(
                $appCfg, $newId, $company, $contact, $email, $phone, $message,
                implode("\n", $linesTextParts), $estimated, $hasSurDevis
            );
        }
    }
    echo json_encode([
        'ok' => true,
        'quote_id' => $newId,
        'estimated_total_eur' => round($estimated, 2),
        'has_sur_devis' => $hasSurDevis,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$catalog = [];
try {
    $proRows = $pdo
        ->query(
            'SELECT id, name, price_eur, pro_price_eur FROM products WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
        )
        ->fetchAll(PDO::FETCH_ASSOC);
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
} catch (Throwable) {
    $catalog = [];
}

$csrf = csrf_token();
$catalogJson = json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= h($csrf) ?>">
<title>Devis pro — <?= h(brand_name()) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{--vk:#3d2b1f;--vd:#A68966;--bg:#f5f3ef}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--vk);line-height:1.5}
.wrap{max-width:720px;margin:0 auto;padding:5rem 1.25rem 3rem}
h1{font-family:'Playfair Display',serif;font-size:clamp(1.5rem,4vw,2rem);margin-bottom:.5rem}
.lead{color:#444;margin-bottom:1.5rem;font-size:.95rem}
.back{display:inline-block;margin-bottom:1.25rem;color:var(--vd);text-decoration:none;font-weight:600}
.back:hover{text-decoration:underline}
.card{background:#fff;border:1px solid rgba(166,137,102,.28);border-radius:16px;padding:1.25rem;margin-bottom:1rem}
label{display:block;font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--vd);margin-bottom:4px}
input,textarea,select{width:100%;padding:10px 12px;border:1.5px solid #d8d0c4;border-radius:10px;font:inherit;font-size:.92rem;margin-bottom:10px}
textarea{min-height:80px;resize:vertical}
.grid2{display:grid;gap:10px}
@media(min-width:520px){.grid2{grid-template-columns:1fr 1fr}}
.line-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:8px}
.line-row select{flex:1;min-width:180px;margin:0}
.line-row input[type=number]{width:88px;margin:0}
.btn-o{background:transparent;border:2px solid var(--vd);color:var(--vk);padding:8px 14px;border-radius:999px;font-weight:600;cursor:pointer;font-size:.82rem}
.btn-p{background:var(--vk);color:#fff;border:none;padding:14px 22px;border-radius:999px;font-weight:600;cursor:pointer;width:100%;font-size:.95rem;margin-top:8px}
.btn-p:hover{background:var(--vd)}
.btn-p:disabled{opacity:.5;cursor:not-allowed}
.summary{font-size:.86rem;background:#f5f3ef;border-radius:12px;padding:12px;margin:12px 0;display:none}
.summary ul{margin:8px 0 0 1.1rem}
.msg{margin-top:12px;padding:12px;border-radius:12px;font-size:.88rem;display:none}
.msg.ok{display:block;background:#e8f5e9;color:#1b5e20}
.msg.err{display:block;background:#ffebee;color:#b71c1c}
</style>
</head>
<body>
<div class="wrap">
  <a class="back" href="index.php">← Retour boutique</a>
  <h1>Demande de devis pro</h1>
  <p class="lead">Volumes professionnels, tarifs HT. Ajoutez vos lignes et envoyez — nous vous recontactons rapidement.</p>

  <div class="card">
    <div id="lineRows"></div>
    <button type="button" class="btn-o" id="addLine">+ Ajouter une ligne</button>
    <div class="summary" id="quoteSummary">
      <strong>Récapitulatif</strong>
      <ul id="quoteList"></ul>
      <p id="quoteTotal" style="margin-top:8px;font-weight:600"></p>
    </div>
  </div>

  <form id="quoteForm" class="card">
    <div class="grid2">
      <div><label for="co">Établissement</label><input id="co" name="company_name" required maxlength="255" placeholder="Restaurant, salon de thé…"></div>
      <div><label for="ct">Contact</label><input id="ct" name="contact_name" required maxlength="160" placeholder="Prénom Nom"></div>
    </div>
    <div class="grid2">
      <div><label for="em">E-mail</label><input id="em" type="email" name="email" required maxlength="180"></div>
      <div><label for="ph">Téléphone</label><input id="ph" type="tel" name="phone" required maxlength="22" placeholder="06…"></div>
    </div>
    <label for="ms">Message (optionnel)</label>
    <textarea id="ms" name="message" maxlength="4000" placeholder="Délai, adresse de livraison…"></textarea>
    <button type="submit" class="btn-p" id="submitBtn">Envoyer la demande</button>
    <div class="msg" id="formMsg" role="status"></div>
  </form>
</div>
<script>
const CATALOG = <?= $catalogJson ?>;
const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
function addLineRow(){
  const wrap=document.getElementById('lineRows');
  const row=document.createElement('div');
  row.className='line-row';
  const sel=document.createElement('select');
  sel.required=true;
  const o0=document.createElement('option');
  o0.value=''; o0.textContent='— Produit —';
  sel.appendChild(o0);
  CATALOG.forEach(p=>{const o=document.createElement('option');o.value=p.id;o.textContent=p.name;sel.appendChild(o);});
  const qty=document.createElement('input');
  qty.type='number';qty.min=1;qty.max=9999;qty.value=1;qty.required=true;
  const rm=document.createElement('button');
  rm.type='button';rm.className='btn-o';rm.style.padding='6px 12px';rm.textContent='Retirer';
  rm.onclick=()=>{row.remove();updateSummary();};
  row.appendChild(sel);row.appendChild(qty);row.appendChild(rm);
  wrap.appendChild(row);
  sel.addEventListener('change',updateSummary);
  qty.addEventListener('input',updateSummary);
}
function updateSummary(){
  const list=document.getElementById('quoteList');
  const sumEl=document.getElementById('quoteSummary');
  const totalEl=document.getElementById('quoteTotal');
  let html='',total=0,sur=[];
  const seen=new Set();
  document.querySelectorAll('#lineRows .line-row').forEach(r=>{
    const id=r.querySelector('select').value;
    const q=parseInt(r.querySelector('input[type=number]').value,10)||0;
    if(!id||q<1||seen.has(id))return;
    seen.add(id);
    const p=CATALOG.find(x=>x.id===id);
    if(!p)return;
    if(p.price_pro!=null&&p.price_pro>0){
      const line=p.price_pro*q;total+=line;
      html+='<li>'+p.name+' × '+q+' → '+line.toFixed(2).replace('.',',')+' € HT</li>';
    }else sur.push(p.name+' × '+q);
  });
  if(!html&&!sur.length){sumEl.style.display='none';return;}
  sumEl.style.display='block';
  list.innerHTML=html+(sur.length?'<li><em>Sur devis : '+sur.join(', ')+'</em></li>':'');
  totalEl.textContent=total>0?'Estimation HT : '+total.toFixed(2).replace('.',',')+' €':(sur.length?'Montant sur devis — nous vous recontactons.':'');
}
document.getElementById('addLine').onclick=()=>{addLineRow();updateSummary();};
document.getElementById('quoteForm').onsubmit=async e=>{
  e.preventDefault();
  const msg=document.getElementById('formMsg');
  const btn=document.getElementById('submitBtn');
  msg.className='msg';msg.textContent='';
  const lines=[],merged=new Map();
  document.querySelectorAll('#lineRows .line-row').forEach(r=>{
    const id=r.querySelector('select').value;
    const qty=parseInt(r.querySelector('input[type=number]').value,10)||0;
    if(!id||qty<1)return;
    merged.set(id,(merged.get(id)||0)+qty);
  });
  merged.forEach((qty,id)=>lines.push({product_id:id,qty}));
  if(!lines.length){msg.className='msg err';msg.textContent='Ajoutez au moins une ligne.';return;}
  btn.disabled=true;
  try{
    const res=await fetch('devis.php',{
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
      body:JSON.stringify({
        company_name:document.getElementById('co').value.trim(),
        contact_name:document.getElementById('ct').value.trim(),
        email:document.getElementById('em').value.trim(),
        phone:document.getElementById('ph').value.trim(),
        message:document.getElementById('ms').value.trim(),
        lines
      })
    });
    const data=await res.json();
    if(!data.ok)throw new Error(data.error||'Erreur');
    msg.className='msg ok';
    msg.textContent='Demande #'+data.quote_id+' envoyée. Merci !';
    document.getElementById('quoteForm').reset();
    document.getElementById('lineRows').innerHTML='';
    addLineRow();updateSummary();
  }catch(err){
    msg.className='msg err';
    msg.textContent=err.message||'Envoi impossible.';
  }
  btn.disabled=false;
};
if(CATALOG.length)addLineRow();
</script>
</body>
</html>
