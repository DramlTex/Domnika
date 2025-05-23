/**
 * Simple client-side cart implementation.
 */
let __cart = {};
const STORES = ['store1', 'store2', 'store3', 'store4'];
const STORE_LABELS = {
  store1: 'Алтуфьево',
  store2: 'Ивантеевка',
  store3: 'Купавна',
  store4: 'Можайск'
};

function cartLoad() {
  try {
    __cart = JSON.parse(localStorage.getItem('cart') || '{}');
    for (const [id, entry] of Object.entries(__cart)) {
      if (typeof entry.qty === 'number') {
        entry.qty = { store1: entry.qty };
      }
    }
  } catch (e) {
    __cart = {};
  }
}

function cartSave() {
  localStorage.setItem('cart', JSON.stringify(__cart));
}

function cartGetQty(id, store) {
  const entry = __cart[id];
  if (!entry) return 0;
  if (store) {
    return entry.qty?.[store] || 0;
  }
  return Object.values(entry.qty || {}).reduce((s, q) => s + q, 0);
}


function cartSetQty(item, qty, store) {
  const id = item.articul;
  if (!id) return;

  if (store) {
    const max = parseFloat(item['stock_' + store]) || 0;
    qty = Math.min(Math.max(0, qty), max);
    if (!__cart[id]) {
      __cart[id] = { item: item, qty: {} };
    }
    __cart[id].qty[store] = qty;
    if (Object.values(__cart[id].qty).every(v => !v)) {
      delete __cart[id];
    }
  } else {
    const max = parseFloat(item.stock) || 0;
    qty = Math.min(Math.max(0, qty), max);
    if (qty <= 0) {
      delete __cart[id];
    } else {
      __cart[id] = { item: item, qty: { store1: qty } };
    }
  }
  cartSave();
  updateCartBadge();
  updateProductModalQty(id);
  updateCartTotal();

  if (isCartOpen()) renderCartItems();
}

function cartChange(item, delta, store) {
  cartSetQty(item, cartGetQty(item.articul, store) + delta, store);

}

function updateCartBadge() {
  const badge = document.getElementById('cartBadge');
  if (!badge) return;
  const total = Object.values(__cart).reduce((sum, c) => {
    return sum + Object.values(c.qty || {}).reduce((s, q) => s + q, 0);
  }, 0);
  badge.textContent = total > 0 ? total : '';
}

function updateCartTotal() {
  const totalEl = document.getElementById('cartTotal');
  if (!totalEl) return;
  const total = Object.values(__cart).reduce((sum, c) => {
    const price = parseFloat(c.item.price) || 0;
    const qty = Object.values(c.qty || {}).reduce((s, q) => s + q, 0);
    return sum + price * qty;
  }, 0);
  totalEl.textContent = total.toFixed(2);
}

function updateProductModalQty(id) {
  if (!id) return;
  STORES.forEach((s, i) => {
    const input = document.getElementById('productModalQty' + (i + 1));
    if (input) input.value = cartGetQty(id, s);
  });
}


function isCartOpen() {
  const modal = document.getElementById('cartModal');
  return modal && modal.style.display === 'block';
}

function renderCartItems() {
  const list = document.getElementById('cartItems');
  list.innerHTML = '';
  Object.values(__cart).forEach(c => {
    const div = document.createElement('div');
    div.className = 'cart-item';
    let html = `<span class="cart-name">${c.item.name}</span>`;
    STORES.forEach((s, i) => {
      const q = c.qty[s] || 0;
      html += `
        <div class="cart-store">
          <span class="store-name">${STORE_LABELS[s]}:</span>
          <div class="cart-controls">
            <button class="cart-minus" data-id="${c.item.articul}" data-store="${s}">-</button>
            <input type="number" class="cart-qty" data-id="${c.item.articul}" data-store="${s}" value="${q}" min="0">
            <button class="cart-plus" data-id="${c.item.articul}" data-store="${s}">+</button>
          </div>
        </div>`;
    });
    html += `<button class="cart-remove" data-id="${c.item.articul}">🗑️</button>`;
    div.innerHTML = html;
    list.appendChild(div);
  });
  list.querySelectorAll('.cart-minus').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      const store = btn.dataset.store;
      const c = __cart[id];
      if (c) cartSetQty(c.item, cartGetQty(id, store) - 1, store);
    });
  });
  list.querySelectorAll('.cart-plus').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      const store = btn.dataset.store;
      const c = __cart[id];
      if (c) cartSetQty(c.item, cartGetQty(id, store) + 1, store);
    });
  });
  list.querySelectorAll('.cart-remove').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      const c = __cart[id];
      if (c) cartSetQty(c.item, 0);
    });
  });
  list.querySelectorAll('.cart-qty').forEach(input => {
    input.addEventListener('input', () => {
      const id = input.dataset.id;
      const store = input.dataset.store;
      const c = __cart[id];
      if (c) cartSetQty(c.item, parseInt(input.value, 10) || 0, store);
    });

  });
  updateCartTotal();
}

function openCartModal() {

  renderCartItems();
  loadCartDetails();
  updateCartTotal();
  document.getElementById('cartModal').style.display = 'block';

}

function closeCartModal() {
  document.getElementById('cartModal').style.display = 'none';
}

function loadCartDetails() {
  const comment = document.getElementById('cartComment');
  const inn = document.getElementById('cartInn');
  const date = document.getElementById('cartDate');
  if (comment) comment.value = localStorage.getItem('cartComment') || '';
  if (inn) inn.value = localStorage.getItem('cartInn') || '';
  if (date) date.value = localStorage.getItem('cartDate') || '';
}

function saveCartDetails() {
  const comment = document.getElementById('cartComment');
  const inn = document.getElementById('cartInn');
  const date = document.getElementById('cartDate');
  if (comment) localStorage.setItem('cartComment', comment.value);
  if (inn) localStorage.setItem('cartInn', inn.value);
  if (date) localStorage.setItem('cartDate', date.value);
}

function submitCart() {
  saveCartDetails();
  alert('Заказ оформлен.');
  __cart = {};
  cartSave();
  updateCartBadge();
  renderCartItems();
  closeCartModal();
}

function setupCart() {
  cartLoad();
  updateCartBadge();
  const openBtn = document.getElementById('openCartButton');
  if (openBtn) openBtn.addEventListener('click', openCartModal);
  const submitBtn = document.getElementById('cartSubmit');
  if (submitBtn) submitBtn.addEventListener('click', submitCart);
  document.getElementById('cartModal').addEventListener('click', e => {
    if (e.target.id === 'cartModal') closeCartModal();
  });
}
