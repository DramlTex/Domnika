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


function cartSetQty(item, qty) {
  const id = item.articul;
  if (!id) return;
  const max = parseFloat(item.stock) || 0;
  qty = Math.min(Math.max(0, qty), max);
  if (qty <= 0) {
    delete __cart[id];
  } else {
    __cart[id] = { item: item, qty: qty };

  }
  cartSave();
  updateCartBadge();
  updateProductModalQty(id);

  if (isCartOpen()) renderCartItems();
}

function cartChange(item, delta) {
  cartSetQty(item, cartGetQty(item.articul) + delta);

}

function updateCartBadge() {
  const badge = document.getElementById('cartBadge');
  if (!badge) return;
  const total = Object.values(__cart).reduce((s, c) => s + c.qty, 0);
  badge.textContent = total > 0 ? total : '';
}

function updateProductModalQty(id) {

  const input = document.getElementById('productModalQty');
  if (input && id) {
    input.value = cartGetQty(id);
  }
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
    div.innerHTML = `
      <span class="cart-name">${c.item.name}</span>
      <div class="cart-controls">
        <button class="cart-minus" data-id="${c.item.articul}">-</button>
        <input type="number" class="cart-qty" data-id="${c.item.articul}" value="${c.qty}" min="0">
        <button class="cart-plus" data-id="${c.item.articul}">+</button>
        <button class="cart-remove" data-id="${c.item.articul}">Удалить</button>
      </div>`;
    list.appendChild(div);
  });
  list.querySelectorAll('.cart-minus').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      const c = __cart[id];
      if (c) cartSetQty(c.item, c.qty - 1);
    });
  });
  list.querySelectorAll('.cart-plus').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      const c = __cart[id];
      if (c) cartSetQty(c.item, c.qty + 1);
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
      const c = __cart[id];
      if (c) cartSetQty(c.item, parseInt(input.value, 10) || 0);
    });

  });
}

function openCartModal() {

  renderCartItems();
  document.getElementById('cartModal').style.display = 'block';

}

function closeCartModal() {
  document.getElementById('cartModal').style.display = 'none';
}

function setupCart() {
  cartLoad();
  updateCartBadge();
  const openBtn = document.getElementById('openCartButton');
  if (openBtn) openBtn.addEventListener('click', openCartModal);
  document.getElementById('cartModal').addEventListener('click', e => {
    if (e.target.id === 'cartModal') closeCartModal();
  });
}
