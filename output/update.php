<?php
/**
 * Пример скрипта: Добавить недостающие упаковки (packs) товарам
 * из файла filtered_products.json и отправить обновления в МойСклад
 * (пачками по 100 штук).
 *
 * Учитываем и uom, и quantity. Если в товаре уже есть pack
 * с той же uom.meta.href и тем же quantity, повторно не добавляем.
 */

//////////////////////
// 1. Конфигурация
//////////////////////


$login = 'domnika.n@jfk.in';
$password = 'Zima9091';

// Пути к файлам
$productsFile = __DIR__ . '/filtered_products.json';  // ранее сохранённые товары
$packsFile    = __DIR__ . '/common_packs.json';       // эталонные упаковки

// Максимальное количество товаров в одном запросе
const BATCH_SIZE = 1000;

// URL для массового обновления товаров (POST /entity/product)
const MOYSKLAD_PRODUCT_URL = 'https://api.moysklad.ru/api/remap/1.2/entity/product';


//////////////////////
// 2. Функции
//////////////////////

/**
 * Функция для POST-запроса (Bulk Update) к МойСклад: /entity/product
 * Принимает массив товаров для обновления (не более 100).
 */
function postProductsBatch(array $batch, string $login, string $password) {
    $url = MOYSKLAD_PRODUCT_URL;
    $ch = curl_init($url);

    $headers = [
        'Authorization: Basic ' . base64_encode("$login:$password"),
        'Accept-Encoding: gzip',
        'Content-Type: application/json',
        'Accept: application/json;charset=utf-8',
    ];

    // Готовим тело запроса — массив товаров
    $payload = json_encode($batch, JSON_UNESCAPED_UNICODE);

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    // POST с массивом $batch
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Ошибка cURL: ' . curl_error($ch) . "\n";
        curl_close($ch);
        return false;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode < 200 || $httpCode >= 300) {
        echo "Ошибка API при массовом обновлении. HTTP Код: $httpCode\n";
        echo "Ответ: $response\n";
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    // Можно проверить тело ответа, если нужно
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "Ошибка декодирования ответа JSON: " . json_last_error_msg() . "\n";
        return false;
    }

    // Возвращаем расшифрованный ответ
    return $data;
}


//////////////////////
// 3. Загрузка данных
//////////////////////

// Читаем сохранённый ранее список товаров (filtered_products.json)
if (!file_exists($productsFile)) {
    die("Файл с товарами ($productsFile) не найден.\n");
}
$productsData = json_decode(file_get_contents($productsFile), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Ошибка декодирования JSON товаров: " . json_last_error_msg() . "\n");
}

// Читаем эталонные упаковки (common_packs.json)
if (!file_exists($packsFile)) {
    die("Файл с упаковками ($packsFile) не найден.\n");
}
$commonPacks = json_decode(file_get_contents($packsFile), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Ошибка декодирования JSON упаковок: " . json_last_error_msg() . "\n");
}

/**
 * Формат common_packs.json может быть примерно таким:
 * [
 *   {
 *     "uom": {
 *       "meta": {
 *         "href": "...",
 *         "type": "uom",
 *         "mediaType": "application/json"
 *       }
 *     },
 *     "quantity": 1
 *   },
 *   {
 *     "uom": {
 *       "meta": {
 *         "href": "...",
 *         "type": "uom",
 *         "mediaType": "application/json"
 *       }
 *     },
 *     "quantity": 2
 *   }
 * ]
 */


/////////////////////////////////////////////
// 4. Формируем массив для обновления товаров
/////////////////////////////////////////////

$updates = [];

foreach ($productsData as $product) {
    // Проверяем, есть ли meta
    if (!isset($product['meta']) || !is_array($product['meta'])) {
        // Без meta не сможем обновить товар
        continue;
    }

    // Текущее packs (если нет, берём пустой массив)
    $currentPacks = isset($product['packs']) && is_array($product['packs'])
        ? $product['packs']
        : [];

    // Соберём все комбинации (uom_href + quantity), чтобы не дублировать
    $existingCombinations = [];
    foreach ($currentPacks as $p) {
        // Пытаемся извлечь uom.href
        $uomHref   = $p['uom']['meta']['href'] ?? '';
        $qty       = $p['quantity'] ?? 0;
        // Уникальный ключ (uomHref + quantity)
        $existingCombinations[] = $uomHref . '|' . $qty;
    }

    $changed = false;

    // Проверяем каждую эталонную упаковку
    foreach ($commonPacks as $cp) {
        $uomHref = $cp['uom']['meta']['href'] ?? '';
        $qty     = $cp['quantity'] ?? 0;
        $combo   = $uomHref . '|' . $qty;

        // Если такой комбинации (uom + quantity) нет, добавляем
        if (!in_array($combo, $existingCombinations, true)) {
            $currentPacks[]         = $cp;        // Добавляем упаковку
            $existingCombinations[] = $combo;     // Чтобы не добавлять дубль
            $changed = true;
        }
    }

    if ($changed) {
        // Если мы что-то добавили
        $updates[] = [
            'meta'  => $product['meta'],  // Нужно для обновления
            'packs' => $currentPacks,
        ];
    }
}

// Если вдруг нет товаров, которым нужно что-то добавлять
if (empty($updates)) {
    echo "Нет товаров, которым нужно добавлять упаковки (с учётом уom и quantity).\n";
    exit;
}


/////////////////////////////////////////////
// 5. Отправляем обновления пачками по 100
/////////////////////////////////////////////

$totalUpdates = count($updates);
echo "Нужно обновить: $totalUpdates товаров.\n";

$chunks = array_chunk($updates, BATCH_SIZE);
$updatedCount = 0;

foreach ($chunks as $i => $batch) {
    echo "Отправляем пачку №" . ($i + 1) . " (товаров: " . count($batch) . ")...\n";

    $response = postProductsBatch($batch, $login, $password);
    if ($response === false) {
        echo "Ошибка обновления в пачке №" . ($i + 1) . "\n";
        // Можно продолжить или прервать — на ваше усмотрение
    } else {
        echo "Пачка №" . ($i + 1) . " успешно обновлена.\n";
        $updatedCount += count($batch);
    }
}

echo "Всего успешно обновлено товаров: $updatedCount\n";
echo "Готово!\n";
