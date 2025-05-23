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
  if (qty <= 0) {
    delete __cart[id];
  } else {
    if (!__cart[id]) {
      __cart[id] = { item: item, qty: 0 };
    }
    __cart[id].item = item;
    __cart[id].qty = qty;

  }
  cartSave();
  updateCartBadge();
  updateProductModalQty(id);

  renderCartItems();
}

function cartChange(item, delta) {
  const qty = cartGetQty(item.articul) + delta;
  cartSetQty(item, qty);

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

function renderCartItems() {
  const list = document.getElementById('cartItems');
  if (!list) return;
  list.innerHTML = '';
  Object.values(__cart).forEach(c => {
    const row = document.createElement('div');
    row.className = 'cart-item';

    const nameSpan = document.createElement('span');
    nameSpan.textContent = c.item.name;

    const controls = document.createElement('div');
    controls.className = 'cart-controls';

    const minus = document.createElement('button');
    minus.textContent = '-';
    minus.onclick = () => cartChange(c.item, -1);

    const input = document.createElement('input');
    input.type = 'number';
    input.min = 0;
    input.value = c.qty;
    input.onchange = () => cartSetQty(c.item, parseInt(input.value) || 0);

    const plus = document.createElement('button');
    plus.textContent = '+';
    plus.onclick = () => cartChange(c.item, 1);

    controls.appendChild(minus);
    controls.appendChild(input);
    controls.appendChild(plus);

    row.appendChild(nameSpan);
    row.appendChild(controls);
    list.appendChild(row);
  });
}

function openCartModal() {
  const modal = document.getElementById('cartModal');
  renderCartItems();

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
