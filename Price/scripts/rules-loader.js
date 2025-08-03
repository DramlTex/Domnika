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
      COLUMN_RULES   = Array.isArray(cols) ? cols.filter(c => c.enabled !== false) : [];
      COUNTRY_RULES  = Array.isArray(sort.countries) ? sort.countries : [];
      buildSortMaps();
      renderTableHeader();
    }).catch(err => {
      console.error('Failed to load rules', err);
    });
  }

