/**
 * Entry point for page scripts.
 */
document.addEventListener('DOMContentLoaded', () => {
  loadData();
  document.getElementById('btnRefresh').addEventListener('click', () => {
    loadData();
  });

  document.getElementById('export-button').addEventListener('click', exportToExcel);

  ['filterArticul','filterName','filterMassMin','filterMassMax','filterPriceMin','filterPriceMax']
    .forEach(id => {
      document.getElementById(id).addEventListener('input', applyFilters);
    });

  ['filterTip','filterCountry','filterStore'].forEach(id => {
    document.getElementById(id).addEventListener('change', applyFilters);
  });

  document.getElementById('modalClose').addEventListener('click', closeModal);
  document.getElementById('imageModal').addEventListener('click', e => {
    if (e.target.id === 'imageModal') closeModal();
  });
  document.getElementById('productModalClose').addEventListener('click', closeProductModal);
});
