<?php
session_start();
session_unset();    // очищаем данные сессии
session_destroy();  // уничтожаем сессию
header('Location: login.php');
exit();
?>
