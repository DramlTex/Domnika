/**
 * Group products by category.
 * @param {Array<Object>} data
 * @returns {Object<string, Array<Object>>}
 */
function groupProductsByCategory(data) {
  const grouped = {};
  data.forEach(item => {
    const group = item.group || 'Остальное';
    if (!grouped[group]) grouped[group] = [];
    grouped[group].push(item);
  });
  return grouped;
}

/**
 * Create tab buttons for product groups.
 * @param {Object<string, Array<Object>>} groupedData
 */
function createTabs(groupedData) {
  const tabsContainer = document.getElementById('tabs');
  tabsContainer.innerHTML = '';

  const customOrder = [
    'Классические чаи',
    'Ароматизированный чай',
    'Травы и добавки',
    'Приправы',
    'По запросу'
  ];

  const allGroups = Object.keys(groupedData);

  const orderedGroups = [];
  customOrder.forEach(name => {
    if (allGroups.includes(name)) orderedGroups.push(name);
  });
  const otherGroups = allGroups.filter(g => !customOrder.includes(g));
  orderedGroups.push(...otherGroups);

  orderedGroups.forEach(groupName => {
    const tabButton = document.createElement('button');
    tabButton.textContent = groupName;
    tabButton.classList.add('tab-button');
    tabButton.addEventListener('click', () => {
      document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
      tabButton.classList.add('active');
      showTab(groupName, groupedData);
    });
    tabsContainer.appendChild(tabButton);
  });

  if (orderedGroups.length > 0) {
    tabsContainer.classList.add('visible');
    const firstTab = tabsContainer.querySelector('.tab-button');
    if (firstTab) {
      firstTab.classList.add('active');
      showTab(orderedGroups[0], groupedData);
    }
  }
}

/**
 * Show table for selected tab.
 * @param {string} groupName
 * @param {Object<string, Array<Object>>} groupedData
 */
function showTab(groupName, groupedData) {
  fillTable(groupedData[groupName]);
}
