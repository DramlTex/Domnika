<?php
// Включаем вывод всех ошибок для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/output/webhook_errors.log');

define('ROOT_DIR', __DIR__);
define('LOG_FILE', ROOT_DIR . '/output/webhook.log');

// Функция логирования с обработкой ошибок записи
function log_event($message, $level = 'INFO') {
    $logEntry = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $level, $message);
    
    // Попытка записи в лог-файл
    $result = @file_put_contents(LOG_FILE, $logEntry, FILE_APPEND);
    
    // Если запись не удалась - пишем в стандартный error_log
    if ($result === false) {
        error_log("Cannot write to log file: " . LOG_FILE . ". Message: $logEntry");
    }
}

// Создаем директорию output если не существует
$outputDir = ROOT_DIR . '/output';
if (!is_dir($outputDir)) {
    if (!mkdir($outputDir, 0755, true)) {
        error_log("FATAL: Cannot create directory: $outputDir");
        die("Server configuration error. Please contact administrator.");
    }
    log_event("Created output directory: $outputDir");
}

// Проверяем доступность конфигурационного файла
$configFile = ROOT_DIR . '/price/config.php';
if (!file_exists($configFile)) {
    log_event("Missing config file: $configFile", 'CRITICAL');
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'server configuration error']));
}

// Загружаем конфигурацию как массив
$config = require $configFile;
if (empty($config['login']) || empty($config['password'])) {
    log_event("Invalid credentials in config file", 'CRITICAL');
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'server configuration error']));
}

$login = $config['login'];
$password = $config['password'];
log_event("Configuration loaded successfully");

/**
 * Send authenticated request to MySklad.
 */
function moysklad_request($url, $login, $password) {
    log_event("Requesting URL: $url");
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => "$login:$password",
        CURLOPT_ENCODING => 'gzip',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_TIMEOUT => 15,
        // Allow capturing response bodies even when server returns an error
        // to log the error message from MySklad.
        CURLOPT_FAILONERROR => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $start = microtime(true);
    $resp = curl_exec($ch);
    $duration = round((microtime(true) - $start) * 1000, 2);
    
    if ($resp === false) {
        $error = curl_error($ch);
        $code = curl_errno($ch);
        log_event("CURL error ($code): $url - $error", 'ERROR');
        curl_close($ch);
        return null;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    log_event("Response received: HTTP $httpCode | {$duration}ms");

    if ($httpCode !== 200) {
        log_event("API error: $url | HTTP $httpCode | Response: " . substr($resp, 0, 500), 'ERROR');
        return null;
    }

    $json = json_decode($resp, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_event("JSON decode error: " . json_last_error_msg() . " | URL: $url", 'ERROR');
        return null;
    }

    return $json;
}

// Инициализация базы данных
$dbFile = ROOT_DIR . '/output/webhook_products.json';
$products = [];

log_event("=== Webhook processing started ===");

// Загрузка существующей базы данных
if (file_exists($dbFile)) {
    $content = file_get_contents($dbFile);
    if ($content !== false) {
        $products = json_decode($content, true) ?? [];
        log_event(sprintf("Database loaded: %d records", count($products)));
    } else {
        log_event("Failed to read database file", 'ERROR');
    }
} else {
    log_event("Database file not found. New file will be created.");
}

// Получение входных данных
$input = file_get_contents('php://input');
if (empty($input)) {
    log_event("No input data received", 'WARNING');
    $input = '{}';
}

log_event("Raw input: " . $input);

$data = json_decode($input, true);
if ($data === null) {
    $jsonError = json_last_error_msg();
    log_event("Invalid JSON: $jsonError | Input: " . substr($input, 0, 200), 'ERROR');
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'invalid json']));
}

// Обработка событий
$event = $data['events'][0] ?? [];
$href = $event['meta']['href'] ?? ($data['meta']['href'] ?? null);
$action = strtoupper($event['action'] ?? '');

log_event(sprintf("Event received: action=%s | href=%s", $action, $href ?? 'null'));

if (empty($href)) {
    log_event("Missing href in payload", 'ERROR');
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'no href']));
}

// Извлечение ID
$path = parse_url($href, PHP_URL_PATH);
$id = $path ? basename($path) : null;

if (empty($id)) {
    log_event("Failed to extract ID from href: $href", 'ERROR');
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'invalid href']));
}

log_event("Entity ID: $id | Action: $action");

// Обработка удаления
if ($action === 'DELETE') {
    $status = 'not_found';
    if (isset($products[$id])) {
        unset($products[$id]);
        log_event("Deleted record: $id");
        $status = 'deleted';
    } else {
        log_event("Delete failed: record $id not found", 'WARNING');
    }

    $result = file_put_contents(
        $dbFile, 
        json_encode($products, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );

    if ($result === false) {
        log_event("Failed to save database after delete", 'ERROR');
        http_response_code(500);
        die(json_encode(['status' => 'error', 'message' => 'save failed']));
    } else {
        log_event("Database saved after $status. Total records: " . count($products));
        echo json_encode(['status' => $status]);
    }
    exit;
}

// Обработка создания/обновления
$expand = '?expand=country,attributes,images,product,product.attributes,product.images';
$requestUrl = $href . $expand;
log_event("Fetching expanded data: $requestUrl");

$item = moysklad_request($requestUrl, $login, $password);
if ($item) {
    $entityType = $item['meta']['type'] ?? 'unknown';
    $entityName = $item['name'] ?? 'no name';
    
    $products[$id] = $item;
    log_event("Received data for ID: $id | Type: $entityType | Name: $entityName");

    $result = file_put_contents(
        $dbFile, 
        json_encode($products, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );

    if ($result === false) {
        log_event("Failed to save database", 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'save failed']);
    } else {
        log_event("Database updated successfully. Total records: " . count($products));
        echo json_encode(['status' => 'stored']);
    }
} else {
    log_event("Failed to fetch expanded data for ID: $id", 'ERROR');
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => 'data fetch failed']);
}

log_event("=== Webhook processing completed ===");
?>