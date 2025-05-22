<?php
session_start();
require_once 'data.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    // Если не авторизован или не админ – перенаправляем на форму входа
    header('Location: login.php');
    exit();
}

$users = loadUsers();

// Обработка удаления пользователя (параметр delete в URL)
if (isset($_GET['delete'])) {
    $delLogin = $_GET['delete'];
    // Нельзя удалить собственную учетную запись админа, если он сам инициирует
    if ($delLogin !== $_SESSION['user']['login']) {
        // Фильтруем массив, оставляя только тех, у кого логин не совпадает с удаляемым
        $users = array_filter($users, function($u) use ($delLogin) {
            return $u['login'] !== $delLogin;
        });
        saveUsers(array_values($users)); // сохраняем обновленный список
    }
    header('Location: admin.php');
    exit();
}

// Обработка добавления нового пользователя (данные из формы POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_login'])) {
    $newLogin   = trim($_POST['new_login']);
    $newPassword= $_POST['new_password'] ?? '';
    $newRole    = $_POST['new_role'] ?? 'user';
    $newHref    = trim($_POST['new_href'] ?? '');
    $newDiscount= isset($_POST['new_discount']) ? (int)$_POST['new_discount'] : 0;

    // Проверка уникальности логина
    $exists = false;
    foreach ($users as $u) {
        if ($u['login'] === $newLogin) {
            $exists = true;
            break;
        }
    }
    // Добавляем, только если логин уникален и непустой
    if (!$exists && $newLogin !== '') {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $users[] = [
            'login'            => $newLogin,
            'password_hash'    => $hashedPassword,
            'role'             => $newRole,
            'counterparty_href'=> $newHref,
            'discount'         => $newDiscount
        ];
        saveUsers($users);
    }
    header('Location: admin.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <title>Админ-панель</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <h2>Управление пользователями</h2>
  <p>Вы вошли как <strong><?php echo htmlspecialchars($_SESSION['user']['login']); ?></strong> 
     (<a href="logout.php">Выйти</a>)</p>

  <!-- Таблица со списком пользователей -->
  <table>
    <tr><th>Логин</th><th>Роль</th><th>Контрагент (href)</th><th>Скидка %</th><th>Действия</th></tr>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= htmlspecialchars($u['login']) ?></td>
        <td><?= htmlspecialchars($u['role']) ?></td>
        <td style="word-break: break-all;"><?= htmlspecialchars($u['counterparty_href'] ?? '') ?></td>
        <td><?= htmlspecialchars($u['discount'] ?? 0) ?></td>
        <td>
          <?php if ($u['login'] !== $_SESSION['user']['login']): ?>
            <a href="admin.php?delete=<?= urlencode($u['login']) ?>" 
               onclick="return confirm('Удалить пользователя &laquo;<?= htmlspecialchars($u['login']) ?>&raquo;?');">
               Удалить
            </a>
          <?php else: ?>
            <!-- Нельзя удалить самого себя -->
            <span style="color:gray;">(недоступно)</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>

  <!-- Форма для добавления нового пользователя -->
  <h3>Добавить нового пользователя</h3>
  <form method="post" action="admin.php">
    <label>Логин: <input type="text" name="new_login" required></label><br>
    <label>Пароль: <input type="text" name="new_password" required></label><br>
    <label>Роль: 
      <select name="new_role">
        <option value="user">Обычный пользователь</option>
        <option value="admin">Администратор</option>
      </select>
    </label><br>
    <label>Контрагент (href): <input type="text" name="new_href" placeholder="Ссылка на контрагента"></label><br>
    <label>Скидка (%): <input type="number" name="new_discount" value="0" min="0" max="100"></label><br>
    <button type="submit">Создать пользователя</button>
  </form>
</body>
</html>
