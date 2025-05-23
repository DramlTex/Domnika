<?php
define('DIR', __DIR__);

// 1. Логирование
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', DIR.'/php-error.log');
error_log("=== Запуск скрипта data.php ===");

// 2. Заголовки
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');

// 3. Данные авторизации и базовый URL
$config   = include DIR.'/config.php';
$login    = $config['login'];
$password = $config['password'];
$base_url = 'https://api.moysklad.ru/api/remap/1.2/';

// 4. Функция запроса
function moysklad_request($url, $login, $password) {
    error_log("Отправляем запрос: $url");
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $login . ':' . $password);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errNo    = curl_errno($ch);
    $errMsg   = $errNo ? curl_error($ch) : '';
    curl_close($ch);

    if ($errNo) {
        error_log("Ошибка cURL: $errMsg");
        return ['error'=>'cURL','message'=>$errMsg];
    }
    error_log("HTTP code: $code");

    if ($code != 200) {
        error_log("HTTP ошибка: код $code, ответ: $response");
        return ['error'=>'HTTP','code'=>$code,'raw'=>$response];
    }
    $json = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $msg = json_last_error_msg();
        error_log("Ошибка JSON-декодирования: $msg");
        return ['error'=>'JSON','message'=>$msg,'raw'=>$response];
    }

    // Логируем сам ответ (может быть большим)
    //error_log("Ответ JSON: ".print_r($json, true));
    return $json;
}

// 5. Функция постраничной выборки
function fetchAllAssortment($login, $password, $base_url, $params=[]) {
    error_log("Начало fetchAllAssortment");
    $params['limit'] = 100;
    $url    = $base_url.'entity/assortment?'.http_build_query($params);
    $result = [];

    while ($url) {
        $resp = moysklad_request($url, $login, $password);
        if (!empty($resp['error'])) {
            error_log("Ошибка при получении данных: ".print_r($resp, true));
            break;
        }
        if (empty($resp['rows'])) {
            error_log("rows пусты, завершаем цикл");
            break;
        }
        $countBefore = count($result);
        $result      = array_merge($result, $resp['rows']);
        $countAfter  = count($result);
        error_log("Добавлено: ".($countAfter - $countBefore)." элементов (итого: $countAfter)");

        $url = !empty($resp['meta']['nextHref']) ? $resp['meta']['nextHref'] : null;
    }

    error_log("Итого получено: ".count($result)." элементов в fetchAllAssortment");
    return $result;
}

// 6. Идентификаторы складов
$storeIds = [
    'store1'=>'b4ec11ca-1996-11ee-0a80-10a600356d49',
    'store2'=>'bd85783b-a0a6-11ee-0a80-116500205ac6',
    'store3'=>'55ea222f-b319-11eb-0a80-087e000db893',
    'store4'=>'0dba741b-a6a9-11ec-0a80-0c9a000afb24',
];

// 7. Массив скомбинированных товаров
$combinedItems = [];

// 8. Функция обработки результатов
function processItemsFromStore($items, $storeKey, &$combined, $login, $password, $base_url) {
    error_log("Обработка склада $storeKey, всего: ".count($items)." позиций");

    foreach ($items as $item) {
        $type     = $item['meta']['type'] ?? '';
        $itemId   = $item['id'] ?? '';
        $prodId   = $item['product']['id'] ?? '';

        if ($type === 'variant') {
            if (!$prodId) {
                continue; // нет ссылки на родителя
            }
            $groupKey = $prodId;
            $source   = $item['product'] ?? [];
            if (!isset($combined[$groupKey]) && empty($source)) {
                $url    = $base_url.'entity/product/'.$prodId.'?expand=country,attributes,images';
                $source = moysklad_request($url, $login, $password);
                if (!empty($source['error'])) {
                    error_log("Не удалось получить товар $prodId: ".print_r($source, true));
                    continue;
                }
            }
            error_log("Вариант. Группируем по товару ID=$prodId");
        } else {
            $groupKey = $itemId; // обычный товар
            $source   = $item;
            error_log("Товар. Группируем по ID=$itemId");
        }
        if (!isset($combined[$groupKey])) {
            $description  = $source['description']         ?? '';
            $articul      = $source['article']         ?? '';
            $name         = $source['name']            ?? '';
            $uom          = $source['uom']['name']      ?? '';
            $supplier     = $source['country']['name']  ?? '';
            $tip=''; $mass=''; $vol=''; $photoAttr='';

            // Доп. поля
            if (!empty($source['attributes']) && is_array($source['attributes'])) {
                foreach ($source['attributes'] as $attr) {
                    $n = $attr['name']  ?? ''; 
                    $v = $attr['value'] ?? '';
                    if ($n==='тип')              { $tip = is_array($v)?($v['name']??''):$v; }
                    if ($n==='Вес тарного места') { $mass = $v; }
                    if ($n==='Объём тарного места'){ $vol  = $v; }
                    if ($n==='Фото')             { $photoAttr = $v; }
                }
            }

            // Цена
            $price = 0;
            if (!empty($source['salePrices']) && is_array($source['salePrices'])) {
                $discountPriceFound = false;
                foreach ($source['salePrices'] as $sp) {
                    if (!empty($sp['priceType']['name']) &&
                        mb_stripos($sp['priceType']['name'], 'Предоп.под заказ (Опт 1)') !== false) {
                        $price = $sp['value']/100;
                        $discountPriceFound = true; 
                        break;
                    }
                }
                if (!$discountPriceFound) {
                    $first = $source['salePrices'][0] ?? null;
                    if ($first && isset($first['value'])) {
                        $price = $first['value']/100;
                    }
                }
            }

            // Изображения
            $mini=''; 
            $full='';
            if (!empty($source['images']['rows'][0])) {
                $img  = $source['images']['rows'][0];
                $mini = $img['miniature']['downloadHref'] ?? '';
                $full = $img['meta']['downloadHref']      ?? '';
            }

            $combined[$groupKey] = [
                'description'=>$description,
                'articul'=>$articul,
                'name'=>$name,
                'uom'=>$uom,
                'supplier'=>$supplier,
                'tip'=>$tip,
                'mass'=>$mass,
                'volumeWeight'=>$vol,
                'photoUrl'=>$photoAttr,
                'price'=>$price,
                'stock_store1'=>0,
                'stock_store2'=>0,
                'stock_store3'=>0,
                'stock_store4'=>0,
                'photoMini'=>$mini,
                'photoFull'=>$full
            ];

            error_log("Создана новая запись для ключа=$groupKey");
        }

        $stock = 0;
        if (isset($item['quantity'])) {
            $stock = $item['quantity'];
        }
        $fieldName = 'stock_'.$storeKey;
        $combined[$groupKey][$fieldName] += $stock;
        error_log("Добавлен остаток $stock к полю $fieldName (ключ=$groupKey)");
    }
}

// 9. Цикл по складам
foreach ($storeIds as $key => $uuid) {
    $params = [
        'filter' => 'stockStore='.$base_url.'entity/store/'.$uuid.';quantityMode=positiveOnly;stockMode=all;',
        // Обратите внимание: можем расширить expand, чтобы поля родительского товара были доступны
        'expand' => 'country,images,product,product.uom,product.attributes,product.images'
    ];
    $items = fetchAllAssortment($login, $password, $base_url, $params);
    processItemsFromStore($items, $key, $combinedItems, $login, $password, $base_url);
}

// 10. Формируем итог
// Перебор товаров и добавление в итоговый массив
foreach ($combinedItems as $uniqueId => $d) {
    // Суммируем остатки по всем складам
    $totalStock = $d['stock_store1'] + $d['stock_store2'] + $d['stock_store3'] + $d['stock_store4'];

    // Проверяем: если цена <= 0 или общий остаток <= 0, пропускаем товар
    if ($d['price'] <= 0 || $totalStock <= 0) {
        continue;
    }
    // Если товар прошел проверку, добавляем его в итоговый массив
    $rows[] = [
        'description'  => $d['description'],
        'articul'      => $d['articul'],
        'name'         => $d['name'],
        'uom'          => $d['uom'],
        'tip'          => $d['tip'],
        'supplier'     => $d['supplier'],
        'mass'         => $d['mass'],
        'price'        => $d['price'],
        'stock'        => $totalStock, // Общий остаток
        'stock_store1' => $d['stock_store1'],
        'stock_store2' => $d['stock_store2'],
        'stock_store3' => $d['stock_store3'],
        'stock_store4' => $d['stock_store4'],
        'volumeWeight' => $d['volumeWeight'],
        'photoMini'    => $d['photoMini'],
        'photoFull'    => $d['photoFull'],
    ];
}
error_log("Итоговая выборка: ".count($rows)." строк");

// 11. Вывод
echo json_encode(['rows'=>$rows], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
error_log("=== Завершение скрипта data.php ===");
