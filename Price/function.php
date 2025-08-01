<?php
// Загрузка пользователей из JSON-файла
function loadUsers(): array {
    $file = __DIR__ . '/casa/xNtxj6hsL2.json';
    $jsonData = @file_get_contents($file);
    if ($jsonData === false) {
        return []; // если файла нет или ошибка чтения
    }
    $data = json_decode($jsonData, true);
    return is_array($data) ? $data : [];
}

// Сохранение массива пользователей в JSON-файл
function saveUsers(array $users): void {
    $file = __DIR__ . '/casa/xNtxj6hsL2.json';
    file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT));
}