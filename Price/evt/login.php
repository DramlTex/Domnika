<?php
session_start();
require_once 'data.php';  // подключаем функции loadUsers/saveUsers

// Если пользователь уже вошел, перенаправим сразу на нужную страницу
if (isset($_SESSION['user'])) {
    if ($_SESSION['user']['role'] === 'admin') {
        header('Location: admin.php');
    } else {
        header('Location: price.php');
    }
    exit();
}

// Инициализация переменной для возможного сообщения об ошибке
$error = '';

// Обработка отправки формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['login'] ?? '';
    $pass  = $_POST['password'] ?? '';
    $users = loadUsers();
    foreach ($users as $u) {
        // Проверяем совпадение логина и проверяем хэш пароля
        if ($u['login'] === $login && password_verify($pass, $u['password_hash'])) {
            // Успешный вход: сохраняем данные пользователя в сессии
            $_SESSION['user'] = [
                'login'            => $u['login'],
                'role'             => $u['role'],
                'counterparty_href'=> $u['counterparty_href'] ?? '',
                'discount'         => $u['discount'] ?? 0
            ];
            // Перенаправление в зависимости от роли
            if ($u['role'] === 'admin') {
                header('Location: admin.php');
            } else {
                header('Location: price.php');
            }
            exit();
        }
    }
    // Если цикл завершился без выхода, значит логин/пароль не подошли
    $error = 'Неверный логин или пароль.';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <title>Вход в систему</title>
  <link rel="stylesheet" href="styles.css">
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
