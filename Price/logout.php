<?php
ini_set('session.save_path', __DIR__ . '/sessions');
session_start();
session_unset();    // очищаем данные сессии
session_destroy();  // уничтожаем сессию
header('Location: login.php');
exit();
?>
