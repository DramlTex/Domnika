<?php
/**
 * Скрипт начальной загрузки товаров и модификаций из "МойСклад".
 * Получает полный ассортимент и сохраняет его в output/webhook_products.json.
 */

$config = include dirname(__DIR__) . '/Price/config.php';
$login = $config['login'];
$password = $config['password'];

/**
 * Выполнить GET-запрос к API.
 */
function moysklad_request(string $url, string $login, string $password): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => "$login:$password",
        CURLOPT_ENCODING => 'gzip',
        CURLOPT_HTTPHEADER => ['Accept: application/json;charset=utf-8'],
        CURLOPT_FAILONERROR => false,
    ]);

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $code !== 200) {
        fwrite(STDERR, "API error: HTTP $code\n");
        return null;
    }

    $data = json_decode($resp, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        fwrite(STDERR, "JSON error: " . json_last_error_msg() . "\n");
        return null;
    }
    return $data;
}

/**
 * Получить все товары и модификации.
 */
function fetch_all_items(string $login, string $password): array
{
    $expand = 'country,attributes,images,product,product.attributes,product.images';
    $url = 'https://api.moysklad.ru/api/remap/1.2/entity/assortment?limit=100&expand=' . urlencode($expand);
    $result = [];

    while ($url) {
        $json = moysklad_request($url, $login, $password);
        if (!$json) {
            break;
        }

        foreach ($json['rows'] ?? [] as $row) {
            if (!empty($row['id'])) {
                $result[$row['id']] = $row;
            }
        }

        if (!empty($json['meta']['nextHref'])) {
            $url = $json['meta']['nextHref'];
            if (strpos($url, 'expand=') === false) {
                $url .= '&expand=' . urlencode($expand);
            }
        } else {
            $url = null;
        }
    }

    return $result;
}

$items = fetch_all_items($login, $password);
$outFile = __DIR__ . '/webhook_products.json';
file_put_contents($outFile, json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo "Saved " . count($items) . " items to $outFile\n";
