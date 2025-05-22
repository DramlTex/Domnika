/**
 * Render product table in chunks for performance.
 * @param {Array<Object>} data
 */
function fillTable(data) {
  const tbody = document.querySelector('#priceList tbody');
  tbody.innerHTML = '';
  const btn = document.getElementById('btnRefresh');
  if (btn) btn.classList.add('hover-effect');

  let index = 0;
  const chunkSize = 50;

  function processChunk() {
    const end = Math.min(index + chunkSize, data.length);
    for (let i = index; i < end; i++) {
      const item = data[i];
      const storeVal = document.getElementById('filterStore').value;
      let stockVal = item.stock;
      if (storeVal) stockVal = item['stock_' + storeVal] || 0;
      const formatNumber = num => {
        if (num === null || num === undefined) return '';
        const number = parseFloat(num);
        return isNaN(number) ? num : number.toFixed(2).replace(/\.?0+$/, '');
      };

      const tr = document.createElement('tr');
      let photoCell = 'Нет фото';
      if (item.photoMini) {
        const mini = 'image_proxy.php?url=' + encodeURIComponent(item.photoMini);
        const full = 'image_proxy.php?url=' + encodeURIComponent(item.photoFull);
        photoCell = `<div class="image-container" onclick="openModal('${full}')">
            <img src="${mini}" alt="Фото" class="mini-img">
            <div class="zoom-icon"></div>
          </div>`;
      }

      tr.innerHTML = `
        <td>${i + 1}</td>
        <td>${item.articul}</td>
        <td style="text-align:center;">${photoCell}</td>
        <td>${item.name}</td>
        <td>${item.uom}</td>
        <td>${item.tip}</td>
        <td>${item.supplier}</td>
        <td>${formatNumber(item.mass)}</td>
        <td>${formatNumber(item.price)}</td>
        <td>${formatNumber(stockVal)}</td>
        <td>${formatNumber(item.volumeWeight)}</td>`;
      tr.addEventListener('click', e => {
        if (e.target.closest('.image-container')) return;
        openProductModal(item);
      });
      tbody.appendChild(tr);
    }
    index = end;
    if (index < data.length) {
      requestAnimationFrame(processChunk);
    } else if (btn) {
      setTimeout(() => btn.classList.remove('hover-effect'), 300);
    }
  }

  requestAnimationFrame(processChunk);
}

/**
 * Fill filter drop-downs with unique values.
 * @param {Array<Object>} data
 */
function fillFilters(data) {
  const tipSelect = document.getElementById('filterTip');
  const types = [...new Set(data.map(i => i.tip).filter(Boolean))];
  tipSelect.innerHTML = '<option value="">(Все)</option>';
  types.forEach(t => {
    const opt = document.createElement('option');
    opt.value = t;
    opt.textContent = t;
    tipSelect.appendChild(opt);
  });

  const countrySelect = document.getElementById('filterCountry');
  const countries = [...new Set(data.map(i => i.supplier).filter(Boolean))];
  countrySelect.innerHTML = '<option value="">(Все)</option>';
  countries.forEach(c => {
    const opt = document.createElement('option');
    opt.value = c;
    opt.textContent = c;
    countrySelect.appendChild(opt);
  });
}
