/**
 * Panier, réservations, commande — API PHP (remplace Firebase + EmailJS).
 */
const BOOT = window.__TIRAMII__ || { csrf: '', products: [] };
const PRODUCTS = BOOT.products || [];
const FIXED_BOXES = {
  box1: {
    id: 'box1',
    stockId: 'box1',
    name: 'Box gourmande',
    price: 10,
    displayFlavors: ['Bueno', 'Oreo', 'Speculos', 'KitKat'],
    cartImgKey: 'oreo',
  },
  box_supreme: {
    id: 'box_supreme',
    stockId: 'box_supreme',
    name: 'Box suprême',
    price: 10,
    displayFlavors: ["M&M's", 'Raffaello', 'Daim', 'Kinder Bueno White'],
    cartImgKey: 'kw',
  },
};

const HOLD_MS = 5 * 60 * 1000;
const SESSION_KEY = 'tiramii_session_id';
const CART_KEY = 'tiramii_cart';
const DELIVERY_ALLOWED_DEPTS = ['75', '91', '92', '93', '94'];
/** Libellé enregistré en base et affiché au client (pas de créneau au quart d’heure). */
const DELIVERY_TIME_LABEL = 'Après 21h30';

const sessionId = localStorage.getItem(SESSION_KEY) || crypto.randomUUID();
localStorage.setItem(SESSION_KEY, sessionId);

function apiUrl(name) {
  const base = window.location.pathname.replace(/[^/]*$/, '');
  return `${base}api/${name}`;
}

let STOCK = {};
let RESERVATIONS = {};
let cart = loadCartFromStorage();
let payMethod = 'cash';
let holdInterval = null;
let holdExpiredShown = false;

function imgSrcForProduct(id) {
  const safe = String(id).replace(/[^a-z0-9_-]/gi, '');
  const el = document.querySelector(`.product-card[data-product-id="${safe}"] img`);
  if (el && el.src) return el.src;
  const oreo = document.querySelector('.product-card[data-product-id="oreo"] img');
  return oreo && oreo.src ? oreo.src : '';
}

function loadCartFromStorage() {
  try {
    const raw = localStorage.getItem(CART_KEY);
    const parsed = raw ? JSON.parse(raw) : [];
    if (!Array.isArray(parsed)) return [];
    return parsed
      .map((item) => {
        if (FIXED_BOXES[item.id]) {
          const box = FIXED_BOXES[item.id];
          return {
            id: box.id,
            stockId: box.stockId,
            name: box.name,
            price: box.price,
            qty: Math.max(1, parseInt(item.qty || 1, 10)),
            imgSrc: imgSrcForProduct(box.cartImgKey || 'oreo'),
            boxLabel: box.displayFlavors.join(' · '),
          };
        }
        const p = PRODUCTS.find((prod) => prod.id === item.id);
        if (!p) return null;
        return {
          ...p,
          qty: Math.max(1, parseInt(item.qty || 1, 10)),
          imgSrc: imgSrcForProduct(p.id),
        };
      })
      .filter(Boolean);
  } catch {
    return [];
  }
}

function saveCartToStorage() {
  localStorage.setItem(CART_KEY, JSON.stringify(cart.map((i) => ({ id: i.id, qty: i.qty }))));
}

function isReservationActive(res) {
  return !!(res && res.expiresAt && res.expiresAt > Date.now() && res.items);
}

function normalizeReservations(data) {
  const src = data && typeof data === 'object' ? data : {};
  const cleaned = {};
  Object.entries(src).forEach(([key, value]) => {
    if (isReservationActive(value)) cleaned[key] = value;
  });
  return cleaned;
}

function getMyReservation() {
  const res = RESERVATIONS[sessionId];
  return isReservationActive(res) ? res : null;
}

function getQty(id) {
  if (STOCK[id] === undefined) return 0;
  const n = parseInt(STOCK[id], 10);
  return Number.isNaN(n) ? 0 : n;
}

function getReservedQty(id, excludedSessionId = null) {
  let total = 0;
  Object.entries(RESERVATIONS).forEach(([key, res]) => {
    if (!isReservationActive(res) || key === excludedSessionId) return;
    total += parseInt((res.items && res.items[id]) || 0, 10) || 0;
  });
  return total;
}

function getDisplayAvailableQty(id) {
  const stock = getQty(id);
  if (stock === 999) return 999;
  return Math.max(0, stock - getReservedQty(id));
}

function getAvailableForSession(id) {
  const stock = getQty(id);
  if (stock === 999) return 999;
  return Math.max(0, stock - getReservedQty(id, sessionId));
}

function cartToReservationMap(nextCart) {
  const out = {};
  nextCart.forEach((item) => {
    const key = item.stockId || item.id;
    out[key] = (out[key] || 0) + item.qty;
  });
  return out;
}

async function pullState() {
  const r = await fetch(apiUrl('state.php'), { credentials: 'same-origin' });
  if (!r.ok) throw new Error('État stock indisponible');
  const data = await r.json();
  STOCK = data.stock || {};
  RESERVATIONS = normalizeReservations(data.reservations || {});
}

async function syncReservation(nextCart) {
  const items = cartToReservationMap(nextCart);
  const r = await fetch(apiUrl('sync-reservation.php'), {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': BOOT.csrf,
    },
    body: JSON.stringify({ session_id: sessionId, items }),
  });
  const data = await r.json().catch(() => ({}));
  if (!r.ok || !data.ok) {
    throw new Error(data.error || 'Impossible de réserver le stock.');
  }
  await pullState();
}

function showToast(message) {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = message;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2200);
}

function updateProductCards() {
  PRODUCTS.forEach((p) => {
    const qty = getDisplayAvailableQty(p.id);
    const available = qty > 0;
    const low = qty !== 999 && qty <= 3 && qty > 0;
    const safe = String(p.id).replace(/[^a-z0-9_-]/gi, '');
    const card = document.querySelector(`.product-card[data-product-id="${safe}"]`);
    if (!card) return;
    const btn = document.getElementById(`btn-${p.id}`);
    let sold = card.querySelector('.sold-overlay');
    let badge = card.querySelector('.stock-badge');
    if (!available) {
      if (!sold) {
        sold = document.createElement('div');
        sold.className = 'sold-overlay';
        sold.textContent = 'Épuisé';
        card.querySelector('.product-img').appendChild(sold);
      }
      if (btn) {
        btn.disabled = true;
        btn.textContent = '❌ Épuisé';
      }
    } else {
      sold?.remove();
      if (btn) {
        btn.disabled = false;
        btn.textContent = '🛒 Ajouter';
      }
    }
    if (low) {
      if (!badge) {
        badge = document.createElement('div');
        badge.className = 'stock-badge';
        card.querySelector('.product-img').appendChild(badge);
      }
      badge.textContent = `⚠️ Plus que ${qty}`;
    } else {
      badge?.remove();
    }
  });
}

function observeReveal() {
  const obs = new IntersectionObserver(
    (entries) => {
      entries.forEach((e, i) => {
        if (e.isIntersecting) setTimeout(() => e.target.classList.add('visible'), i * 80);
      });
    },
    { threshold: 0.1 }
  );
  document.querySelectorAll('.reveal:not(.visible)').forEach((el) => obs.observe(el));
}

async function validateDeliveryForOrder(_address, zip, _city, _cartTotalEur) {
  const digits = String(zip).replace(/\D/g, '');
  if (digits.length < 5) {
    throw new Error('📍 Code postal invalide. Indiquez un code postal à 5 chiffres.');
  }
  const dept = digits.slice(0, 2);
  if (!DELIVERY_ALLOWED_DEPTS.includes(dept)) {
    throw new Error(
      '📍 La livraison est limitée aux départements 75, 91, 92, 93 et 94. Votre commande ne peut pas être livrée à cette adresse.'
    );
  }
}

function formatCountdown(ms) {
  const totalSeconds = Math.max(0, Math.ceil(ms / 1000));
  const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
  const seconds = String(totalSeconds % 60).padStart(2, '0');
  return `${minutes}:${seconds}`;
}

async function releaseMyReservationAndCart(showMsg = false) {
  try {
    await syncReservation([]);
  } catch {
    /* ignore */
  }
  cart = [];
  saveCartToStorage();
  updateCart();
  if (showMsg) showToast('⏰ Réservation expirée. Le panier a été vidé.');
}

function updateHoldBanner() {
  const banner = document.getElementById('holdBanner');
  if (!banner) return;
  const myReservation = getMyReservation();
  if (!myReservation || cart.length === 0) {
    banner.style.display = 'none';
    banner.innerHTML = '';
    return;
  }
  const remaining = myReservation.expiresAt - Date.now();
  if (remaining <= 0) {
    banner.style.display = 'none';
    if (!holdExpiredShown) {
      holdExpiredShown = true;
      releaseMyReservationAndCart(true);
    }
    return;
  }
  holdExpiredShown = false;
  banner.style.display = 'block';
  banner.innerHTML = `<strong>⏳ Stock réservé pendant ${formatCountdown(remaining)}</strong>Le stock de ton panier est bloqué 5 minutes pour éviter les doubles commandes.`;
}

function startHoldWatcher() {
  if (holdInterval) clearInterval(holdInterval);
  holdInterval = setInterval(updateHoldBanner, 1000);
  updateHoldBanner();
}

function updateCart() {
  const total = cart.reduce((s, i) => s + i.price * i.qty, 0);
  const count = cart.reduce((s, i) => s + i.qty, 0);
  const cartCount = document.getElementById('cartCount');
  const cartTotal = document.getElementById('cartTotal');
  const checkoutBtn = document.getElementById('checkoutBtn');
  const cartPromo = document.getElementById('cartPromo');
  if (cartCount) cartCount.textContent = String(count);
  if (cartTotal) cartTotal.textContent = total.toFixed(2).replace('.', ',') + ' €';
  if (checkoutBtn) checkoutBtn.disabled = cart.length === 0;
  if (cartPromo) cartPromo.style.display = count >= 2 ? 'block' : 'none';
  const c = document.getElementById('cartItems');
  if (!c) return;
  if (cart.length === 0) {
    c.innerHTML = '<div class="cart-empty"><div>🍮</div>Votre panier est vide.</div>';
    updateHoldBanner();
    return;
  }
  c.innerHTML = cart
    .map(
      (item, idx) => `
    <div class="cart-item">
      <img class="cart-item-img" src="${item.imgSrc}" alt="${item.name.replace(/"/g, '&quot;')}">
      <div class="cart-item-info">
        <div class="cart-item-name">${item.name}</div>
        <div class="cart-item-price">${(item.price * item.qty).toFixed(2).replace('.', ',')}€</div>
        ${item.boxLabel ? `<div style="font-size:.75rem;color:#8a7090;margin-top:.25rem">${item.boxLabel}</div>` : ''}
        <div class="cart-item-controls">
          <button class="qty-btn" onclick="changeQty(${idx},-1)">−</button>
          <span class="qty-num">${item.qty}</span>
          <button class="qty-btn" onclick="changeQty(${idx},1)">+</button>
          <button class="remove-item" onclick="removeItem(${idx})">🗑</button>
        </div>
      </div>
    </div>`
    )
    .join('');
  updateHoldBanner();
}

window.toggleCart = function () {
  document.getElementById('cartSidebar')?.classList.toggle('open');
  document.getElementById('cartOverlay')?.classList.toggle('open');
};

window.openCheckout = function () {
  if (!cart.length) return;
  window.toggleCart();
  const total = cart.reduce((s, i) => s + i.price * i.qty, 0);
  const count = cart.reduce((s, i) => s + i.qty, 0);
  const reservation = getMyReservation();
  const timerHtml = reservation
    ? `<div class="summary-item"><span>⏳ Réservation</span><span>${formatCountdown(reservation.expiresAt - Date.now())}</span></div>`
    : '';
  const summaryItems = document.getElementById('summaryItems');
  if (summaryItems) {
    summaryItems.innerHTML =
      cart
        .map(
          (i) =>
            `<div class="summary-item"><span>${i.name}${i.boxLabel ? ' (' + i.boxLabel + ')' : ''} x${i.qty}</span><span>${(i.price * i.qty).toFixed(2).replace('.', ',')}€</span></div>`
        )
        .join('') +
      (count >= 2 ? '<div class="summary-item"><span>🥤 Boisson</span><span class="summary-free">Offerte</span></div>' : '') +
      timerHtml;
  }
  const summaryTotal = document.getElementById('summaryTotal');
  if (summaryTotal) summaryTotal.textContent = total.toFixed(2).replace('.', ',') + ' €';
  document.getElementById('checkoutModal')?.classList.add('open');
  const cf = document.getElementById('checkoutForm');
  if (cf) cf.style.display = 'block';
  document.getElementById('successScreen')?.classList.remove('show');
};

window.closeCheckout = function () {
  document.getElementById('checkoutModal')?.classList.remove('open');
};

window.selectPay = function (el, m) {
  document.querySelectorAll('.pay-method').forEach((e) => e.classList.remove('active'));
  el.classList.add('active');
  payMethod = m;
};

window.addFixedBox = async function (boxKey) {
  const box = FIXED_BOXES[boxKey];
  if (!box) return;
  const nextCart = cart.map((item) => ({ ...item }));
  const existing = nextCart.find((i) => i.id === box.id);
  const currentQty = existing ? existing.qty : 0;
  const availableForMe = getAvailableForSession(box.stockId);
  if (availableForMe !== 999 && currentQty + 1 > availableForMe) {
    showToast('⚠️ Stock insuffisant pour cette box.');
    return;
  }
  if (existing) existing.qty++;
  else {
    nextCart.push({
      id: box.id,
      stockId: box.stockId,
      name: box.name,
      price: box.price,
      qty: 1,
      imgSrc: imgSrcForProduct(box.cartImgKey || 'oreo'),
      boxLabel: box.displayFlavors.join(' · '),
    });
  }
  try {
    await syncReservation(nextCart);
    cart = nextCart;
    saveCartToStorage();
    updateCart();
    document.getElementById('cartSidebar')?.classList.add('open');
    document.getElementById('cartOverlay')?.classList.add('open');
    showToast('✅ ' + box.name + ' ajoutée au panier');
    startHoldWatcher();
  } catch (e) {
    showToast(e.message || 'Impossible de réserver cette box.');
  }
};

window.addToCart = async function (id) {
  const p = PRODUCTS.find((x) => x.id === id);
  if (!p) return;
  const nextCart = cart.map((item) => ({ ...item }));
  const inCart = nextCart.find((i) => i.id === id);
  const currentQty = inCart ? inCart.qty : 0;
  const availableForMe = getAvailableForSession(id);
  if (availableForMe !== 999 && currentQty + 1 > availableForMe) {
    showToast(`⚠️ Stock réservé/limité pour ${p.name}`);
    return;
  }
  if (inCart) inCart.qty++;
  else nextCart.push({ ...p, qty: 1, imgSrc: imgSrcForProduct(p.id) });
  try {
    await syncReservation(nextCart);
    cart = nextCart;
    saveCartToStorage();
    updateCart();
    const btn = document.getElementById('btn-' + id);
    if (btn) {
      btn.textContent = '✓ Réservé !';
      btn.classList.add('added');
      setTimeout(() => {
        btn.textContent = '🛒 Ajouter';
        btn.classList.remove('added');
      }, 1400);
    }
    document.getElementById('cartSidebar')?.classList.add('open');
    document.getElementById('cartOverlay')?.classList.add('open');
    startHoldWatcher();
  } catch (e) {
    showToast(e.message || 'Impossible de réserver ce stock.');
  }
};

window.changeQty = async function (idx, d) {
  const item = cart[idx];
  if (!item) return;
  const nextCart = cart.map((entry) => ({ ...entry }));
  nextCart[idx].qty += d;
  if (nextCart[idx].qty <= 0) nextCart.splice(idx, 1);
  const stockKey = item.stockId || item.id;
  if (d > 0) {
    const desiredQty = (nextCart.find((i) => i.id === item.id) || { qty: 0 }).qty;
    const availableForMe = getAvailableForSession(stockKey);
    if (availableForMe !== 999 && desiredQty > availableForMe) {
      showToast('⚠️ Stock maximum atteint !');
      return;
    }
  }
  try {
    await syncReservation(nextCart);
    cart = nextCart;
    saveCartToStorage();
    updateCart();
    startHoldWatcher();
  } catch (e) {
    showToast(e.message || 'Impossible de mettre à jour le panier.');
  }
};

window.removeItem = async function (idx) {
  const nextCart = cart.map((entry) => ({ ...entry }));
  nextCart.splice(idx, 1);
  try {
    await syncReservation(nextCart);
    cart = nextCart;
    saveCartToStorage();
    updateCart();
    startHoldWatcher();
  } catch (e) {
    showToast(e.message || 'Impossible de retirer cet article.');
  }
};

window.placeOrder = async function () {
  const first = document.getElementById('firstName')?.value.trim() || '';
  const last = document.getElementById('lastName')?.value.trim() || '';
  const phone = document.getElementById('phone')?.value.trim() || '';
  const addr = document.getElementById('address')?.value.trim() || '';
  const zip = document.getElementById('zip')?.value.trim() || '';
  const city = document.getElementById('city')?.value.trim() || '';
  const note = document.getElementById('note')?.value.trim() || '';
  if (!first || !phone || !addr) {
    alert('Merci de remplir prénom, téléphone et adresse.');
    return;
  }
  if (!cart.length) {
    showToast('Votre panier est vide.');
    return;
  }
  const cartTotal = cart.reduce((s, i) => s + i.price * i.qty, 0);
  try {
    await validateDeliveryForOrder(addr, zip, city, cartTotal);
  } catch (e) {
    showToast(e.message || 'Adresse ou montant non conforme aux conditions de livraison.');
    return;
  }

  const cartPayload = cart.map((i) => ({
    id: i.id,
    stockId: i.stockId || i.id,
    name: i.name,
    price: i.price,
    qty: i.qty,
    box_label: i.boxLabel || null,
  }));

  try {
    const r = await fetch(apiUrl('order.php'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': BOOT.csrf,
      },
      body: JSON.stringify({
        session_id: sessionId,
        first_name: first,
        last_name: last,
        phone,
        address: addr,
        zip,
        city,
        delivery_time: DELIVERY_TIME_LABEL,
        note,
        payment_method: payMethod,
        cart: cartPayload,
      }),
    });
    const data = await r.json().catch(() => ({}));
    if (!r.ok || !data.ok) {
      showToast(data.error || 'Commande impossible.');
      return;
    }
  } catch {
    showToast('Erreur réseau lors de la commande.');
    return;
  }

  const successName = document.getElementById('successName');
  const successTime = document.getElementById('successTime');
  const successPhone = document.getElementById('successPhone');
  if (successName) successName.textContent = first;
  if (successTime) successTime.textContent = DELIVERY_TIME_LABEL.toLowerCase();
  if (successPhone) successPhone.textContent = phone;
  const checkoutForm = document.getElementById('checkoutForm');
  if (checkoutForm) checkoutForm.style.display = 'none';
  document.getElementById('successScreen')?.classList.add('show');

  cart = [];
  saveCartToStorage();
  updateCart();
  try {
    await pullState();
    updateProductCards();
  } catch {
    /* ignore */
  }
};

function initReviewsSort() {
  const grid = document.getElementById('reviewsGrid');
  const sel = document.getElementById('reviewsSort');
  if (!grid || !sel) return;

  const cards = () => [...grid.querySelectorAll('.review-card')];

  sel.addEventListener('change', () => {
    const mode = sel.value;
    const list = cards();
    if (mode === 'name-asc') {
      list.sort((a, b) =>
        (a.getAttribute('data-review-name') || '').localeCompare(
          b.getAttribute('data-review-name') || '',
          'fr',
          { sensitivity: 'base' },
        ),
      );
    } else if (mode === 'name-desc') {
      list.sort((a, b) =>
        (b.getAttribute('data-review-name') || '').localeCompare(
          a.getAttribute('data-review-name') || '',
          'fr',
          { sensitivity: 'base' },
        ),
      );
    } else {
      list.sort(
        (a, b) =>
          parseInt(a.getAttribute('data-review-idx') || '0', 10) -
          parseInt(b.getAttribute('data-review-idx') || '0', 10),
      );
    }
    list.forEach((el) => grid.appendChild(el));
  });
}

async function init() {
  try {
    await pullState();
  } catch {
    showToast('⚠️ Impossible de charger le stock');
  }
  const myReservation = getMyReservation();
  if (!myReservation && cart.length) {
    cart = [];
    saveCartToStorage();
  }
  cart = loadCartFromStorage();
  updateProductCards();
  updateCart();
  observeReveal();
  initReviewsSort();
  startHoldWatcher();
  setInterval(async () => {
    try {
      await pullState();
      const mr = getMyReservation();
      if (!mr && cart.length) {
        cart = [];
        saveCartToStorage();
        updateCart();
        showToast('⏰ Réservation expirée. Panier vidé.');
      }
      updateProductCards();
      updateCart();
    } catch {
      /* ignore */
    }
  }, 4000);
}

init();
