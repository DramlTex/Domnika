<?php
require_once 'data.php';
$users = loadUsers();
$users[] = [
    "login" => "admin",
    "password_hash" => password_hash("adminpass", PASSWORD_DEFAULT),
    "role" => "admin",
    "counterparty_href" => "",
    "discount" => 0
];
saveUsers($users);
echo "Admin user created";