<?php
ini_set('display_errors', 1);
ini_set('session.save_path', __DIR__ . '/sessions');
error_reporting(E_ALL);

session_start();
require_once 'function.php';  // подключаем функции loadUsers/saveUsers

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

// Инициализация переменной для возможного сообщения об ошибке
$error = '';

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['login'] ?? '';
    $pass  = $_POST['password'] ?? '';
    $users = loadUsers();

    foreach ($users as $u) {
        // Проверяем совпадение логина и хэш пароля
        if ($u['login'] === $login && password_verify($pass, $u['password_hash'])) {
            // Успешный вход: сохраняем данные пользователя в сессии
            $_SESSION['user'] = [
                'login'            => $u['login'],
                'role'             => $u['role'],
                'counterparty_href'=> $u['counterparty_href'] ?? '',
                'discount'         => $u['discount'] ?? 0,
                'productfolders'   => $u['productfolders'] ?? [], // <-- добавляем сюда
                'rules_file'       => $u['rules_file'] ?? 'row_sort_rules.json'
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
    $error = 'Неверный логин или пароль.';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <title>Вход в систему</title>
  <link rel="stylesheet" type="text/css" href="styles.css">
    <style>
    body {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        margin: 0;
        position: relative;
    }
    body::before {
        content: "";
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: url('tea.jpg') center/cover no-repeat;
        opacity: 0.6;
        z-index: -1;
    }
  </style>
</head>
<body>
  <?php if ($error): ?>
    <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
  <?php endif; ?>
  <div class="login-container">
    <form method="post" action="login.php">
      <label><input type="text" name="login" placeholder="Логин" required></label><br>
      <label><input type="password" name="password" placeholder="Пароль" required></label><br>
      <button type="submit">Войти</button>
    </form>
  </div>
</body>
</html>
