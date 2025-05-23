/**
 * Simple client-side cart implementation.
 */
let __cart = {};

function cartLoad() {
  try {
    __cart = JSON.parse(localStorage.getItem('cart') || '{}');
  } catch (e) {
    __cart = {};
  }
}

function cartSave() {
  localStorage.setItem('cart', JSON.stringify(__cart));
}

function cartGetQty(id) {
  return __cart[id]?.qty || 0;
}

function cartChange(item, delta) {
  const id = item.articul;
  if (!id) return;
  if (!__cart[id]) {
    __cart[id] = { item: item, qty: 0 };
  }
  __cart[id].qty += delta;
  if (__cart[id].qty <= 0) {
    delete __cart[id];
  }
  cartSave();
  updateCartBadge();
  updateProductModalQty(id);
}

function updateCartBadge() {
  const badge = document.getElementById('cartBadge');
  if (!badge) return;
  const total = Object.values(__cart).reduce((s, c) => s + c.qty, 0);
  badge.textContent = total > 0 ? total : '';
}

function updateProductModalQty(id) {
  const span = document.getElementById('productModalQty');
  if (span && id) {
    span.textContent = cartGetQty(id);
  }
}

function openCartModal() {
  const modal = document.getElementById('cartModal');
  const list = document.getElementById('cartItems');
  list.innerHTML = '';
  Object.values(__cart).forEach(c => {
    const div = document.createElement('div');
    div.className = 'cart-item';
    div.textContent = `${c.item.name} (${c.qty})`;
    list.appendChild(div);
  });
  modal.style.display = 'block';
}

function closeCartModal() {
  document.getElementById('cartModal').style.display = 'none';
}

function setupCart() {
  cartLoad();
  updateCartBadge();
  const openBtn = document.getElementById('openCartButton');
  if (openBtn) openBtn.addEventListener('click', openCartModal);
  const closeBtn = document.getElementById('cartModalClose');
  if (closeBtn) closeBtn.addEventListener('click', closeCartModal);
  document.getElementById('cartModal').addEventListener('click', e => {
    if (e.target.id === 'cartModal') closeCartModal();
  });
}
