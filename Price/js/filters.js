/**
 * Apply filters to current data set and update table.
 */
function applyFilters() {
  const grouped = window.__groupedData || {};
  const allData = grouped[window.__currentGroup] || [];
  const valArticul = document.getElementById('filterArticul').value.trim().toLowerCase();
  const valName = document.getElementById('filterName').value.trim().toLowerCase();
  const valTip = document.getElementById('filterTip').value;
  const valCountry = document.getElementById('filterCountry').value;
  const massMin = parseFloat(document.getElementById('filterMassMin').value) || null;
  const massMax = parseFloat(document.getElementById('filterMassMax').value) || null;
  const priceMin = parseFloat(document.getElementById('filterPriceMin').value) || null;
  const priceMax = parseFloat(document.getElementById('filterPriceMax').value) || null;
  const storeVal = document.getElementById('filterStore').value;

  const filtered = allData.filter(item => {
    if (valArticul && !item.articul.toLowerCase().includes(valArticul)) return false;
    if (valName && !item.name.toLowerCase().includes(valName)) return false;
    if (valTip && item.tip !== valTip) return false;
    if (valCountry && item.supplier !== valCountry) return false;

    const mass = parseFloat(item.mass) || 0;
    if (massMin !== null && mass < massMin) return false;
    if (massMax !== null && mass > massMax) return false;

    const price = parseFloat(item.price) || 0;
    if (priceMin !== null && price < priceMin) return false;
    if (priceMax !== null && price > priceMax) return false;

    if (storeVal) {
      const storeStock = item['stock_' + storeVal] || 0;
      if (storeStock <= 0) return false;
    }
    return true;
  });

  fillTable(filtered);
}
