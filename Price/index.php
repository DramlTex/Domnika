<?php
ini_set('session.save_path', __DIR__ . '/sessions');
session_start();

// Пример проверки авторизации
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}
$username = $_SESSION['user']['login'];
$userFolders = $_SESSION['user']['productfolders'] ?? [];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<link rel="icon" type="image/x-icon" href="favicon.ico">
  <meta charset="UTF-8">
  <title>Прайс лист ассортимента</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <link rel="stylesheet" type="text/css" href="styles_main.css">
</head>

<body>

<!-- БАННЕР / ШАПКА -->
<div class="banner">
  <img class="logo white-svg"
       src="http://85.193.91.150/Domnika/price/811140.svg"
       alt="JFK Trading Group">
  <div class="left-text">
    <h1>ООО «Джей Эф Кей»</h1>
    <p>Полный цикл производства чая</p>
  </div>
  <div class="divider"></div>
  <div class="banner-right">
    <p><i class="bi bi-telephone-fill"></i><strong>Тел.:</strong>
       <a href="tel:+74950232060">+7 (495) 023-20-60</a>
    </p>
    <p><i class="bi bi-envelope-fill"></i><strong>Email:</strong>
       <a href="mailto:jfkrus@jfk.in">jfkrus@jfk.in</a>
    </p>
    <p><i class="bi bi-globe"></i><strong>Сайт:</strong>
       <a href="http://jfkrus.ru" target="_blank">jfkrus.ru</a>
    </p>
    <p><i class="bi bi-geo-alt-fill"></i><strong>Адрес офиса:</strong>
       <a href="https://yandex.ru/maps/?text=Москва,Осенний+бульвар,23"
          target="_blank">
         г. Москва, Осенний бульвар, 23
       </a>
    </p>
    <h3>Адреса наших складов:</h3>
    <ul>
      <li>
        <a href="https://yandex.ru/maps/?text=127410+Алтуфьево%2C+Москва+г%2C+Алтуфьевское+ш%2C+д.+37%2C+стр.+1"
           target="_blank">
           127410 Алтуфьево, Москва г, Алтуфьевское ш, д. 37, стр. 1
        </a>
      </li>
      <li>
        <a href="https://yandex.ru/maps/?text=142450%2C+МО%2C+г.+Старая+Купавна%2C+ул.+Дорожная+12%2C+стр.+2"
           target="_blank">
           142450, МО, г. Старая Купавна, ул. Дорожная 12, стр. 2
        </a>
      </li>
      <li>
        <a href="https://yandex.ru/maps/?text=143200%2C+МО%2C+г.+Можайск%2C+ул.+Мира%2C+д.+93%2C+к.б"
           target="_blank">
           143200, МО, г. Можайск, ул. Мира, д. 93, к.б
        </a>
      </li>
    </ul>
  </div>
</div>

<!-- ИНФОРМАЦИЯ О ПОЛЬЗОВАТЕЛЕ -->
<p style="margin: 10px 20px;">
  Вы вошли как: <strong><?php echo htmlspecialchars($username); ?></strong>
  <a href="logout.php" class="logout-link">Выйти</a>
</p>

<div class="filters-container">
  <div class="filters-grid">
    
    <div class="filter-group">
      <label for="filterArticul">Артикул</label>
      <input type="text" id="filterArticul">
    </div>

    <div class="filter-group">
      <label for="filterName">Название</label>
      <input type="text" id="filterName">
    </div>

    <div class="filter-group">
      <label for="filterTip">Тип</label>
      <select id="filterTip">
        <option value="">(Все)</option>
      </select>
    </div>

    <div class="filter-group">
      <label for="filterCountry">Страна</label>
      <select id="filterCountry">
        <option value="">(Все)</option>
      </select>
    </div>

    <div class="filter-group">
      <label for="filterMassMin">Масса (от–до)</label>
      <div class="range-group">
        <input type="number" step="0.01" id="filterMassMin" placeholder="От">
        <span>–</span>
        <input type="number" step="0.01" id="filterMassMax" placeholder="До">
      </div>
    </div>

    <div class="filter-group">
      <label for="filterPriceMin">Цена (от–до)</label>
      <div class="range-group">
        <input type="number" step="0.01" id="filterPriceMin" placeholder="От">
        <span>–</span>
        <input type="number" step="0.01" id="filterPriceMax" placeholder="До">
      </div>
    </div>

    <div class="filter-group">
      <label for="filterStore">Склад</label>
      <select id="filterStore">
        <option value="">(Все)</option>
        <option value="store1">Алтуфьево</option>
        <option value="store2">Ивантеевка</option>
        <option value="store3">Купавна</option>
        <option value="store4">Можайск</option>
      </select>
    </div>

    <div class="filter-group btns">
      <button class="btn primary" id="btnRefresh">Обновить</button>
      <button class="btn" id="export-button">Экспорт в Excel</button>
    </div>

  </div>
</div>

<div id="loader" class="loader-container">
  <div class="loader-spinner"></div>
  <p>Загрузка данных...</p>
</div>

<!-- ОСНОВНАЯ ТАБЛИЦА -->
<div class="table-wrapper">
  <table id="priceList">
    <thead>
      <tr>
      <th>№</th>
      <th>Артикул</th>
      <th>Фото</th>
      <th>Название</th>
      <th>Стандарт</th>
      <th>Тип</th>
      <th>Страна</th>
      <th>Вес тарного места</th>
      <th>Цена</th>
      <th>Наличие в кг</th>
      <th>Объём тарного места</th>
      <!-- Добавляем новые колонки -->
      </tr>
    </thead>
    <tbody>
    </tbody>
  </table>
</div>

<!-- ВКЛАДКИ (ТАБЫ) -->
<div id="tabs" class="tabs-container">
</div>

<!-- МОДАЛЬНОЕ ОКНО: УВЕЛИЧЕННОЕ ФОТО -->
<div id="imageModal" class="modal">
  <span class="close-modal" id="modalClose">&times;</span>
  <img class="modal-content" id="modalImg" src="" alt="Фото">
</div>

<!-- МОДАЛЬНОЕ ОКНО: ДЕТАЛИ ТОВАРА (ПРАВАЯ ПАНЕЛЬ) -->
<div id="productModal">
  <div class="modal-content" id="productModalContent">
    <span class="close-modal" id="productModalClose">&times;</span>
    <div class="modal-body">
      <div class="modal-left">
        <img id="productModalImg"
             src=""
             alt="Полное фото">
      </div>
      <div class="modal-right">
        <h2 id="productModalName"></h2>
        <p><strong>Описание:</strong> <span id="productModalDescription"></span></p>
        <p><strong>Артикул:</strong> <span id="productModalArticul"></span></p>
        <p><strong>Тип:</strong> <span id="productModalTip"></span></p>
        <p><strong>Поставщик:</strong> <span id="productModalSupplier"></span></p>
        <p><strong>Вес тарного места:</strong> <span id="productModalMass"></span></p>
        <p><strong>Цена:</strong> <span id="productModalPrice"></span></p>
        <p><strong>Общий остаток:</strong> <span id="productModalStock"></span></p>
        <p><strong>Алтуфьево:</strong> <span id="productModalStock1"></span></p>
        <p><strong>Ивантеевка:</strong> <span id="productModalStock2"></span></p>
        <p><strong>Купавна:</strong> <span id="productModalStock3"></span></p>
        <p><strong>Можайск:</strong> <span id="productModalStock4"></span></p>
        <p><strong>Объём тарного места:</strong> <span id="productModalVolume"></span></p>
      </div>
    </div>
  </div>
</div>

<script>
window.__userFolders = <?php echo json_encode($userFolders, JSON_UNESCAPED_UNICODE); ?>;

// =====================================
// 1) ЗАГРУЗКА ДАННЫХ
// =====================================
function loadData() {
  document.getElementById('loader').style.display = 'block';
  document.querySelector('.table-wrapper').style.display = 'none';
  document.getElementById('tabs').style.display = 'none';
  fetch('http://85.193.91.150/Domnika/price/data.php')
    .then(response => response.json())
    .then(json => {
      if (json && json.rows) {
        let rows = json.rows;
        rows = filterByUserFolders(rows);
        window.__productsData = rows;
        const groupedData = groupProductsByCategory(rows);
        createTabs(groupedData);
        fillTable(groupedData['Остальное'] || []);
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

// =====================================
// 2) ФИЛЬТРАЦИЯ ПО ПАПКАМ
// =====================================
function filterByUserFolders(rows) {
  if (!window.__userFolders || !window.__userFolders.length) return rows;

  return rows.filter(item => {
    if (!item.pathName) return false;
    return window.__userFolders.some(folder => {
      const folderName = folder.name || '';
      if (item.pathName.startsWith(folderName + "/")) return true;
      if (item.pathName === folderName) return true;
      if (item.pathName.includes("/" + folderName + "/")) return true;
      if (item.pathName.endsWith("/" + folderName)) return true;
      return false;
    });
  });
}

// =====================================
// 3) ГРУППИРОВКА ПО ПОЛЮ "group"
// =====================================
function groupProductsByCategory(data) {
  const grouped = {};
  data.forEach(item => {
    const group = item.group || 'Остальное';
    if (!grouped[group]) grouped[group] = [];
    grouped[group].push(item);
  });
  return grouped;
}

// =====================================
// 4) СОЗДАНИЕ ВКЛАДОК
// =====================================
function createTabs(groupedData) {
  const tabsContainer = document.getElementById('tabs');
  tabsContainer.innerHTML = '';

  // Массив групп в нужном порядке
  const customOrder = [
    "Классические чаи",
    "Ароматизированный чай",
    "Травы и добавки",
    "Приправы",
    "По запросу"
  ];

  // Все фактические группы, которые у нас есть
  const allGroups = Object.keys(groupedData);

  // 1. Берём только те группы из customOrder, которые реально присутствуют
  const orderedGroups = [];
  customOrder.forEach(name => {
    if (allGroups.includes(name)) {
      orderedGroups.push(name);
    }
  });

  // 2. Остальные группы (не входящие в customOrder)
  const otherGroups = allGroups.filter(g => !customOrder.includes(g));
  // Если нужно, можно их тоже отсортировать:
  // otherGroups.sort();

  // 3. Добавляем "прочие" группы в конец
  orderedGroups.push(...otherGroups);

  // 4. Создаём вкладки в порядке orderedGroups
  orderedGroups.forEach(groupName => {
    const tabButton = document.createElement('button');
    tabButton.textContent = groupName;
    tabButton.classList.add('tab-button');

    tabButton.addEventListener('click', () => {
      // Снимаем "active" со всех вкладок
      document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
      // Подсвечиваем текущую вкладку
      tabButton.classList.add('active');
      // Отображаем таблицу для выбранной группы
      showTab(groupName, groupedData);
    });

    tabsContainer.appendChild(tabButton);
  });

  // Если у нас есть хотя бы одна группа, показываем первую вкладку сразу
  if (orderedGroups.length > 0) {
    tabsContainer.classList.add('visible');
    const firstTab = tabsContainer.querySelector('.tab-button');
    if (firstTab) {
      firstTab.classList.add('active');
      showTab(orderedGroups[0], groupedData);
    }
  }
}


function showTab(groupName, groupedData) {
  fillTable(groupedData[groupName]);
}

// =====================================
// 5) ОТОБРАЖЕНИЕ ТАБЛИЦЫ
// =====================================
function fillTable(data) {
  const tbody = document.querySelector('#priceList tbody');
  tbody.innerHTML = '';
  const btn = document.getElementById('btnRefresh');
  if (btn) btn.classList.add('hover-effect');

  let index = 0;
  const chunkSize = 50;

  function processChunk() {
    const end = Math.min(index + chunkSize, data.length);
    for (let i = index; i < end; i++) {
      const item = data[i];
      const storeVal = document.getElementById('filterStore').value;
      let stockVal = item.stock;
      if (storeVal) stockVal = item['stock_' + storeVal] || 0;
      const formatNumber = (num) => {
        if (num === null || num === undefined) return '';
        const number = parseFloat(num);
        return isNaN(number) ? num : number.toFixed(2).replace(/\.?0+$/, '');
      };

      const tr = document.createElement('tr');
      let photoCell = 'Нет фото';
      if (item.photoMini) {
        const mini = 'image_proxy.php?url=' + encodeURIComponent(item.photoMini);
        const full = 'image_proxy.php?url=' + encodeURIComponent(item.photoFull);
        photoCell = `
          <div class="image-container"
               onclick="openModal('${full}')">
            <img src="${mini}" alt="Фото" class="mini-img">
            <div class="zoom-icon"></div>
          </div>
        `;
      }

      tr.innerHTML = `
        <td>${i + 1}</td>
        <td>${item.articul}</td>
        <td style="text-align:center;">${photoCell}</td>
        <td>${item.name}</td>
        <td>${item.uom}</td>
        <td>${item.tip}</td>
        <td>${item.supplier}</td>
        <td>${formatNumber(item.mass)}</td>
        <td>${formatNumber(item.price)}</td>
        <td>${formatNumber(stockVal)}</td>
        <td>${formatNumber(item.volumeWeight)}</td>
      `;
      tr.addEventListener('click', (e) => {
        if (e.target.closest('.image-container')) return;
        openProductModal(item);
      });

      tbody.appendChild(tr);
    }
    index = end;
    if (index < data.length) {
      requestAnimationFrame(processChunk);
    } else {
      if (btn) {
        setTimeout(() => btn.classList.remove('hover-effect'), 300);
      }
    }
  }

  requestAnimationFrame(processChunk);
}

// =====================================
// 6) ЗАПОЛНЕНИЕ ФИЛЬТРОВ
// =====================================
function fillFilters(data) {
  const tipSelect = document.getElementById('filterTip');
  const types = [...new Set(data.map(i => i.tip).filter(Boolean))];
  tipSelect.innerHTML = '<option value="">(Все)</option>';
  types.forEach(t => {
    const opt = document.createElement('option');
    opt.value = t;
    opt.textContent = t;
    tipSelect.appendChild(opt);
  });

  const countrySelect = document.getElementById('filterCountry');
  const countries = [...new Set(data.map(i => i.supplier).filter(Boolean))];
  countrySelect.innerHTML = '<option value="">(Все)</option>';
  countries.forEach(c => {
    const opt = document.createElement('option');
    opt.value = c;
    opt.textContent = c;
    countrySelect.appendChild(opt);
  });
}

// =====================================
// 7) ПРИМЕНЕНИЕ ФИЛЬТРОВ
// =====================================
function applyFilters() {
  const allData = window.__productsData || [];
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

// =====================================
// 8) МОДАЛЬ ОКНО ДЛЯ ФОТО
// =====================================
function openModal(fullUrl) {
  const modal = document.getElementById('imageModal');
  const modalImg = document.getElementById('modalImg');
  modalImg.src = fullUrl;
  modal.style.display = 'block';
}
function closeModal() {
  document.getElementById('imageModal').style.display = 'none';
}

// =====================================
// 9) ПАНЕЛЬ С ДЕТАЛЯМИ ТОВАРА (СПРАВА)
// =====================================

function formatStock(num) {
  if (!num) return '0';
  const val = parseFloat(num);
  if (isNaN(val)) return num;
  return val.toFixed(2);
}
function openProductModal(item) {
  document.getElementById('productModalName').textContent = item.name || '—';
  document.getElementById('productModalArticul').textContent = item.articul || '—';
  document.getElementById('productModalDescription').textContent = item.description || '—';
  document.getElementById('productModalTip').textContent = item.tip || '—';
  document.getElementById('productModalSupplier').textContent = item.supplier || '—';
  document.getElementById('productModalMass').textContent = item.mass || '—';
  document.getElementById('productModalPrice').textContent = item.price || '0';
  document.getElementById('productModalStock').textContent = formatStock(item.stock);
document.getElementById('productModalStock1').textContent = formatStock(item.stock_store1);
document.getElementById('productModalStock2').textContent = formatStock(item.stock_store2);
document.getElementById('productModalStock3').textContent = formatStock(item.stock_store3);
document.getElementById('productModalStock4').textContent = formatStock(item.stock_store4);
  document.getElementById('productModalVolume').textContent = item.volumeWeight || '—';
  const modalImg = document.getElementById('productModalImg');
  if (item.photoFull) {
    modalImg.src = 'image_proxy.php?url=' + encodeURIComponent(item.photoFull);
  } else {
    modalImg.src = '';
  }
  document.getElementById('productModal').classList.add('open');
}
function closeProductModal() {
  document.getElementById('productModal').classList.remove('open');
}

async function loadImageAsBase64(url) {
  const response = await fetch(url);      // подгружаем файл logo_big.png
  const blob = await response.blob();     // превращаем в Blob
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onloadend = () => {
      // reader.result выглядит как "data:image/png;base64,iVBORw0KGgo..."
      // чтобы SheetJS "съел" эту строку, обычно оставляют только часть после "base64,"
      const base64data = reader.result.split(',')[1];
      resolve(base64data);
    };
    reader.onerror = reject;
    reader.readAsDataURL(blob);
  });
}

// =====================================
// ПРИ ЗАГРУЗКЕ СТРАНИЦЫ
// =====================================
document.addEventListener('DOMContentLoaded', () => {
  loadData();
  document.getElementById('btnRefresh').addEventListener('click', () => {
    loadData();
  });
  document.getElementById('export-button').addEventListener('click', async () => {
  // Пусть у нас есть "сырые" данные:
  const allData = window.__productsData || [];
  if (!allData.length) {
    alert("Нет данных для экспорта.");
    return;
  }

  // Сериализуем в JSON
  const jsonData = JSON.stringify(allData);

  // Создаём форму (POST), кладём jsonData, целим на export.php
  const form = document.createElement("form");
  form.method = "POST";
  form.action = "export.php";   // Путь к вашему скрипту PhpSpreadsheet
  form.style.display = "none";

  // Создаём скрытое поле
  const input = document.createElement("input");
  input.type = "hidden";
  input.name = "jsonData";
  input.value = jsonData;

  form.appendChild(input);
  document.body.appendChild(form);
  form.submit();

  // Удалим форму после отправки (необязательно)
  document.body.removeChild(form);
});

  // Фильтры
  ['filterArticul','filterName','filterMassMin','filterMassMax','filterPriceMin','filterPriceMax']
  .forEach(id => {
    document.getElementById(id).addEventListener('input', applyFilters);
  });

['filterTip','filterCountry','filterStore'].forEach(id => {
    document.getElementById(id).addEventListener('change', applyFilters);
});
  document.getElementById('modalClose').addEventListener('click', closeModal);
      document.getElementById('imageModal').addEventListener('click', (e) => {
        if (e.target.id === 'imageModal') closeModal();
      });
  document.getElementById('productModalClose').addEventListener('click', closeProductModal);
});
</script>

</body>
</html>
