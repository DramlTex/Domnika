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
  document.getElementById('productModalName').textContent = item.name || '—';
  document.getElementById('productModalArticul').textContent = item.articul || '—';
  document.getElementById('productModalDescription').textContent = item.description || '—';
  document.getElementById('productModalTip').textContent = item.tip || '—';
  document.getElementById('productModalSupplier').textContent = item.supplier || '—';
  document.getElementById('productModalMass').textContent = item.mass || '—';
  document.getElementById('productModalPrice').textContent = item.price || '0';
  document.getElementById('productModalStock').textContent = formatStock(item.stock);
  document.getElementById('productModalStock1').textContent = formatStock(item.stock_store1);
  document.getElementById('productModalStock2').textContent = formatStock(item.stock_store2);
  document.getElementById('productModalStock3').textContent = formatStock(item.stock_store3);
  document.getElementById('productModalStock4').textContent = formatStock(item.stock_store4);
  document.getElementById('productModalVolume').textContent = item.volumeWeight || '—';
  const modalImg = document.getElementById('productModalImg');
  if (item.photoFull) {
    modalImg.src = 'image_proxy.php?url=' + encodeURIComponent(item.photoFull);
  } else {
    modalImg.src = '';
  }
  document.getElementById('productModal').classList.add('open');
}

/** Close product modal. */
function closeProductModal() {
  document.getElementById('productModal').classList.remove('open');
}
