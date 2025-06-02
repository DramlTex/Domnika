<?php
// 1. Sessions
ini_set('session.save_path', __DIR__ . '/sessions');
if (!is_dir(__DIR__ . '/sessions')) {
    mkdir(__DIR__ . '/sessions', 0777, true);
}
session_start();

// Проверяем, что пользователь авторизован
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Проверяем, что роль = 'admin'
if ($_SESSION['user']['role'] !== 'admin') {
    // Если пользователь НЕ админ, не шлём его на login,
//   а сразу ведём на index.php — чтобы не было циклического редиректа!
    header('Location: index.php');
    exit();
}

// admin.php
ini_set('display_errors', 1);
error_reporting(E_ALL);



// 2. Config with MoySklad login/password
$config   = include __DIR__ . '/config.php';
$login    = $config['login'];
$password = $config['password'];

// ------------------ Функции для работы с users.json ------------------
function loadUsers() {
    $file = __DIR__ . '/users.json';
    if (!file_exists($file)) {
        return [];
    }
    $data = file_get_contents($file);
    $arr  = json_decode($data, true);
    return is_array($arr) ? $arr : [];
}

function saveUsers(array $users) {
    $file = __DIR__ . '/users.json';
    file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ------------------ Проверка, что залогинен как админ ------------------
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// ------------------ Работа с МойСклад (Counterparties + ProductFolders) ------------------
$msError = '';

// -- 1) Функция для загрузки контрагентов (с постраничным переходом)
function getCounterpartiesMS($login, $password, &$msError) {
    $allCounterparties = [];
    $url = 'https://api.moysklad.ru/api/remap/1.2/entity/counterparty?limit=1000';

    while ($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_USERPWD, $login . ":" . $password);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $msError = "Ошибка cURL: $err";
            return $allCounterparties;
        }
        if ($httpCode >= 400) {
            $msError = "Сервер вернул код ошибки: $httpCode<br>Ответ: " . htmlspecialchars($response);
            return $allCounterparties;
        }

        $data = json_decode($response, true);
        if (!isset($data['rows']) || !is_array($data['rows'])) {
            $msError = "Некорректный формат ответа от МойСклад при загрузке контрагентов.";
            return $allCounterparties;
        }

        foreach ($data['rows'] as $row) {
            if (!empty($row['name']) && !empty($row['meta']['href'])) {
                $allCounterparties[] = [
                    'name' => $row['name'],
                    'href' => $row['meta']['href']
                ];
            }
        }

        if (!empty($data['meta']['nextHref'])) {
            $url = $data['meta']['nextHref'];
        } else {
            $url = null;
        }
    }

    return $allCounterparties;
}

// -- 2) Функция для загрузки групп товаров (productfolders) с постраничным переходом
function getProductFoldersMS($login, $password, &$msError) {
    $allFolders = [];
    $url = 'https://api.moysklad.ru/api/remap/1.2/entity/productfolder?limit=1000';

    while ($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_USERPWD, $login . ":" . $password);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $msError = "Ошибка cURL: $err";
            return $allFolders;
        }
        if ($httpCode >= 400) {
            $msError = "Сервер вернул код ошибки: $httpCode<br>Ответ: " . htmlspecialchars($response);
            return $allFolders;
        }

        $data = json_decode($response, true);
        if (!isset($data['rows']) || !is_array($data['rows'])) {
            $msError = "Некорректный формат ответа от МойСклад при загрузке групп товаров.";
            return $allFolders;
        }

        foreach ($data['rows'] as $row) {
            if (!empty($row['name']) && !empty($row['meta']['href'])) {
                // Используем только name как название группы
                $pathName = !empty($row['pathName']) ? $row['pathName'] : '';
                $allFolders[] = [
                    'name' => $row['name'],
                    'pathName' => $pathName, // Используем pathName для отображения пути
                    'href' => $row['meta']['href']
                ];
            }
        }

        if (!empty($data['meta']['nextHref'])) {
            $url = $data['meta']['nextHref'];
        } else {
            $url = null;
        }
    }

    return $allFolders;
}

// -- 3) Функция для загрузки стран
function getCountriesMS($login, $password, &$msError) {
    $allCountries = [];
    $url = 'https://api.moysklad.ru/api/remap/1.2/entity/country?limit=1000';

    while ($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_USERPWD, $login . ':' . $password);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $msError = "Ошибка cURL: $err";
            return $allCountries;
        }
        if ($httpCode >= 400) {
            $msError = "Сервер вернул код ошибки: $httpCode<br>Ответ: " . htmlspecialchars($response);
            return $allCountries;
        }

        $data = json_decode($response, true);
        if (!isset($data['rows']) || !is_array($data['rows'])) {
            $msError = "Некорректный формат ответа от МойСклад при загрузке стран.";
            return $allCountries;
        }

        foreach ($data['rows'] as $row) {
            if (!empty($row['name'])) {
                $allCountries[] = $row['name'];
            }
        }

        if (!empty($data['meta']['nextHref'])) {
            $url = $data['meta']['nextHref'];
        } else {
            $url = null;
        }
    }

    return $allCountries;
}

// -- 4) Функция для загрузки типов чая из пользовательского справочника
// Идентификатор справочника хранится в константе
define('MS_TYPE_ENTITY_ID', '677eddfc-f284-11ef-0a80-0ee70028752e');

function getTypesMS($login, $password, &$msError) {
    $allTypes = [];
    $url = 'https://api.moysklad.ru/api/remap/1.2/entity/customentity/' . MS_TYPE_ENTITY_ID . '?limit=1000';

    while ($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_USERPWD, $login . ':' . $password);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $msError = "Ошибка cURL: $err";
            return $allTypes;
        }
        if ($httpCode >= 400) {
            $msError = "Сервер вернул код ошибки: $httpCode<br>Ответ: " . htmlspecialchars($response);
            return $allTypes;
        }

        $data = json_decode($response, true);
        if (!isset($data['rows']) || !is_array($data['rows'])) {
            $msError = "Некорректный формат ответа от МойСклад при загрузке типов.";
            return $allTypes;
        }

        foreach ($data['rows'] as $row) {
            if (!empty($row['name'])) {
                $allTypes[] = $row['name'];
            }
        }

        if (!empty($data['meta']['nextHref'])) {
            $url = $data['meta']['nextHref'];
        } else {
            $url = null;
        }
    }

    return $allTypes;
}

// ------------------ Загрузка пользователей ------------------
$users = loadUsers();

// ------------------ Функции для работы с локальным JSON контрагентов ------------------
function loadCounterpartiesLocal() {
    $file = __DIR__ . '/counterparties.json';
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    $arr  = json_decode($json, true);
    return is_array($arr) ? $arr : [];
}

function saveCounterpartiesLocal($data) {
    $file = __DIR__ . '/counterparties.json';
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ------------------ Функции для работы с локальным JSON групп товаров ------------------
function loadProductFoldersLocal() {
    $file = __DIR__ . '/productfolders.json';
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    $arr  = json_decode($json, true);
    return is_array($arr) ? $arr : [];
}

function saveProductFoldersLocal($data) {
    $file = __DIR__ . '/productfolders.json';
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Загружаем локальный список контрагентов и групп товаров
$counterparties = loadCounterpartiesLocal();
$productFolders = loadProductFoldersLocal();
$countriesList = getCountriesMS($login, $password, $msError);
$typesList = getTypesMS($login, $password, $msError);

// ------------------ Row sort rules ------------------
$rulesFilePath = __DIR__ . '/row_sort_rules.json';
$sortRules = [];
if (file_exists($rulesFilePath)) {
    $json = file_get_contents($rulesFilePath);
    $sortRules = json_decode($json, true);
    if (!is_array($sortRules)) {
        $sortRules = [];
    }
}
$countryOrder = $sortRules['countryOrder'] ?? [];
$typeOrder = $sortRules['typeOrder'] ?? [];

// ------------------ Column rules ------------------
$columnFilePath = __DIR__ . '/column_rules.json';
$columnRules = [];
if (file_exists($columnFilePath)) {
    $json = file_get_contents($columnFilePath);
    $columnRules = json_decode($json, true);
    if (!is_array($columnRules)) {
        $columnRules = [];
    }
}

// ------------------ Обработка кнопки "Обновить контрагентов из МойСклад" ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updateCounterparties'])) {
    $newCounterparties = getCounterpartiesMS($login, $password, $msError);
    if (!empty($newCounterparties)) {
        saveCounterpartiesLocal($newCounterparties);
    }
    header('Location: admin.php');
    exit;
}

// ------------------ Обработка кнопки "Обновить группы товаров" ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updateProductFolders'])) {
    $newFolders = getProductFoldersMS($login, $password, $msError);
    if (!empty($newFolders)) {
        saveProductFoldersLocal($newFolders);
    }
    header('Location: admin.php');
    exit;
}

// ------------------ Save sorting rules ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveSortRules'])) {
    $countries = array_map('trim', $_POST['countryOrder'] ?? []);
    $countries = array_values(array_filter($countries, fn($c) => $c !== ''));
    $sortRules['countryOrder'] = $countries;

    $types = array_map('trim', $_POST['typeOrder'] ?? []);
    $types = array_values(array_filter($types, fn($t) => $t !== ''));
    $sortRules['typeOrder'] = $types;

    file_put_contents($rulesFilePath, json_encode($sortRules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    header('Location: admin.php');
    exit;
}

// ------------------ Save column rules ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveColumnRules'])) {
    $ids     = $_POST['col_id'] ?? [];
    $titles  = $_POST['col_title'] ?? [];
    $classes = $_POST['col_class'] ?? [];
    $enabled = $_POST['col_enabled'] ?? [];
    $newCols = [];
    foreach ($ids as $i => $id) {
        $id = trim($id);
        $title = trim($titles[$i] ?? '');
        $class = trim($classes[$i] ?? '');
        $en    = isset($enabled[$i]) && $enabled[$i] === '1';
        $row = ['id' => $id, 'title' => $title, 'enabled' => $en];
        if ($class !== '') {
            $row['class'] = $class;
        }
        $newCols[] = $row;
    }
    file_put_contents($columnFilePath, json_encode($newCols, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    header('Location: admin.php');
    exit;
}

// ------------------ PROCESS CHANGES FOR EXISTING USERS (кроме админа) ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveChanges'])) {
  foreach ($users as $index => &$u) {
      if ($u['role'] === 'admin') {
          continue;
      }

      // Удаление пользователя
      if (!empty($_POST['delete'][$index]) && $_POST['delete'][$index] == '1') {
          unset($users[$index]);
          continue;
      }

      // Скидка
      $newDiscount = isset($_POST['discount'][$index]) ? (int)$_POST['discount'][$index] : 0;
      $u['discount'] = max(0, min(100, $newDiscount));

      // 1) Контрагент
      $newHref = $_POST['counterparty_href'][$index] ?? '';
      // Ищем контрагента в локальном списке, чтобы узнать name
      $foundCounterparty = null;
      foreach ($counterparties as $cnt) {
          if ($cnt['href'] === $newHref) {
              $foundCounterparty = $cnt;
              break;
          }
      }
      // Записываем
      $u['counterparty'] = [
          'href' => $newHref,
          'name' => $foundCounterparty['name'] ?? ''
      ];

      // 2) Группы товаров (множественный выбор)
      $newFolders = $_POST['productfolder_hrefs'][$index] ?? [];  // массив href
      $productFoldersData = [];
      if (is_array($newFolders)) {
          foreach ($newFolders as $folderHref) {
              foreach ($productFolders as $pf) {
                  if ($pf['href'] === $folderHref) {
                      $productFoldersData[] = [
                          'href' => $folderHref,
                          'name' => $pf['name']
                      ];
                      break;
                  }
              }
          }
      }
      $u['productfolders'] = $productFoldersData;

      // 3) Новый пароль (если задан)
      $newPassword = $_POST['new_password'][$index] ?? '';
      if (!empty($newPassword)) {
          $u['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
      }

  }

  // Переписываем индексы и сохраняем
  $users = array_values($users);
  saveUsers($users);

  header('Location: admin.php');
  exit;
}


// ------------------ ADD NEW USER (role=user, password_hash) ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addUser'])) {
  $newLogin    = trim($_POST['new_login'] ?? '');
  $newPassword = trim($_POST['new_password'] ?? '');
  $newHref     = $_POST['new_href'] ?? '';
  $newDiscount = isset($_POST['new_discount']) ? (int)$_POST['new_discount'] : 0;

  // Группы товаров (href)
  $newFolders = $_POST['new_productfolder_hrefs'] ?? []; // массив href

  if ($newLogin === '' || $newPassword === '') {
      header('Location: admin.php');
      exit;
  }

  // Проверка логина на уникальность
  foreach ($users as $u) {
      if ($u['login'] === $newLogin) {
          header('Location: admin.php');
          exit;
      }
  }

  // Находим контрагента
  $foundCounterparty = null;
  foreach ($counterparties as $cnt) {
      if ($cnt['href'] === $newHref) {
          $foundCounterparty = $cnt;
          break;
      }
  }

  // Формируем массив групп
  $productFoldersData = [];
  if (is_array($newFolders)) {
      foreach ($newFolders as $folderHref) {
          foreach ($productFolders as $pf) {
              if ($pf['href'] === $folderHref) {
                  $productFoldersData[] = [
                      'href' => $folderHref,
                      'name' => $pf['name']
                  ];
                  break;
              }
          }
      }
  }

  // Добавляем нового пользователя
  $users[] = [
      'login'       => $newLogin,
      'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
      'role'        => 'user',
      'discount'    => max(0, min(100, $newDiscount)),
      'counterparty'=> [
          'href' => $newHref,
          'name' => $foundCounterparty['name'] ?? ''
      ],
      'productfolders' => $productFoldersData
  ];

  saveUsers($users);
  header('Location: admin.php');
  exit;
}


?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <link rel="stylesheet" type="text/css" href="styles.css">
    <meta charset="UTF-8">
    <title>Админ-панель</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" />
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <style>
        table, th, td {
            border:1px solid #ccc;
            border-collapse: collapse;
            padding:8px;
        }
        th {
            background:#eee;
        }
        .error {
            padding:10px; margin:10px 0; background:#ffcfcf; color:#900; font-weight:bold;
        }
        /* Чтобы чекбоксы аккуратнее смотрелись в колонке */
        .checkbox-list {
            width: 300px;
            max-height: 150px;  /* или любое другое ограничение высоты */
            overflow-y: auto;   /* прокрутка, если списков слишком много */
            border: 1px solid #ddd;
            padding: 5px;
        }
        .sort-rules {
            margin: 20px 0;
        }
        .country-row {
            margin-bottom: 5px;
        }
        .type-row {
            margin-bottom: 5px;
        }
        .column-row {
            margin-bottom: 5px;
        }
        .column-row.disabled {
            opacity: 0.5;
        }
        .drag-handle {
            cursor: move;
            margin-right: 5px;
        }
        /* ===== Select2 ===== */
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #444;
            line-height: 28px;
        }
        .select2-container .select2-selection--single .select2-selection__rendered {
            display: block;
            padding-left: 8px;
            padding-right: 20px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* ===== Buttons ===== */
        .btn-msk.btn-success {
            color: #fff;
            background-color: #004fed;
            border-color: #004fed;
        }
        .btn-msk.btn-success:hover {
            color: #004fed;
            background-color: #fff;
            border-color: #004fed;
        }
        .btn-msk {
            padding: 10px 25px;
            text-decoration: none;
            border: 2px solid #2855af;
            border-radius: 8px;
            transition: .2s ease background-color, .2s ease color;
            cursor: pointer;
            font-weight: 600;
            background-color: transparent;
            color: #2855af;
        }

        /* ===== Input fields ===== */
        .ms-login-field .ms-form-control:-webkit-autofill {
            -webkit-box-shadow: 0 0 0 1000px #fff inset;
        }
        .ms-login-field .ms-form-control {
            height: 44px;
            padding: 0 14px;
            border-radius: 6px;
            border: 1px solid #d2d2d2;
        }
        @media (min-width: 768px) {
            .ms-login-field .ms-form-control {
                font-size: 14px;
            }
        }
        @media (min-width: 992px) {
            .ms-login-field .ms-form-control {
                font-size: 16px;
            }
        }
    </style>
</head>
<body class="ms-login-field">
<h2>Админ-панель</h2>
<p>[<a href="logout.php">Выйти</a>]</p>

<!-- Кнопки для обновления контрагентов/групп из МойСклад -->
<form method="post" action="admin.php" style="display:inline-block;">
    <button type="submit" name="updateCounterparties" value="1" class="btn-msk btn-success">
        Обновить контрагентов
    </button>
</form>

<!-- Редактирование порядка стран -->
<div class="sort-rules">
    <form method="post" action="admin.php" id="countryForm">
        <div id="countryFields">
        <?php foreach ($countryOrder as $c): ?>
            <div class="country-row">
                <span class="drag-handle">&#9776;</span>
                <select name="countryOrder[]" class="country-select">
                    <option value="">(Не выбрана)</option>
                    <?php foreach ($countriesList as $name): ?>
                        <option value="<?= htmlspecialchars($name) ?>" <?= $name === $c ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                    <?php if (!in_array($c, $countriesList, true)): ?>
                        <option value="<?= htmlspecialchars($c) ?>" selected><?= htmlspecialchars($c) ?></option>
                    <?php endif; ?>
                </select>
                <button type="button" class="remove-country btn-msk">Удалить</button>
            </div>
        <?php endforeach; ?>
        </div>
        <button type="button" id="addCountry" class="btn-msk">Добавить страну</button>
        <button type="submit" name="saveSortRules" class="btn-msk btn-success">Сохранить</button>
    </form>
</div>

<!-- Редактирование порядка типов -->
<div class="sort-rules">
    <form method="post" action="admin.php" id="typeForm">
        <div id="typeFields">
        <?php foreach ($typeOrder as $t): ?>
            <div class="type-row">
                <span class="drag-handle">&#9776;</span>
                <select name="typeOrder[]" class="type-select">
                    <option value="">(Не выбран)</option>
                    <?php foreach ($typesList as $name): ?>
                        <option value="<?= htmlspecialchars($name) ?>" <?= $name === $t ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                    <?php if (!in_array($t, $typesList, true)): ?>
                        <option value="<?= htmlspecialchars($t) ?>" selected><?= htmlspecialchars($t) ?></option>
                    <?php endif; ?>
                </select>
                <button type="button" class="remove-type btn-msk">Удалить</button>
            </div>
        <?php endforeach; ?>
        </div>
        <button type="button" id="addType" class="btn-msk">Добавить тип</button>
        <button type="submit" name="saveSortRules" class="btn-msk btn-success">Сохранить</button>
    </form>
</div>

<!-- Редактирование колонок -->
<div class="sort-rules">
    <form method="post" action="admin.php" id="columnForm">
        <div id="columnFields">
        <?php foreach ($columnRules as $col): ?>
            <div class="column-row<?php if (empty($col['enabled'])) echo ' disabled'; ?>">
                <span class="drag-handle">&#9776;</span>
                <select name="col_id[]" class="column-select">
                    <?php foreach ($columnRules as $opt): ?>
                        <option value="<?= htmlspecialchars($opt['id']) ?>" <?= $opt['id'] === $col['id'] ? 'selected' : '' ?>><?= htmlspecialchars($opt['title']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="col_title[]" value="<?= htmlspecialchars($col['title']) ?>" class="ms-form-control" style="width:150px;" />
                <input type="hidden" name="col_class[]" value="<?= htmlspecialchars($col['class'] ?? '') ?>">
                <input type="hidden" name="col_enabled[]" class="col-enabled" value="<?= $col['enabled'] ? '1' : '0' ?>">
                <button type="button" class="toggle-column btn-msk">
                    <?= $col['enabled'] ? 'Выключить' : 'Включить' ?>
                </button>
            </div>
        <?php endforeach; ?>
        </div>
        <button type="submit" name="saveColumnRules" class="btn-msk btn-success">Сохранить</button>
    </form>
</div>
<form method="post" action="admin.php" style="display:inline-block;">
    <button type="submit" name="updateProductFolders" value="1" class="btn-msk btn-success">
        Обновить группы товаров
    </button>
</form>

<!-- Ошибка от МойСклад, если есть -->
<?php if ($msError): ?>
    <div class="error">
        <strong>Ошибка при обращении к МойСклад:</strong><br>
        <?= $msError ?>
    </div>
<?php endif; ?>

<hr>

<!-- Таблица пользователей (исключая админа) -->
<form method="post" action="admin.php">
    <table>
        <tr>
            <th>Логин</th>
            <th>Контрагент</th>
            <th>Скидка %</th>
            <th>Новый пароль</th>
            <th>Группы товаров</th>
            <th>Удалить?</th>
        </tr>
        <?php foreach ($users as $index => $u): ?>
            <?php if ($u['role'] === 'admin') continue; // не отображаем админа ?>
            <tr>
                <td><?= htmlspecialchars($u['login']) ?></td>

                <!-- Контрагент (один) -->
                <td>
                    <select name="counterparty_href[<?= $index ?>]">
                        <option value="">(Не выбран)</option>
                        <?php
                        // Текущий href контрагента
                        $currentCounterpartyHref = $u['counterparty']['href'] ?? '';
                        foreach ($counterparties as $cnt):
                            $selected = ($cnt['href'] === $currentCounterpartyHref) ? 'selected' : '';
                            ?>
                            <option value="<?= htmlspecialchars($cnt['href']) ?>" <?= $selected ?>>
                                <?= htmlspecialchars($cnt['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>

                <!-- Скидка -->
                <td>
                    <input type="number"
                           name="discount[<?= $index ?>]"
                           value="<?= (int)($u['discount'] ?? 0) ?>"
                           min="0"
                           max="100"
                           class="ms-form-control"
                           style="width:60px;">
                </td>

                <!-- Новый пароль -->
                <td>
                    <input type="text" name="new_password[<?= $index ?>]" class="ms-form-control" placeholder="Новый пароль">
                </td>

                <!-- Группы товаров (множественный выбор через чекбоксы) -->
                <td>
                    <div class="checkbox-list">
                        <?php
                        // Какие группы сейчас привязаны к пользователю:
                        // $u['productfolders'] — массив объектов [ ['href'=>'...', 'name'=>'...'], ... ]
                        $currentFolders = $u['productfolders'] ?? [];
                        // Получим массив только href для сравнения
                        $currentFoldersHrefs = array_column($currentFolders, 'href');

                        foreach ($productFolders as $pf):
                            $pfHref = $pf['href'];
                            $pfName = $pf['name'];
                            // Если href текущей папки есть у пользователя, ставим checked
                            $checked = in_array($pfHref, $currentFoldersHrefs) ? 'checked' : '';
                            ?>
                            <label style="display:block;">
                                <input type="checkbox"
                                       name="productfolder_hrefs[<?= $index ?>][]"
                                       value="<?= htmlspecialchars($pfHref) ?>"
                                    <?= $checked ?>>
                                <?= htmlspecialchars($pfName) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </td>

                <!-- Удалить -->
                <td style="text-align:center;">
                    <input type="checkbox" name="delete[<?= $index ?>]" value="1">
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <br>
    <button type="submit" name="saveChanges" class="btn-msk btn-success">Сохранить изменения</button>
</form>

<hr>

<!-- Форма добавления нового пользователя -->
<h3>Добавить нового пользователя</h3>
<form method="post" action="admin.php">
    <input type="hidden" name="addUser" value="1">

    <label>Логин:
        <input type="text" name="new_login" required class="ms-form-control">
    </label>
    <br><br>

    <label>Пароль:
        <input type="text" name="new_password" required class="ms-form-control">
    </label>
    <br><br>

    <label>Контрагент:
        <select name="new_href">
            <option value="">(Не выбран)</option>
            <?php foreach ($counterparties as $cnt): ?>
                <option value="<?= htmlspecialchars($cnt['href']) ?>">
                    <?= htmlspecialchars($cnt['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <br><br>

    <label>Скидка (%):
        <input type="number" name="new_discount" value="0" min="0" max="100" class="ms-form-control">
    </label>
    <br><br>

    <label>Группы товаров:</label><br>
    <div class="checkbox-list">
        <?php foreach ($productFolders as $pf):
            $pfHref = $pf['href'];
            $pfName = $pf['name'];
            ?>
            <label style="display:block;">
                <input type="checkbox"
                       name="new_productfolder_hrefs[]"
                       value="<?= htmlspecialchars($pfHref) ?>">
                <?= htmlspecialchars($pfName) ?>
            </label>
        <?php endforeach; ?>
    </div>
    <br>

    <button type="submit" class="btn-msk btn-success">Создать пользователя</button>
</form>

<script>
var countries = <?= json_encode($countriesList, JSON_UNESCAPED_UNICODE) ?>;
var types = <?= json_encode($typesList, JSON_UNESCAPED_UNICODE) ?>;

function createCountryRow(value) {
    var row = $('<div class="country-row"></div>');
    var handle = $('<span class="drag-handle">&#9776;</span>');
    var select = $('<select name="countryOrder[]" class="country-select"></select>');
    select.append('<option value="">(Не выбрана)</option>');
    countries.forEach(function(c) {
        var opt = $('<option>').val(c).text(c);
        if (c === value) opt.attr('selected', 'selected');
        select.append(opt);
    });
    if (value && countries.indexOf(value) === -1) {
        select.append($('<option>').val(value).text(value).attr('selected', 'selected'));
    }
    var btn = $('<button type="button" class="remove-country btn-msk">Удалить</button>');
    row.append(handle).append(select).append(btn);
    return row;
}

function createTypeRow(value) {
    var row = $('<div class="type-row"></div>');
    var handle = $('<span class="drag-handle">&#9776;</span>');
    var select = $('<select name="typeOrder[]" class="type-select"></select>');
    select.append('<option value="">(Не выбран)</option>');
    types.forEach(function(t) {
        var opt = $('<option>').val(t).text(t);
        if (t === value) opt.attr('selected', 'selected');
        select.append(opt);
    });
    if (value && types.indexOf(value) === -1) {
        select.append($('<option>').val(value).text(value).attr('selected', 'selected'));
    }
    var btn = $('<button type="button" class="remove-type btn-msk">Удалить</button>');
    row.append(handle).append(select).append(btn);
    return row;
}

var columnOptions = <?php echo json_encode(array_column($columnRules, 'title', 'id'), JSON_UNESCAPED_UNICODE); ?>;

function createColumnRow(id, title, cls, enabled) {
    var row = $('<div class="column-row"></div>');
    if (!enabled) row.addClass('disabled');
    var handle = $('<span class="drag-handle">&#9776;</span>');
    var select = $('<select name="col_id[]" class="column-select"></select>');
    $.each(columnOptions, function(key, val) {
        var opt = $('<option>').val(key).text(val);
        if (key === id) opt.attr('selected', 'selected');
        select.append(opt);
    });
    var titleInput = $('<input type="text" name="col_title[]" class="ms-form-control" style="width:150px;">').val(title || '');
    var classInput = $('<input type="hidden" name="col_class[]">').val(cls || '');
    var enabledInput = $('<input type="hidden" name="col_enabled[]" class="col-enabled">').val(enabled ? '1' : '0');
    var toggleBtn = $('<button type="button" class="toggle-column btn-msk"></button>').text(enabled ? 'Выключить' : 'Включить');
    row.append(handle, select, titleInput, classInput, enabledInput, toggleBtn);
    return row;
}

$(function() {
    $('select').select2();

    $('#countryFields').sortable({
        handle: '.drag-handle'
    }).disableSelection();

    $('#typeFields').sortable({
        handle: '.drag-handle'
    }).disableSelection();

    $('#addCountry').on('click', function() {
        var newRow = createCountryRow('');
        $('#countryFields').append(newRow);
        newRow.find('select').select2();
        $('#countryFields').sortable('refresh');
    });

    $('#addType').on('click', function() {
        var newRow = createTypeRow('');
        $('#typeFields').append(newRow);
        newRow.find('select').select2();
        $('#typeFields').sortable('refresh');
    });

    $(document).on('click', '.remove-country', function() {
        $(this).closest('.country-row').remove();
    });

    $(document).on('click', '.remove-type', function() {
        $(this).closest('.type-row').remove();
    });

    $('#columnFields').sortable({
        handle: '.drag-handle'
    }).disableSelection();


    $(document).on('click', '.toggle-column', function() {
        var row = $(this).closest('.column-row');
        var input = row.find('.col-enabled');
        var val = input.val() === '1';
        if (val) {
            input.val('0');
            $(this).text('Включить');
            row.addClass('disabled');
        } else {
            input.val('1');
            $(this).text('Выключить');
            row.removeClass('disabled');
        }
    });

});
</script>
</body>
</html>
