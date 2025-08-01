/**
 * Load column and row sorting rules from JSON files.
 * @returns {Promise<void>}
 */

function loadRules() {
  const rulesFile = window.__rulesFile || 'casa/row_sort_rules.json';
  const colPromise  = fetch('casa/column_rules.json').then(r => r.json());
  const sortPromise = fetch(rulesFile).then(r => r.json());
  const prodPromise = fetch('casa/product_sort_rules.json')
    .then(r => r.json())
    .catch(() => ({}));

  return Promise.all([colPromise, sortPromise, prodPromise]).then(([cols, sort, prod]) => {
    COLUMN_RULES  = Array.isArray(cols) ? cols.filter(c => c.enabled !== false) : [];
    COUNTRY_ORDER = Array.isArray(sort.countryOrder) ? sort.countryOrder : [];
    TYPE_ORDER    = Array.isArray(sort.typeOrder) ? sort.typeOrder : [];
    TYPE_SORT     = sort.typeSort || 'alphabetical';
    if (Array.isArray(prod.productOrder)) {
      PRODUCT_ORDER = prod.productOrder.map(item => {
        if (typeof item === 'string') return item;
        if (item && typeof item.id === 'string') return item.id;
        return null;
      }).filter(id => id);
    } else {
      PRODUCT_ORDER = [];
    }
    renderTableHeader();
  }).catch(err => {
    console.error('Failed to load rules', err);
  });
}

