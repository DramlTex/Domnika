<?php
header('Content-Type: application/json');
$query = trim($_GET['q'] ?? '');
$results = [];
if ($query !== '') {
    $file = __DIR__ . '/output/filtered_products.json';
    if (file_exists($file)) {
        $products = json_decode(file_get_contents($file), true);
        foreach ($products as $p) {
            if (stripos($p['name'], $query) !== false) {
                $results[] = ['id' => $p['articul'], 'text' => $p['name']];
                if (count($results) >= 20) break;
            }
        }
    }
}
echo json_encode($results, JSON_UNESCAPED_UNICODE);
