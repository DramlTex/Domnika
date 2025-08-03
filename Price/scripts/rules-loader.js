/**
 * Load column and row sorting rules for a specific group.
 * @param {string} groupName
 * @returns {Promise<void>}
 */

function groupToSlug(name) {
  switch (name) {
    case 'Ароматизированный чай': return 'aroma';
    case 'Травы и добавки': return 'herbs';
    case 'Приправы': return 'spices';
    case 'Классические чаи':
    default: return 'classic';
  }
}

function fetchJson(path, def) {
  return fetch(path)
    .then(r => (r.ok ? r.json() : def))
    .catch(() => def);
}

function loadRules(groupName) {
  const slug = groupToSlug(groupName || 'Классические чаи');
  const colPromise  = fetchJson(`casa/column_rules_${slug}.json`, []);
  const sortPromise = fetchJson(`casa/row_sort_rules_${slug}.json`, {});

  return Promise.all([colPromise, sortPromise]).then(([cols, sort]) => {
    COLUMN_RULES = Array.isArray(cols) ? cols.filter(c => c.enabled !== false) : [];
    TYPE_SORT    = sort.typeSort || 'alphabetical';

    const order = Array.isArray(sort.order) ? sort.order : [];
    COUNTRY_ORDER = order.map(o => o.country);
    TYPE_ORDER_MAP = {};
    PRODUCT_ORDER_MAP = {};
    TYPE_ORDER = [];

    const normCountry = v => (v || '').trim().toUpperCase();
    const normType = v => (v || '').trim().toUpperCase();

    order.forEach(c => {
      const cNorm = normCountry(c.country);
      const types = Array.isArray(c.types) ? c.types : [];
      TYPE_ORDER_MAP[cNorm] = types.map(t => t.type);
      types.forEach(t => {
        if (!TYPE_ORDER.includes(t.type)) TYPE_ORDER.push(t.type);
        const key = cNorm + '|' + normType(t.type);
        const prodArr = Array.isArray(t.products) ? t.products : [];
        PRODUCT_ORDER_MAP[key] = prodArr.map(p => {
          if (typeof p === 'string') return p;
          if (p && typeof p.id === 'string') return p.id;
          return null;
        }).filter(id => id);
      });
    });

    renderTableHeader();
  }).catch(err => {
    console.error('Failed to load rules', err);
  });
}

