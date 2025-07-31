<?php
// Загрузка пользователей из JSON-файла
function loadUsers(): array {
    $jsonData = @file_get_contents('xNtxj6hsL2.json');
    if ($jsonData === false) {
        return []; // если файла нет или ошибка чтения
    }
    $data = json_decode($jsonData, true);
    return is_array($data) ? $data : [];
}

// Сохранение массива пользователей в JSON-файл
function saveUsers(array $users): void {
    file_put_contents('xNtxj6hsL2.json', json_encode($users, JSON_PRETTY_PRINT));
}