/**
 * Show modal with full size image.
 * @param {string} fullUrl
 */
function openModal(fullUrl) {
  const modal = document.getElementById('imageModal');
  const modalImg = document.getElementById('modalImg');
  modalImg.src = fullUrl;
  modal.style.display = 'block';
}

/** Close image modal. */
function closeModal() {
  document.getElementById('imageModal').style.display = 'none';
}

/**
 * Format stock value to two decimals.
 * @param {number|string} num
 * @returns {string}
 */
function formatStock(num) {
  if (!num) return '0';
  const val = parseFloat(num);
  if (isNaN(val)) return num;
  return val.toFixed(2);
}

/**
 * Open product details modal.
 * @param {Object} item
 */
function openProductModal(item) {
  const setText = (id, value) => {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
  };
  setText('productModalName', item.name || '—');
  setText('productModalArticul', item.articul || '—');
  setText('productModalDescription', item.description || '—');
  setText('productModalTip', item.tip || '—');
  setText('productModalSupplier', item.supplier || '—');
  setText('productModalMass', item.mass || '—');
  setText('productModalPrice', item.price || '0');
  setText('productModalStock', formatStock(item.stock));
  setText('productModalStock1', formatStock(item.stock_store1));
  setText('productModalStock2', formatStock(item.stock_store2));
  setText('productModalStock3', formatStock(item.stock_store3));
  setText('productModalStock4', formatStock(item.stock_store4));
  setText('productModalVolume', item.volumeWeight || '—');
  const modalImg = document.getElementById('productModalImg');
  if (modalImg) {
    if (item.photoFull) {
      modalImg.src = 'image_proxy.php?url=' + encodeURIComponent(item.photoFull);
    } else {
      modalImg.src = '';
    }
  }
  // cart controls removed
  const modal = document.getElementById('productModal');
  if (modal) modal.classList.add('open');
}

/** Close product modal. */
function closeProductModal() {
  document.getElementById('productModal').classList.remove('open');
}
