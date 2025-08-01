<?php
require_once 'data.php';
$users = loadUsers();
$users[] = [
    "login" => "admin",
    "password" => "adminpass",
    "role" => "admin",
    "counterparty_href" => "",
    "discount" => 0
];
saveUsers($users);
echo "Admin user created";