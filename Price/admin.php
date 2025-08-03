<?php
// 1. Sessions
ini_set('session.save_path', __DIR__ . '/sessions');
if (!is_dir(__DIR__ . '/sessions')) {
    mkdir(__DIR__ . '/sessions', 0777, true);
}
session_start();

// Проверяем, что пользователь авторизован
if (!isset($_SESSION['user'])) {
    header('Location: auth.php');
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

// ------------------ Функции для работы с xNtxj6hsL2.json ------------------
function loadUsers() {
    $file = __DIR__ . '/casa/xNtxj6hsL2.json';
    if (!file_exists($file)) {
        return [];
    }
    $data = file_get_contents($file);
    $arr  = json_decode($data, true);
    return is_array($arr) ? $arr : [];
}

function saveUsers(array $users) {
    $file = __DIR__ . '/casa/xNtxj6hsL2.json';
    file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ------------------ Проверка, что залогинен как админ ------------------
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: auth.php');
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
    $file = __DIR__ . '/casa/counterparties.json';
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    $arr  = json_decode($json, true);
    return is_array($arr) ? $arr : [];
}

function saveCounterpartiesLocal($data) {
    $file = __DIR__ . '/casa/counterparties.json';
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ------------------ Функции для работы с локальным JSON групп товаров ------------------
function loadProductFoldersLocal() {
    $file = __DIR__ . '/casa/productfolders.json';
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    $arr  = json_decode($json, true);
    return is_array($arr) ? $arr : [];
}

function saveProductFoldersLocal($data) {
    $file = __DIR__ . '/casa/productfolders.json';
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Данные из МоегоСклада загружаются только по запросу пользователя
$reloadRequested = isset($_POST['loadMs']);

if ($reloadRequested) {
    $counterparties = getCounterpartiesMS($login, $password, $msError);
    $productFolders = getProductFoldersMS($login, $password, $msError);
    $countriesList  = getCountriesMS($login, $password, $msError);
    $typesList      = getTypesMS($login, $password, $msError);

    // cache results locally
    saveCounterpartiesLocal($counterparties);
    saveProductFoldersLocal($productFolders);

    $_SESSION['msData'] = [
        'counterparties' => $counterparties,
        'productFolders' => $productFolders,
        'countries'      => $countriesList,
        'types'          => $typesList,
    ];
} elseif (isset($_SESSION['msData'])) {
    $counterparties = $_SESSION['msData']['counterparties'];
    $productFolders = $_SESSION['msData']['productFolders'];
    $countriesList  = $_SESSION['msData']['countries'];
    $typesList      = $_SESSION['msData']['types'];
} else {
    // fall back to cached data if available
    $counterparties = loadCounterpartiesLocal();
    $productFolders = loadProductFoldersLocal();
    $countriesList  = [];
    $typesList      = [];
}

// ------------------ Tabs for rules ------------------
$adminTabs = [
    'classic' => 'Классические чаи',
    'aroma'   => 'Ароматизированный чай',
    'herbs'   => 'Травы и добавки',
    'spices'  => 'Приправы'
];
$currentTab = $_GET['tab'] ?? 'classic';
if (!isset($adminTabs[$currentTab])) {
    $currentTab = 'classic';
}
$currentTabName = $adminTabs[$currentTab];

// ------------------ Row sort rules ------------------
$rulesFilePath = __DIR__ . "/casa/row_sort_rules_{$currentTab}.json";
$countryRules = [];
if (file_exists($rulesFilePath)) {
    $json = file_get_contents($rulesFilePath);
    $tmp  = json_decode($json, true);
    if (is_array($tmp) && isset($tmp['countries']) && is_array($tmp['countries'])) {
        $countryRules = $tmp['countries'];
    }
}

// ------------------ Column rules ------------------
$columnFilePath = __DIR__ . "/casa/column_rules_{$currentTab}.json";
$columnRules = [];
if (file_exists($columnFilePath)) {
    $json = file_get_contents($columnFilePath);
    $columnRules = json_decode($json, true);
    if (!is_array($columnRules)) {
        $columnRules = [];
    }
}

// ------------------ Save sorting and column rules ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveRules'])) {
    // Nested sorting rules
    $postedCountries = $_POST['countries'] ?? [];
    $newCountries = [];
    if (is_array($postedCountries)) {
        foreach ($postedCountries as $c) {
            $cName = trim($c['name'] ?? '');
            if ($cName === '') continue;
            $newTypes = [];
            foreach ($c['types'] ?? [] as $t) {
                $tName = trim($t['name'] ?? '');
                if ($tName === '') continue;
                $newProducts = [];
                foreach ($t['products'] ?? [] as $p) {
                    $id = trim($p['id'] ?? '');
                    if ($id === '') continue;
                    $pName = trim($p['name'] ?? '');
                    $newProducts[] = ['id' => $id, 'name' => $pName];
                }
                $newTypes[] = ['name' => $tName, 'products' => $newProducts];
            }
            $newCountries[] = ['name' => $cName, 'types' => $newTypes];
        }
    }
    file_put_contents(
        $rulesFilePath,
        json_encode(['countries' => $newCountries], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    // Column rules
    $ids     = $_POST['col_id'] ?? [];
    $titles  = $_POST['col_title'] ?? [];
    $classes = $_POST['col_class'] ?? [];
    $enabled = $_POST['col_enabled'] ?? [];
    $newCols = [];
    foreach ($ids as $i => $id) {
        $id    = trim($id);
        $title = trim($titles[$i] ?? '');
        $class = trim($classes[$i] ?? '');
        $en    = isset($enabled[$i]) && $enabled[$i] === '1';
        $row   = ['id' => $id, 'title' => $title, 'enabled' => $en];
        if ($class !== '') {
            $row['class'] = $class;
        }
        $newCols[] = $row;
    }
    file_put_contents(
        $columnFilePath,
        json_encode($newCols, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    header('Location: admin.php?tab=' . $currentTab);
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
          $u['password'] = $newPassword;
      }

  }

  // Переписываем индексы и сохраняем
  $users = array_values($users);
  saveUsers($users);

  header('Location: admin.php?tab=' . $currentTab);
  exit;
}


// ------------------ ADD NEW USER (role=user, password) ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addUser'])) {
  $newLogin    = trim($_POST['new_login'] ?? '');
  $newPassword = trim($_POST['new_password'] ?? '');
  $newHref     = $_POST['new_href'] ?? '';
  $newDiscount = isset($_POST['new_discount']) ? (int)$_POST['new_discount'] : 0;

  // Группы товаров (href)
  $newFolders = $_POST['new_productfolder_hrefs'] ?? []; // массив href

  if ($newLogin === '' || $newPassword === '') {
      header('Location: admin.php?tab=' . $currentTab);
      exit;
  }

  // Проверка логина на уникальность
  foreach ($users as $u) {
      if ($u['login'] === $newLogin) {
          header('Location: admin.php?tab=' . $currentTab);
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
      'password'    => $newPassword,
      'role'        => 'user',
      'discount'    => max(0, min(100, $newDiscount)),
      'counterparty'=> [
          'href' => $newHref,
          'name' => $foundCounterparty['name'] ?? ''
      ],
      'productfolders' => $productFoldersData
  ];

  saveUsers($users);
  header('Location: admin.php?tab=' . $currentTab);
  exit;
}

$username = $_SESSION['user']['login'];


?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <link rel="icon" type="image/x-icon" href="favicons/favicon.ico">
    <link rel="stylesheet" type="text/css" href="styles/admin.css">
    <meta charset="UTF-8">
    <title>Админ-панель</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" />
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    
</head>
<body class="ms-login-field">
<header>
    <div class="header-left">
        <button type="button" id="openUsersModal" class="header-btn">Редактировать пользователей</button>
        <form method="post" action="admin.php?tab=<?= $currentTab ?>" class="header-form">
            <button type="submit" name="loadMs" class="header-btn">Получить данные из Моего Склада</button>
        </form>
    </div>
    <h2 class="header-title"><?= htmlspecialchars($currentTabName) ?></h2>
    <p class="user-info">
        Вы вошли как: <strong><?= htmlspecialchars($username) ?></strong>
        <a href="logout.php" class="header-btn logout-link">Выйти</a>
    </p>
</header>
<div class="admin-container">
    <h3 class="rules-title">Настройка правил отображения товаров</h3>
    <div class="admin-tabs">
    <?php foreach ($adminTabs as $slug => $title): ?>
        <a href="admin.php?tab=<?= $slug ?>" class="admin-tab<?= $slug === $currentTab ? ' active' : '' ?>"><?= htmlspecialchars($title) ?></a>
    <?php endforeach; ?>
    </div>
    
    <!-- Правила сортировки и столбцов -->
    <form method="post" action="admin.php?tab=<?= $currentTab ?>" id="rulesForm">
    <hr>
    <!-- Редактирование колонок -->
    <div class="sort-rules">
        <h4 class="column-title">Колонки таблицы</h4>
        <table class="column-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Колонка</th>
                    <th>Название</th>
                    <th>Статус</th>
                </tr>
            </thead>
            <tbody id="columnFields">
            <?php foreach ($columnRules as $col): ?>
                <tr class="column-row<?php if (empty($col['enabled'])) echo ' disabled'; ?>">
                    <td><span class="drag-handle">&#9776;</span></td>
                    <td>
                        <select name="col_id[]" class="column-select">
                            <?php foreach ($columnRules as $opt): ?>
                                <option value="<?= htmlspecialchars($opt['id']) ?>" <?= $opt['id'] === $col['id'] ? 'selected' : '' ?>><?= htmlspecialchars($opt['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <input type="text" name="col_title[]" value="<?= htmlspecialchars($col['title']) ?>" class="ms-form-control" />
                        <input type="hidden" name="col_class[]" value="<?= htmlspecialchars($col['class'] ?? '') ?>">
                        <input type="hidden" name="col_enabled[]" class="col-enabled" value="<?= $col['enabled'] ? '1' : '0' ?>">
                    </td>
                    <td>
                        <button type="button" class="toggle-column btn-msk<?= $col['enabled'] ? ' active' : '' ?>">
                            <?= $col['enabled'] ? 'Выключить' : 'Включить' ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <hr>
    <div class="sort-rules">
        <h4>Порядок стран</h4>
            <div class="sort-rules">
        <h4>Порядок стран, типов и товаров</h4>
        <div id="countryContainer">
            <?php foreach ($countryRules as $ci => $country): ?>
                <div class="country-block">
                    <span class="drag-handle">&#9776;</span>
                    <select name="countries[<?= $ci ?>][name]" class="country-select">
                        <option value="">(Не выбрана)</option>
                        <?php foreach ($countriesList as $name): ?>
                            <option value="<?= htmlspecialchars($name) ?>" <?= $name === ($country['name'] ?? '') ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                        <?php if (!in_array($country['name'] ?? '', $countriesList, true)): ?>
                            <option value="<?= htmlspecialchars($country['name'] ?? '') ?>" selected><?= htmlspecialchars($country['name'] ?? '') ?></option>
                        <?php endif; ?>
                    </select>
                    <button type="button" class="remove-country btn-msk">Удалить страну</button>
                    <div class="type-container">
                        <?php foreach (($country['types'] ?? []) as $ti => $type): ?>
                            <div class="type-block">
                                <span class="drag-handle">&#9776;</span>
                                <select name="countries[<?= $ci ?>][types][<?= $ti ?>][name]" class="type-select">
                                    <option value="">(Не выбран)</option>
                                    <?php foreach ($typesList as $name): ?>
                                        <option value="<?= htmlspecialchars($name) ?>" <?= $name === ($type['name'] ?? '') ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                    <?php endforeach; ?>
                                    <?php if (!in_array($type['name'] ?? '', $typesList, true)): ?>
                                        <option value="<?= htmlspecialchars($type['name'] ?? '') ?>" selected><?= htmlspecialchars($type['name'] ?? '') ?></option>
                                    <?php endif; ?>
                                </select>
                                <button type="button" class="remove-type btn-msk">Удалить тип</button>
                                <div class="product-container">
                                    <?php foreach (($type['products'] ?? []) as $pi => $p): ?>
                                        <div class="product-row">
                                            <span class="drag-handle">&#9776;</span>
                                            <span class="product-name"><?= htmlspecialchars($p['name']) ?></span>
                                            <input type="hidden" name="countries[<?= $ci ?>][types][<?= $ti ?>][products][<?= $pi ?>][id]" value="<?= htmlspecialchars($p['id']) ?>">
                                            <input type="hidden" name="countries[<?= $ci ?>][types][<?= $ti ?>][products][<?= $pi ?>][name]" value="<?= htmlspecialchars($p['name']) ?>">
                                            <button type="button" class="remove-product btn-msk">Удалить</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="text" class="product-search" placeholder="Введите имя товара" style="width:300px;">
                                <button type="button" class="btnSearchProduct">Найти</button>
                                <select class="productResults" style="width:300px; display:none;"></select>
                                <button type="button" class="addProduct" style="display:none;">Добавить</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="add-type btn-msk">Добавить тип</button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="addCountry" class="btn-msk">Добавить страну</button>
    </div>
    
    <button type="submit" name="saveRules" class="btn-msk btn-success">Сохранить</button>
    </form>
    </div>
    <?php if ($msError): ?>
        <div class="error">
            <strong>Ошибка при обращении к МойСклад:</strong><br>
            <?= $msError ?>
        </div>
    <?php endif; ?>
    
</div>

<div id="usersModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" id="closeUsersModal">&times;</span>
        <!-- Таблица пользователей (исключая админа) -->
        <h3 class="users-modal-title">Пользователи</h3>
        <form method="post" action="admin.php?tab=<?= $currentTab ?>">
            <table>
                <thead>
                    <tr>
                        <th>Логин</th>
                        <th>Контрагент</th>
                        <th>Скидка %</th>
                        <th>Новый пароль</th>
                        <th>Группы товаров</th>
                        <th>Удалить?</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $index => $u): ?>
                        <?php if ($u['role'] === 'admin') continue; ?>
                        <tr>
                            <td><?= htmlspecialchars($u['login']) ?></td>

                            <!-- Контрагент (один) -->
                            <td>
                                <select name="counterparty_href[<?= $index ?>]">
                                    <option value="">(Не выбран)</option>
                                    <?php
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
                                    $currentFolders = $u['productfolders'] ?? [];
                                    $currentFoldersHrefs = array_column($currentFolders, 'href');

                                    foreach ($productFolders as $pf):
                                        $pfHref = $pf['href'];
                                        $pfName = $pf['name'];
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
                </tbody>
            </table>

            <br>
            <button type="submit" name="saveChanges" class="header-btn save-changes-btn">Сохранить изменения</button>
        </form>

        <button type="button" id="openAddUserModal" class="header-btn">Создать нового</button>
    </div>
</div>

<!-- Модальное окно добавления нового пользователя -->
<div id="addUserModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" id="closeAddUserModal">&times;</span>
        <h3 class="add-user-title">Добавить нового пользователя</h3>
        <form method="post" action="admin.php?tab=<?= $currentTab ?>" class="add-user-form">
            <input type="hidden" name="addUser" value="1">

            <div class="login-pass-row">
                <input type="text" name="new_login" placeholder="Логин" required class="ms-form-control">
                <input type="text" name="new_password" placeholder="Пароль" required class="ms-form-control">
            </div>
            <br>
            <label class="centered-label">Скидка (%):
                <input type="number" name="new_discount" value="0" min="0" max="100" class="ms-form-control">
            </label>
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
            <br>
            <label class="centered-label">Группы товаров:</label><br>
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

            <button type="submit" class="header-btn">Созздать</button>
        </form>
    </div>
</div>


<script>
var countries = <?= json_encode($countriesList, JSON_UNESCAPED_UNICODE) ?>;
var types = <?= json_encode($typesList, JSON_UNESCAPED_UNICODE) ?>;

function createCountryBlock(value) {
    var index = $('#countryContainer .country-block').length;
    var block = $('<div class="country-block"></div>');
    var handle = $('<span class="drag-handle">&#9776;</span>');
    var select = $('<select class="country-select"></select>').attr('name', 'countries[' + index + '][name]');
    select.append('<option value="">(Не выбрана)</option>');
    countries.forEach(function(c){
        var opt = $('<option>').val(c).text(c);
        if (c === value) opt.attr('selected','selected');
        select.append(opt);
    });
    if (value && countries.indexOf(value) === -1){
        select.append($('<option>').val(value).text(value).attr('selected','selected'));
    }
    var remove = $('<button type="button" class="remove-country btn-msk">Удалить страну</button>');
    var typeCont = $('<div class="type-container"></div>');
    var addType = $('<button type="button" class="add-type btn-msk">Добавить тип</button>');
    block.append(handle, select, remove, typeCont, addType);
    return block;
}

function createTypeBlock(cIndex, value) {
    var tIndex = $('#countryContainer .country-block').eq(cIndex).find('.type-block').length;
    var block = $('<div class="type-block"></div>');
    var handle = $('<span class="drag-handle">&#9776;</span>');
    var select = $('<select class="type-select"></select>').attr('name', 'countries[' + cIndex + '][types][' + tIndex + '][name]');
    select.append('<option value="">(Не выбран)</option>');
    types.forEach(function(t){
        var opt = $('<option>').val(t).text(t);
        if (t === value) opt.attr('selected','selected');
        select.append(opt);
    });
    if (value && types.indexOf(value) === -1){
        select.append($('<option>').val(value).text(value).attr('selected','selected'));
    }
    var remove = $('<button type="button" class="remove-type btn-msk">Удалить тип</button>');
    var prodCont = $('<div class="product-container"></div>');
    var search = $('<input type="text" class="product-search" placeholder="Введите имя товара" style="width:300px;">');
    var btnSearch = $('<button type="button" class="btnSearchProduct">Найти</button>');
    var results = $('<select class="productResults" style="width:300px; display:none;"></select>');
    var addProd = $('<button type="button" class="addProduct" style="display:none;">Добавить</button>');
    block.append(handle, select, remove, prodCont, search, btnSearch, results, addProd);
    return block;
}

function createProductRow(cIndex, tIndex, id, name){
    var pIndex = $('#countryContainer .country-block').eq(cIndex).find('.type-block').eq(tIndex).find('.product-row').length;
    var row = $('<div class="product-row"></div>');
    var handle = $('<span class="drag-handle">&#9776;</span>');
    var text = $('<span class="product-name"></span>').text(name);
    var hidId = $('<input type="hidden">').attr('name','countries['+cIndex+'][types]['+tIndex+'][products]['+pIndex+'][id]').val(id);
    var hidName = $('<input type="hidden">').attr('name','countries['+cIndex+'][types]['+tIndex+'][products]['+pIndex+'][name]').val(name);
    var remove = $('<button type="button" class="remove-product btn-msk">Удалить</button>');
    row.append(handle, text, hidId, hidName, remove);
    return row;
}

var columnOptions = <?php echo json_encode(array_column($columnRules, 'title', 'id'), JSON_UNESCAPED_UNICODE); ?>;

function createColumnRow(id, title, cls, enabled) {
    var row = $('<tr class="column-row"></tr>');
    if (!enabled) row.addClass('disabled');
    var handle = $('<td><span class="drag-handle">&#9776;</span></td>');
    var selectTd = $('<td></td>');
    var select = $('<select name="col_id[]" class="column-select"></select>');
    $.each(columnOptions, function(key, val){
        var opt = $('<option>').val(key).text(val);
        if (key === id) opt.attr('selected','selected');
        select.append(opt);
    });
    selectTd.append(select);
    var titleTd = $('<td></td>');
    var titleInput = $('<input type="text" name="col_title[]" class="ms-form-control">').val(title || '');
    var classInput = $('<input type="hidden" name="col_class[]">').val(cls || '');
    var enabledInput = $('<input type="hidden" name="col_enabled[]" class="col-enabled">').val(enabled ? '1' : '0');
    titleTd.append(titleInput, classInput, enabledInput);
    var toggleTd = $('<td></td>');
    var toggleBtn = $('<button type="button" class="toggle-column btn-msk"></button>').text(enabled ? 'Выключить' : 'Включить');
    if (enabled) toggleBtn.addClass('active');
    toggleTd.append(toggleBtn);
    row.append(handle, selectTd, titleTd, toggleTd);
    return row;
}

$(function(){
    $('select').select2();
    $('#countryContainer').sortable({handle: '.drag-handle'}).disableSelection();

    $(document).on('click','#addCountry', function(){
        var block = createCountryBlock('');
        $('#countryContainer').append(block);
        block.find('select').select2();
        $('#countryContainer').sortable('refresh');
    });

    $(document).on('click','.add-type', function(){
        var cBlock = $(this).closest('.country-block');
        var cIndex = cBlock.index();
        var block = createTypeBlock(cIndex, '');
        cBlock.find('.type-container').append(block);
        block.find('select').select2();
        cBlock.find('.type-container').sortable({handle: '.drag-handle'}).disableSelection();
    });

    $(document).on('click','.btnSearchProduct', function(){
        var tBlock = $(this).closest('.type-block');
        var term = tBlock.find('.product-search').val().trim();
        if(term.length < 2) return;
        $.getJSON('search_product.php',{q: term}, function(data){
            var select = tBlock.find('.productResults');
            select.empty();
            data.forEach(function(item){ select.append($('<option>').val(item.id).text(item.text)); });
            select.show();
            tBlock.find('.addProduct').show();
        });
    });

    $(document).on('click','.addProduct', function(){
        var tBlock = $(this).closest('.type-block');
        var cIndex = tBlock.closest('.country-block').index();
        var tIndex = tBlock.index();
        var select = tBlock.find('.productResults');
        var id = select.val();
        var name = select.find('option:selected').text();
        if(!id) return;
        var row = createProductRow(cIndex, tIndex, id, name);
        tBlock.find('.product-container').append(row);
        tBlock.find('.product-container').sortable({handle: '.drag-handle'}).disableSelection();
    });

    $(document).on('click','.remove-country', function(){ $(this).closest('.country-block').remove(); });
    $(document).on('click','.remove-type', function(){ $(this).closest('.type-block').remove(); });
    $(document).on('click','.remove-product', function(){ $(this).closest('.product-row').remove(); });

    $('#columnFields').sortable({handle: '.drag-handle'}).disableSelection();

    $(document).on('click', '.toggle-column', function(){
        var row = $(this).closest('.column-row');
        var input = row.find('.col-enabled');
        var val = input.val() === '1';
        if (val){
            input.val('0');
            $(this).text('Включить').removeClass('active');
            row.addClass('disabled');
        } else {
            input.val('1');
            $(this).text('Выключить').addClass('active');
            row.removeClass('disabled');
        }
    });

    $('#openUsersModal').on('click', function(){ $('#usersModal').show(); });
    $('#closeUsersModal').on('click', function(){ $('#usersModal').hide(); });
    $('#usersModal').on('click', function(e){ if(e.target.id === 'usersModal'){ $('#usersModal').hide(); } });
    $('#openAddUserModal').on('click', function(){ $('#addUserModal').show(); });
    $('#closeAddUserModal').on('click', function(){ $('#addUserModal').hide(); });
});
</script>

</body>
</html>
