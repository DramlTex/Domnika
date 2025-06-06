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
<LINK rel=stylesheet type="text/css" href="styles.css">
<head>
  <meta charset="UTF-8" />
  <title>Вход в систему</title>
</head>
<body>
  <h2>Авторизация</h2>
  <?php if ($error): ?>
    <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
  <?php endif; ?>
  <form method="post" action="login.php">
    <label>Логин: <input type="text" name="login" required></label><br>
    <label>Пароль: <input type="password" name="password" required></label><br>
    <button type="submit">Войти</button>
  </form>
</body>
</html>
