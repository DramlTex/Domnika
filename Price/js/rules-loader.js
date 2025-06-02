/**
 * Load column and row sorting rules from JSON files.
 * @returns {Promise<void>}
 */
function loadRules() {
  const rulesFile = window.__rulesFile || 'row_sort_rules.json';
  const colPromise = fetch('column_rules.json').then(r => r.json());
  const sortPromise = fetch(rulesFile).then(r => r.json());
  return Promise.all([colPromise, sortPromise]).then(([cols, sort]) => {
    COLUMN_RULES = Array.isArray(cols) ? cols : [];
    COUNTRY_ORDER = Array.isArray(sort.countryOrder) ? sort.countryOrder : [];
    TYPE_ORDER = Array.isArray(sort.typeOrder) ? sort.typeOrder : [];
    renderTableHeader();
  }).catch(err => {
    console.error('Failed to load rules', err);
  });
}

