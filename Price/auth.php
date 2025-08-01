<?php
ini_set('display_errors', 1);
ini_set('session.save_path', __DIR__ . '/sessions');
error_reporting(E_ALL);

session_start();
require_once 'function.php';  // подключаем функции loadUsers/saveUsers

// Извлекаем сообщение об ошибке из сессии (если есть)
$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

// Если пользователь уже авторизован:
if (isset($_SESSION['user'])) {
    // Проверяем роль
    if ($_SESSION['user']['role'] === 'admin') {
        header('Location: admin.php');
        exit();
    } else {
        header('Location: index.php');
        exit();
    }
}

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['login'] ?? '';
    $pass  = $_POST['password'] ?? '';
    $users = loadUsers();

    foreach ($users as $u) {
        // Проверяем совпадение логина и пароля
        if ($u['login'] === $login && $pass === ($u['password'] ?? '')) {
            // Успешный вход: сохраняем данные пользователя в сессии
            $_SESSION['user'] = [
                'login'            => $u['login'],
                'role'             => $u['role'],
                'counterparty_href'=> $u['counterparty_href'] ?? '',
                'discount'         => $u['discount'] ?? 0,
                'productfolders'   => $u['productfolders'] ?? [], // <-- добавляем сюда
                'rules_file'       => $u['rules_file'] ?? 'casa/row_sort_rules.json'
            ];
            // Редирект в зависимости от роли
            if ($u['role'] === 'admin') {
                header('Location: admin.php');
            } else {
                header('Location: index.php');
            }
            exit();
        }
    }

    // Если дошли сюда, значит логин/пароль не подошли
    $_SESSION['error'] = 'Неверный логин или пароль.';
    header('Location: auth.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <link rel="icon" type="image/x-icon" href="favicons/favicon.ico">
  <meta charset="UTF-8" />
  <title>Вход в систему</title>
  <link rel="stylesheet" type="text/css" href="styles/auth.css">
</head>
<body>
  <div class="login-container">
    <?php if ($error): ?>
      <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <form id="loginForm" method="post" action="auth.php">
      <label><input type="text" name="login" placeholder="Логин" required></label><br>
      <label><input type="password" name="password" placeholder="Пароль" required></label><br>
      <input type="submit" value="Войти">
    </form>
  </div>
</body>
</html>
