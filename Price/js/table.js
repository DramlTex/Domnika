/**
 * Order of countries in the table. Add new countries at the end
 * of the array to control their position.
 * @type {string[]}
 */
const COUNTRY_ORDER = [
  'ИНДИЯ',
  'ЦЕЙЛОН',
  'ИРАН',
  'ВЬЕТНАМ',
  'КИТАЙ',
  'КЕНИЯ',
  'ЕГИПЕТ',
  'НИГЕРИЯ',
  'ЮЖНАЯ АФРИКА'
];

/**
 * Optional order of types within each country. Keep empty to sort
 * alphabetically. Add new types at the end of the array to override the
 * alphabetical order.
 * @type {string[]}
 */
const TYPE_ORDER = [];

/**
 * Sort array of products according to `COUNTRY_ORDER` and article.
 * @param {Array<Object>} data
 * @returns {Array<Object>} sorted copy of data
 */
function normalizeCountry(value) {
  return (value || '').trim().toUpperCase();
}

function sortByCountry(data) {
  const countryMap = COUNTRY_ORDER.reduce((acc, c, i) => {
    acc[normalizeCountry(c)] = i;
    return acc;
  }, {});
  const typeMap = TYPE_ORDER.reduce((acc, t, i) => {
    acc[t] = i;
    return acc;
  }, {});
  return data.slice().sort((a, b) => {
    const aCountry = normalizeCountry(a.supplier);
    const bCountry = normalizeCountry(b.supplier);
    const ai = countryMap.hasOwnProperty(aCountry) ? countryMap[aCountry] : COUNTRY_ORDER.length;
    const bi = countryMap.hasOwnProperty(bCountry) ? countryMap[bCountry] : COUNTRY_ORDER.length;
    if (ai !== bi) return ai - bi;

    const aType = a.tip || '';
    const bType = b.tip || '';
    const ati = typeMap.hasOwnProperty(aType) ? typeMap[aType] : TYPE_ORDER.length;
    const bti = typeMap.hasOwnProperty(bType) ? typeMap[bType] : TYPE_ORDER.length;
    if (ati !== bti) return ati - bti;

    if (aType !== bType) return aType.localeCompare(bType);

    return a.articul.localeCompare(b.articul);
  });
}

/**
 * Return array of country names ordered according to COUNTRY_ORDER.
 * @param {string[]} list
 * @returns {string[]}
 */
function orderCountriesList(list) {
  const map = COUNTRY_ORDER.reduce((acc, c, i) => {
    acc[normalizeCountry(c)] = i;
    return acc;
  }, {});
  return list.slice().sort((a, b) => {
    const ai = map.hasOwnProperty(normalizeCountry(a)) ? map[normalizeCountry(a)] : COUNTRY_ORDER.length;
    const bi = map.hasOwnProperty(normalizeCountry(b)) ? map[normalizeCountry(b)] : COUNTRY_ORDER.length;
    return ai - bi;
  });
}

/**
 * Order list of types using TYPE_ORDER when provided, otherwise alphabetically.
 * @param {string[]} list
 * @returns {string[]}
 */
function orderTypesList(list) {
  const map = TYPE_ORDER.reduce((acc, t, i) => {
    acc[t] = i;
    return acc;
  }, {});
  return list.slice().sort((a, b) => {
    const ai = map.hasOwnProperty(a) ? map[a] : TYPE_ORDER.length;
    const bi = map.hasOwnProperty(b) ? map[b] : TYPE_ORDER.length;
    if (ai !== bi) return ai - bi;
    return a.localeCompare(b);
  });
}

/*
 * Пример пользовательской сортировки стран. Раскомментируйте функцию
 * и замените вызов `sortByCountry` внутри `fillTable`, передав свой
 * список стран в нужном порядке.
 */
// function sortByCustomCountryOrder(data, order) {
//   const map = order.reduce((acc, c, i) => {
//     acc[c] = i;
//     return acc;
//   }, {});
//   return data.slice().sort((a, b) => {
//     const ai = map.hasOwnProperty(a.supplier) ? map[a.supplier] : order.length;
//     const bi = map.hasOwnProperty(b.supplier) ? map[b.supplier] : order.length;
//     if (ai !== bi) return ai - bi;
//     return a.articul.localeCompare(b.articul);
//   });
// }

/**
 * Render product table in chunks for performance.
 * @param {Array<Object>} data
 */
function fillTable(data) {
  const sorted = sortByCountry(data);
  const tbody = document.querySelector('#priceList tbody');
  tbody.innerHTML = '';
  const btn = document.getElementById('btnRefresh');
  if (btn) btn.classList.add('hover-effect');

  let index = 0;
  const chunkSize = 50;
  let rowNumber = 1;
  let currentCountry = null;
  let currentType = null;

  function processChunk() {
    const end = Math.min(index + chunkSize, sorted.length);
    for (let i = index; i < end; i++) {
      const item = sorted[i];
      const storeVal = document.getElementById('filterStore').value;
      let stockVal = item.stock;
      if (storeVal) stockVal = item['stock_' + storeVal] || 0;
      const formatNumber = num => {
        if (num === null || num === undefined) return '';
        const number = parseFloat(num);
        return isNaN(number) ? num : number.toFixed(2).replace(/\.?0+$/, '');
      };

      if (currentCountry !== item.supplier) {
        const header = document.createElement('tr');
        header.className = 'country-row';
        header.innerHTML = `<td colspan="11">${item.supplier}</td>`;
        tbody.appendChild(header);
        currentCountry = item.supplier;
        currentType = null;
      }

      if (currentType !== (item.tip || '')) {
        const typeHeader = document.createElement('tr');
        typeHeader.className = 'type-row';
        typeHeader.innerHTML = `<td colspan="11">${item.tip || ''}</td>`;
        tbody.appendChild(typeHeader);
        currentType = item.tip || '';
      }

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
        <td>${rowNumber}</td>
        <td>${item.articul}</td>
        <td class="photo-cell">${photoCell}</td>
        <td>${item.name}</td>
        <td>${item.uom}</td>
        <td class="type-cell">${item.tip}</td>
        <td class="country-cell">${item.supplier}</td>
        <td>${formatNumber(item.mass)}</td>
        <td>${formatNumber(item.price)}</td>
        <td>${formatNumber(stockVal)}</td>
        <td>${formatNumber(item.volumeWeight)}</td>`;
      tr.addEventListener('click', e => {
        if (e.target.closest('.image-container')) return;
        openProductModal(item);
      });
      tbody.appendChild(tr);
      rowNumber++;
    }
    index = end;
    if (index < sorted.length) {
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
  const orderedTypes = orderTypesList(types);
  tipSelect.innerHTML = '<option value="">(Все)</option>';
  orderedTypes.forEach(t => {
    const opt = document.createElement('option');
    opt.value = t;
    opt.textContent = t;
    tipSelect.appendChild(opt);
  });

  const countrySelect = document.getElementById('filterCountry');
  const countries = [...new Set(data.map(i => i.supplier).filter(Boolean))];
  const ordered = orderCountriesList(countries);
  countrySelect.innerHTML = '<option value="">(Все)</option>';
  ordered.forEach(c => {
    const opt = document.createElement('option');
    opt.value = c;
    opt.textContent = c;
    countrySelect.appendChild(opt);
  });
}
