/**
 * Load column and row sorting rules from JSON files.
 * @returns {Promise<void>}
 */
let PRODUCT_ORDER = [];

function loadRules() {
  const rulesFile = window.__rulesFile || 'row_sort_rules.json';
  const colPromise  = fetch('column_rules.json').then(r => r.json());
  const sortPromise = fetch(rulesFile).then(r => r.json());
  const prodPromise = fetch('product_sort_rules.json')
    .then(r => r.json())
    .catch(() => ({}));

  return Promise.all([colPromise, sortPromise, prodPromise]).then(([cols, sort, prod]) => {
    COLUMN_RULES  = Array.isArray(cols) ? cols.filter(c => c.enabled !== false) : [];
    COUNTRY_ORDER = Array.isArray(sort.countryOrder) ? sort.countryOrder : [];
    TYPE_ORDER    = Array.isArray(sort.typeOrder) ? sort.typeOrder : [];
    TYPE_SORT     = sort.typeSort || 'alphabetical';
    PRODUCT_ORDER = Array.isArray(prod.productOrder) ? prod.productOrder : [];
    renderTableHeader();
  }).catch(err => {
    console.error('Failed to load rules', err);
  });
}

