/**
 * Порядок стран и типов загружается из JSON-файла `casa/row_sort_rules.json`.
 * Эти переменные заполняются в `rules-loader.js` и используются
 * для сортировки и фильтрации таблицы.
 * @type {string[]}
 */
let COUNTRY_RULES = [];
let COUNTRY_MAP = {};
let TYPE_MAP = {};
let PRODUCT_MAP = {};
let GLOBAL_TYPE_ORDER = [];

/**
 * Описание колонок (порядок и заголовки) также поступает из
 * JSON-файла `casa/column_rules.json` через `rules-loader.js`.
 * @type {Array<Object>}
 */
let COLUMN_RULES = [];

/**
 * Currently attached row click handler.
 * @type {?Function}
 */
let ROW_CLICK_HANDLER = null;

/**
 * Groups that should be displayed even when stock is zero.
 * @type {string[]}
 */
const ALWAYS_SHOW_GROUPS = [
  'Ароматизированный чай',
  'Приправы'
];

/**
 * Sort array of products according to `COUNTRY_ORDER` and article.
 * @param {Array<Object>} data
 * @returns {Array<Object>} sorted copy of data
 */
function normalizeCountry(value) {
  return (value || '').trim().toUpperCase();
}

function normalizeType(value) {
  return (value || '').trim().toUpperCase();
}

function buildSortMaps() {
  COUNTRY_MAP = {};
  TYPE_MAP = {};
  PRODUCT_MAP = {};
  GLOBAL_TYPE_ORDER = [];
  COUNTRY_RULES.forEach((c, ci) => {
    const cName = c.name || '';
    const cNorm = normalizeCountry(cName);
    COUNTRY_MAP[cNorm] = ci;
    const types = Array.isArray(c.types) ? c.types : [];
    TYPE_MAP[cNorm] = {};
    types.forEach((t, ti) => {
      const tName = t.name || '';
      const tNorm = normalizeType(tName);
      TYPE_MAP[cNorm][tNorm] = ti;
      if (!GLOBAL_TYPE_ORDER.some(x => normalizeType(x) === tNorm)) {
        GLOBAL_TYPE_ORDER.push(tName);
      }
      const prods = Array.isArray(t.products) ? t.products : [];
      prods.forEach((p, pi) => {
        let id = typeof p === 'string' ? p : p.id;
        if (id) {
          PRODUCT_MAP[id] = { country: ci, type: ti, index: pi };
        }
      });
    });
  });
}

function sortByCountry(data) {
  return data.slice().sort((a, b) => {
    const aProd = PRODUCT_MAP[a.articul];
    const bProd = PRODUCT_MAP[b.articul];
    if (aProd || bProd) {
      if (!aProd) return 1;
      if (!bProd) return -1;
      if (aProd.country !== bProd.country) return aProd.country - bProd.country;
      if (aProd.type !== bProd.type) return aProd.type - bProd.type;
      if (aProd.index !== bProd.index) return aProd.index - bProd.index;
    }

    const aCountry = normalizeCountry(a.supplier);
    const bCountry = normalizeCountry(b.supplier);
    const aidx = Object.prototype.hasOwnProperty.call(COUNTRY_MAP, aCountry) ? COUNTRY_MAP[aCountry] : COUNTRY_RULES.length;
    const bidx = Object.prototype.hasOwnProperty.call(COUNTRY_MAP, bCountry) ? COUNTRY_MAP[bCountry] : COUNTRY_RULES.length;
    if (aidx !== bidx) return aidx - bidx;

    const aType = normalizeType(a.tip);
    const bType = normalizeType(b.tip);
    const aTypeMap = TYPE_MAP[aCountry] || {};
    const bTypeMap = TYPE_MAP[bCountry] || {};
    const ati = Object.prototype.hasOwnProperty.call(aTypeMap, aType) ? aTypeMap[aType] : Object.keys(aTypeMap).length;
    const bti = Object.prototype.hasOwnProperty.call(bTypeMap, bType) ? bTypeMap[bType] : Object.keys(bTypeMap).length;
    if (ati !== bti) return ati - bti;

    if (aType !== bType) return aType.localeCompare(bType);

    return a.name.localeCompare(b.name);
  });
}

/**
 * Return array of country names ordered according to COUNTRY_ORDER.
 * @param {string[]} list
 * @returns {string[]}
 */
function orderCountriesList(list) {
  return list.slice().sort((a, b) => {
    const ai = Object.prototype.hasOwnProperty.call(COUNTRY_MAP, normalizeCountry(a)) ? COUNTRY_MAP[normalizeCountry(a)] : COUNTRY_RULES.length;
    const bi = Object.prototype.hasOwnProperty.call(COUNTRY_MAP, normalizeCountry(b)) ? COUNTRY_MAP[normalizeCountry(b)] : COUNTRY_RULES.length;
    return ai - bi;
  });
}

/**
 * Order list of types using TYPE_ORDER when provided, otherwise alphabetically.
 * @param {string[]} list
 * @returns {string[]}
 */
function orderTypesList(list) {
  const map = GLOBAL_TYPE_ORDER.reduce((acc, t, i) => {
    acc[normalizeType(t)] = i;
    return acc;
  }, {});
  return list.slice().sort((a, b) => {
    const aNorm = normalizeType(a);
    const bNorm = normalizeType(b);
    const ai = Object.prototype.hasOwnProperty.call(map, aNorm) ? map[aNorm] : GLOBAL_TYPE_ORDER.length;
    const bi = Object.prototype.hasOwnProperty.call(map, bNorm) ? map[bNorm] : GLOBAL_TYPE_ORDER.length;
    if (ai !== bi) return ai - bi;
    return aNorm.localeCompare(bNorm);
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
 * Render table header according to COLUMN_RULES.
 */
function renderTableHeader() {
  if (!COLUMN_RULES.length) return;
  const headRow = document.querySelector('#priceList thead tr');
  if (!headRow) return;
  headRow.innerHTML = COLUMN_RULES.map(col => {
    const cls = col.class ? ` class="${col.class}"` : '';
    return `<th${cls}>${col.title}</th>`;
  }).join('');
}

/**
 * Render product table in chunks for performance.
 * @param {Array<Object>} data
 */
function fillTable(data) {
  const sorted = sortByCountry(data);
  const tbody = document.querySelector('#priceList tbody');
  tbody.innerHTML = '';
  const handleRowClick = e => {
    if (e.target.closest('.image-container')) return;
    const row = e.target.closest('tr[data-index]');
    if (!row) return;
    const idx = parseInt(row.dataset.index, 10);
    const item = sorted[idx];
    if (item) openProductModal(item);
  };

  if (ROW_CLICK_HANDLER) {
    tbody.removeEventListener('click', ROW_CLICK_HANDLER);
  }
  ROW_CLICK_HANDLER = handleRowClick;
  tbody.addEventListener('click', ROW_CLICK_HANDLER);

  const btn = document.getElementById('btnRefresh');
  if (btn) btn.classList.add('hover-effect');

  let index = 0;
  const chunkSize = 50;
  let rowNumber = 1;
  let currentCountry = null;
  let currentType = null; // normalized type name

  const colSpan = COLUMN_RULES.length || 11;

  function processChunk() {
    const end = Math.min(index + chunkSize, sorted.length);
    for (let i = index; i < end; i++) {
      const item = sorted[i];
      const storeVal = document.getElementById('filterStore').value;
      let stockVal = item.stock;
      if (storeVal) {
        const val = item['stock_' + storeVal];
        stockVal = val === undefined ? 0 : val;
      }
      const formatNumber = num => {
        if (num === null || num === undefined) return '';
        const number = parseFloat(num);
        return isNaN(number) ? num : number.toFixed(2).replace(/\.?0+$/, '');
      };
      const formatStock = num => {
        if (num === null || num === undefined) return '';
        const number = parseFloat(num);
        if (isNaN(number)) return num;
        return Math.floor(number).toString();
      };

      if (!ALWAYS_SHOW_GROUPS.includes(item.group) && Math.floor(parseFloat(stockVal)) <= 0) {
        continue;
      }

      if (currentCountry !== item.supplier) {
        const header = document.createElement('tr');
        header.className = 'country-row';
        header.innerHTML = `<td colspan="${colSpan}">${item.supplier}</td>`;
        tbody.appendChild(header);
        currentCountry = item.supplier;
        currentType = null;
      }

      const itemTypeNorm = normalizeType(item.tip);
      if (currentType !== itemTypeNorm) {
        const typeHeader = document.createElement('tr');
        typeHeader.className = 'type-row';
        typeHeader.innerHTML = `<td colspan="${colSpan}">${item.tip || ''}</td>`;
        tbody.appendChild(typeHeader);
        currentType = itemTypeNorm;
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

      const cellsHtml = COLUMN_RULES.map(col => {
        switch (col.id) {
          case 'num':
            return `<td class="num-cell">${rowNumber}</td>`;
          case 'articul':
            return `<td>${item.articul}</td>`;
          case 'photo':
            return `<td class="photo-cell">${photoCell}</td>`;
          case 'name':
            return `<td class="name-cell">${item.name}</td>`;
          case 'uom':
            return `<td>${item.uom}</td>`;
          case 'tip':
            return `<td class="type-cell">${item.tip}</td>`;
          case 'supplier':
            return `<td class="country-cell">${item.supplier}</td>`;
          case 'mass':
            return `<td>${formatNumber(item.mass)}</td>`;
          case 'price':
            return `<td>${formatNumber(item.price)}</td>`;
          case 'stock':
            return `<td>${formatStock(stockVal)}</td>`;
          case 'volumeWeight':
            return `<td>${formatNumber(item.volumeWeight)}</td>`;
          default:
            return `<td>${item[col.id] || ''}</td>`;
        }
      }).join('');

      tr.innerHTML = cellsHtml;
      tr.dataset.index = i;
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

  const typeMap = new Map();
  data.forEach(i => {
    if (!i.tip) return;
    const norm = normalizeType(i.tip);
    if (!typeMap.has(norm)) typeMap.set(norm, i.tip);
  });
  const orderedTypes = orderTypesList([...typeMap.keys()]);

  tipSelect.innerHTML = '<option value="">(Все)</option>';
  orderedTypes.forEach(t => {
    const opt = document.createElement('option');
    opt.value = t;
    opt.textContent = typeMap.get(t) || t;
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
