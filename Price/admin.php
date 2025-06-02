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
    file_put_contents($rulesFilePath, json_encode($sortRules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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
    </style>
</head>
<body>
<h2>Админ-панель</h2>
<p>[<a href="logout.php">Выйти</a>]</p>

<!-- Кнопки для обновления контрагентов/групп из МойСклад -->
<form method="post" action="admin.php" style="display:inline-block;">
    <button type="submit" name="updateCounterparties" value="1">
        Обновить контрагентов
    </button>
</form>

<!-- Редактирование порядка стран -->
<div class="sort-rules">
    <form method="post" action="admin.php" id="countryForm">
        <?php foreach ($countryOrder as $c): ?>
            <input type="text" name="countryOrder[]" value="<?= htmlspecialchars($c) ?>"><br>
        <?php endforeach; ?>
        <div id="countryExtra"></div>
        <button type="button" id="addCountry">Добавить страну</button>
        <button type="submit" name="saveSortRules">Сохранить</button>
    </form>
</div>
<form method="post" action="admin.php" style="display:inline-block;">
    <button type="submit" name="updateProductFolders" value="1">
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
                           style="width:60px;">
                </td>

                <!-- Новый пароль -->
                <td>
                    <input type="text" name="new_password[<?= $index ?>]" placeholder="Новый пароль">
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
    <button type="submit" name="saveChanges">Сохранить изменения</button>
</form>

<hr>

<!-- Форма добавления нового пользователя -->
<h3>Добавить нового пользователя</h3>
<form method="post" action="admin.php">
    <input type="hidden" name="addUser" value="1">

    <label>Логин:
        <input type="text" name="new_login" required>
    </label>
    <br><br>

    <label>Пароль:
        <input type="text" name="new_password" required>
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
        <input type="number" name="new_discount" value="0" min="0" max="100">
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

    <button type="submit">Создать пользователя</button>
</form>

<script>
document.getElementById('addCountry').addEventListener('click', function () {
    var cont = document.getElementById('countryExtra');
    var inp = document.createElement('input');
    inp.type = 'text';
    inp.name = 'countryOrder[]';
    cont.appendChild(inp);
    cont.appendChild(document.createElement('br'));
});
</script>
</body>
</html>
