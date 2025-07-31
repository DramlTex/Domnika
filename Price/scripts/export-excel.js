/**
 * Export current data set to Excel using hidden form.
 */
function exportToExcel() {
  const allData = window.__productsData || [];
  if (!allData.length) {
    alert('Нет данных для экспорта.');
    return;
  }
  const jsonData = JSON.stringify(allData);
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = 'export.php';
  form.style.display = 'none';

  const input = document.createElement('input');
  input.type = 'hidden';
  input.name = 'jsonData';
  input.value = jsonData;

  form.appendChild(input);
  document.body.appendChild(form);
  form.submit();
  document.body.removeChild(form);
}
