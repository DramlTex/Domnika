<?php
// Simple webhook handler for product and variant events
// Stores expanded entity data in output/webhook_products.json

define('ROOT_DIR', __DIR__);

require ROOT_DIR . '/Price/config.php';

/**
 * Send authenticated request to MySklad.
 */
function moysklad_request($url, $login, $password)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $login . ':' . $password);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) {
        error_log("webhook request failed: $url code $code");
        return null;
    }

    $json = json_decode($resp, true);
    return $json;
}

// Load existing database
$dbFile = ROOT_DIR . '/output/webhook_products.json';
$products = [];
if (file_exists($dbFile)) {
    $products = json_decode(file_get_contents($dbFile), true) ?: [];
}

$input = file_get_contents('php://input');
error_log('[webhook] ' . $input);
$data = json_decode($input, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'invalid json']);
    exit;
}

// MySklad sends array of events
$event = $data['events'][0] ?? [];
$href  = $event['meta']['href'] ?? ($data['meta']['href'] ?? null);
$action = strtoupper($event['action'] ?? '');

if (!$href) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'no href']);
    exit;
}

// Determine ID from href
$id = basename(parse_url($href, PHP_URL_PATH));

if ($action === 'DELETE') {
    unset($products[$id]);
    file_put_contents($dbFile, json_encode($products, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo json_encode(['status' => 'deleted']);
    exit;
}

$expand = '?expand=country,attributes,images,product,product.attributes,product.images';
$item = moysklad_request($href . $expand, $login, $password);
if ($item) {
    $products[$id] = $item;
    file_put_contents($dbFile, json_encode($products, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo json_encode(['status' => 'stored']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error']);
}
?>
