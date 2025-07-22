<?php
define('DIR', __DIR__);

$logFile = DIR . '/php-error.log';
if (file_exists($logFile) && filesize($logFile) > 10 * 1024 * 1024) {
    unlink($logFile);
    touch($logFile);
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', $logFile);

function log_event(string $type, string $message): void
{
    error_log("[$type] $message");
}

log_event('INFO', 'Запуск search_product.php');

ini_set('session.save_path', __DIR__ . '/sessions');
if (!is_dir(__DIR__ . '/sessions')) {
    mkdir(__DIR__ . '/sessions', 0777, true);
}
session_start();

// Проверяем авторизацию администратора
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    log_event('WARNING', 'Unauthorized access attempt');
    http_response_code(403);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Читаем настройки авторизации в МойСклад
$config = include __DIR__ . '/config.php';
$login    = $config['login'] ?? '';
$password = $config['password'] ?? '';
$token    = $config['token'] ?? '';

header('Content-Type: application/json; charset=UTF-8');

$query = trim($_GET['q'] ?? '');
if ($query === '') {
    log_event('INFO', 'Empty search query');
    echo json_encode([]);
    exit;
}
log_event('INFO', "Search query: $query");

$encodedQuery = rawurlencode($query);
$url = "https://online.moysklad.ru/api/remap/1.2/entity/product?filter=name~{$encodedQuery}&limit=20";
log_event('INFO', "Request URL: $url");

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_ENCODING, '');

$headers = ['Accept: application/json;charset=utf-8'];
if ($token !== '') {
    $headers[] = 'Authorization: Bearer ' . $token;
} else {
    curl_setopt($ch, CURLOPT_USERPWD, "$login:$password");
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

if ($error) {
    log_event('ERROR', "cURL error: $error");
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка cURL']);
    exit;
}
if ($httpCode === 401 || $httpCode === 403) {
    log_event('ERROR', "Invalid token, HTTP $httpCode");
    http_response_code(403);
    echo json_encode(['error' => 'Недействительный токен']);
    exit;
}
if ($httpCode !== 200) {
    log_event('ERROR', "API error HTTP $httpCode");
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка API МойСклад', 'status' => $httpCode]);
    exit;
}

$data = json_decode($response, true);
if (!is_array($data)) {
    log_event('ERROR', 'Invalid JSON response');
    http_response_code(500);
    echo json_encode(['error' => 'Некорректный ответ от API']);
    exit;
}

$results = [];
if (isset($data['rows']) && is_array($data['rows'])) {
    foreach ($data['rows'] as $item) {
        $id   = $item['externalCode'] ?? ($item['article'] ?? '');
        $name = $item['name'] ?? '';
        if ($id !== '' && $name !== '') {
            $results[] = ['id' => $id, 'text' => $name];
        }
    }
}

log_event('INFO', 'Found ' . count($results) . ' products');
echo json_encode($results, JSON_UNESCAPED_UNICODE);
