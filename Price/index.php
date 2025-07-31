<?php
ini_set('session.save_path', __DIR__ . '/sessions');
session_start();

// Пример проверки авторизации
if (!isset($_SESSION['user'])) {
    header('Location: auth.php');
    exit();
}
$username = $_SESSION['user']['login'];
$userFolders = $_SESSION['user']['productfolders'] ?? [];
$rulesFile = $_SESSION['user']['rules_file'] ?? 'casa/row_sort_rules.json';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<link rel="icon" type="image/x-icon" href="favicons/favicon.ico">
  <meta charset="UTF-8">
  <title>Прайс лист ассортимента</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

  <link rel="stylesheet" type="text/css" href="styles/reset.css">
  <link rel="stylesheet" type="text/css" href="styles/banner.css">
  <link rel="stylesheet" type="text/css" href="styles/filters.css">
  <link rel="stylesheet" type="text/css" href="styles/buttons.css">
  <link rel="stylesheet" type="text/css" href="styles/table.css">
  <link rel="stylesheet" type="text/css" href="styles/modal-photo.css">
  <link rel="stylesheet" type="text/css" href="styles/product-panel.css">
  <link rel="stylesheet" type="text/css" href="styles/thumbnails.css">
  <link rel="stylesheet" type="text/css" href="styles/tabs.css">
  <link rel="stylesheet" type="text/css" href="styles/effects.css">
</head>


<body>

<!-- БАННЕР / ШАПКА -->
<div class="banner">
  <img class="logo white-svg"
       src="favicons/811140.svg"
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
        <a href="https://yandex.ru/maps/?ll=38.216686%2C55.794850&mode=whatshere&whatshere%5Bpoint%5D=38.215004%2C55.796465&whatshere%5Bzoom%5D=17&z=16.54"
           target="_blank">
           Дорожная 40, Индустриальный парк Старая Купавна М7
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
<p class="user-info"><!-- margin moved to CSS -->

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
  <div class="table-scroll">
    <table id="priceList">
    <thead>
      <tr>
      <th class="num-col">№</th>
      <th>Артикул</th>
      <th>Фото</th>
      <th class="name-col">Название</th>
      <th>Стандарт</th>
      <th class="type-col">Тип</th>
      <th class="country-col">Страна</th>
      <th>Вес тарного места</th>
      <th>Цена</th>
      <th>Наличие в кг</th>
      <th>Объемный вес</th>
      <!-- Добавляем новые колонки -->
      </tr>
    </thead>
    <tbody>
    </tbody>

  </table>
  </div>
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

        <div class="store-row">
          <p><strong>Алтуфьево:</strong> <span id="productModalStock1"></span></p>
        </div>
        <div class="store-row">
          <p><strong>Ивантеевка:</strong> <span id="productModalStock2"></span></p>
        </div>
        <div class="store-row">
          <p><strong>Купавна:</strong> <span id="productModalStock3"></span></p>
        </div>
        <div class="store-row">
          <p><strong>Можайск:</strong> <span id="productModalStock4"></span></p>
        </div>

        <p><strong>Объём тарного места:</strong> <span id="productModalVolume"></span></p>
      </div>
    </div>
  </div>
</div>


<script>window.__userFolders = <?php echo json_encode($userFolders, JSON_UNESCAPED_UNICODE); ?>;</script>
<script>window.__rulesFile = <?php echo json_encode($rulesFile, JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="scripts/data-loader.js"></script>
<script src="scripts/tabs.js"></script>
<script src="scripts/table.js"></script>
<script src="scripts/filters.js"></script>
<script src="scripts/modals.js"></script>
<script src="scripts/rules-loader.js"></script>
<script src="scripts/export-excel.js"></script>
<script src="scripts/main.js"></script>

</body>
</html>
