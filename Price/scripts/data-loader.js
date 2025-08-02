/**
 * Load product data from server and initialize the table.
 * @returns {Promise<void>}
 */
function loadData() {
  document.getElementById('loader').style.display = 'block';
  document.querySelector('.table-wrapper').style.display = 'none';
  document.getElementById('tabs').style.display = 'none';
  fetch('data.php')
    .then(response => response.json())
    .then(json => {
      if (json && json.rows) {
        let rows = json.rows;
        rows = filterByUserFolders(rows);
        window.__productsData = rows;
        const groupedData = groupProductsByCategory(rows);
        window.__groupedData = groupedData;
        createTabs(groupedData);
        fillFilters(rows);
        document.getElementById('loader').style.display = 'none';
        document.querySelector('.table-wrapper').style.display = '';
        document.getElementById('tabs').style.display = '';
      } else {
        console.error('Некорректный ответ:', json);
        document.getElementById('loader').style.display = 'none';
      }
    })
    .catch(err => {
      console.error('Ошибка при запросе:', err);
      document.getElementById('loader').style.display = 'none';
    });
}

/**
 * Filter rows based on user folders.
 * @param {Array<Object>} rows
 * @returns {Array<Object>} filtered rows
 */
function filterByUserFolders(rows) {
  if (!window.__userFolders || !window.__userFolders.length) return rows;
  return rows.filter(item => {
    if (!item.pathName) return false;
    return window.__userFolders.some(folder => {
      const folderName = folder.name || '';
      if (item.pathName.startsWith(folderName + '/')) return true;
      if (item.pathName === folderName) return true;
      if (item.pathName.includes('/' + folderName + '/')) return true;
      if (item.pathName.endsWith('/' + folderName)) return true;
      return false;
    });
  });
}

/**
 * Helper to load image and convert to Base64.
 * @param {string} url
 * @returns {Promise<string>} Base64 string
 */
async function loadImageAsBase64(url) {
  const response = await fetch(url);
  const blob = await response.blob();
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onloadend = () => {
      const base64data = reader.result.split(',')[1];
      resolve(base64data);
    };
    reader.onerror = reject;
    reader.readAsDataURL(blob);
  });
}
