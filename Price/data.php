<?php
define('DIR', __DIR__);

/**
 * 1. Логирование и настройки ошибок
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', DIR.'/php-error.log');
error_log("=== Запуск скрипта data.php ===");

/**
 * 2. Заголовки
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');

/**
 * 3. Данные авторизации и базовый URL
 */
$config   = include DIR.'/config.php';
$login    = $config['login'];
$password = $config['password'];
$base_url = 'https://api.moysklad.ru/api/remap/1.2/';

/**
 * 4. Функция для выполнения запросов к API МойСклад
 */
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

    return $json;
}

/**
 * 5. Функция постраничного обхода ассортимента
 *    Возвращает массив всех позиций (товаров, модификаций) по заданным параметрам $params.
 */
function fetchAllAssortment($login, $password, $base_url, $params=[]) {
    error_log("Начало fetchAllAssortment");

    // Лимит 100 позиций на страницу
    $params['limit'] = 100;
    $url = $base_url.'entity/assortment?' . http_build_query($params);

    $result       = [];
    $expandParams = '';

    // Сохраняем expand, если он был передан
    if (!empty($params['expand'])) {
        $expandParams = '&expand=' . urlencode($params['expand']);
    }

    // Цикл по страницам, пока есть nextHref
    while ($url) {
        $resp = moysklad_request($url, $login, $password);
        if (!empty($resp['error'])) {
            error_log("Ошибка при получении данных: ".print_r($resp, true));
            break;
        }

        // Добавляем полученные товары/модификации в $result
        $result = array_merge($result, $resp['rows'] ?? []);

        // Проверяем, есть ли ссылка на следующую страницу
        if (!empty($resp['meta']['nextHref'])) {
            $url = $resp['meta']['nextHref'];
            // Если в nextHref нет expand, допишем его
            if (strpos($url, 'expand=') === false) {
                $url .= $expandParams;
            }
        } else {
            $url = null;
        }
    }

    error_log("Итого получено: ".count($result)." элементов");
    return $result;
}

/**
 * 6. Список складов, по которым будем собирать остатки
 */
$storeIds = [
    'store1' => 'ae17fecd-6546-11e6-7a69-971100105a05',
    'store2' => '9495d8b0-96a7-11ea-0a80-05a4000860e5',
    'store3' => 'ac7c8eb2-ef03-11ed-0a80-034d00b42c42',
    'store4' => 'edc74a61-4cf6-11ec-0a80-05d0001d524d',
];

/**
 * 7. Подготовим массив для итоговых данных
 */
$combinedItems = [];

/**
 * Проверка атрибутов товара (или родителя модификации):
 * Нужно, чтобы "Группа для счетов" была "Прайс". Если это так, товар включаем в итог.
 * Дополнительно вытаскиваем нужные поля (группа, минимальное кол-во, фото и т.д.).
 */
function checkProductAttributes($source) {
    $result = [
        'include'    => false,     // Флаг, включать ли товар в итог
        'group'      => 'Остальное',
        'minOrderQty'=> '',
        'photoLink'  => '',
        'standard'   => '',
    ];

    if (empty($source['attributes']) || !is_array($source['attributes'])) {
        return $result;
    }

    foreach ($source['attributes'] as $attr) {
        $attrName  = $attr['name']  ?? '';
        $attrValue = $attr['value'] ?? '';

        // "Стандарт"
        if ($attrName === 'Стандарт' && !empty($attrValue['name'])) {
            $result['standard'] = $attrValue['name'];
        }
        // Минимальное кол-во для заказа
        elseif ($attrName === 'минимальное кол-во для заказа') {
            $result['minOrderQty'] = is_array($attrValue) ? ($attrValue['name'] ?? $attrValue) : $attrValue;
        }
        // Ссылка на фото
        elseif ($attrName === 'Ссылка на фото' || $attrName === 'Фото') {
            $result['photoLink'] = is_array($attrValue) 
                ? ($attrValue['name'] ?? $attrValue['href'] ?? $attrValue) 
                : $attrValue;
        }
        // Проверяем "Группа для счетов"
        elseif ($attrName === 'Группа для счетов') {
            if (!empty($attrValue['name']) && $attrValue['name'] === 'Прайс') {
                $result['include'] = true;
                // Дополнительно ищем, какой атрибут "Группа"
                foreach ($source['attributes'] as $groupAttr) {
                    if ($groupAttr['name'] === 'Группа' && !empty($groupAttr['value']['name'])) {
                        $result['group'] = $groupAttr['value']['name'];
                        break;
                    }
                }
            }
            break; // после "Группа для счетов" можно выйти из цикла
        }
    }

    return $result;
}

/**
 * Создаёт запись в $combinedItems на основе данных товара/родителя
 */
function createCombinedEntry($source, $checkAttrs) {
    $description  = $source['description']     ?? '';
    $articul      = $source['article']        ?? '';
    $name         = $source['name']           ?? '';
    // В коде "uom" заменён на "Стандарт" (или берём из родителя)
    $uom          = $checkAttrs['standard'];
    $supplier     = $source['country']['name'] ?? '';
    $pathName     = $source['pathName']        ?? '';
    $href         = $source['meta']['href']    ?? '';
    $href         = explode('?', $href)[0]; // убираем GET-параметры, если есть

    // Цена — ищем в salePrices (сначала скидочную, если есть)
    $price = 0;
    if (!empty($source['salePrices']) && is_array($source['salePrices'])) {
        $discountPriceFound = false;
        foreach ($source['salePrices'] as $sp) {
            if (!empty($sp['priceType']['name']) 
                && mb_stripos($sp['priceType']['name'], 'скид') !== false
            ) {
                $price = $sp['value'] / 100;
                $discountPriceFound = true;
                break;
            }
        }
        if (!$discountPriceFound) {
            $first = $source['salePrices'][0] ?? null;
            if ($first && isset($first['value'])) {
                $price = $first['value'] / 100;
            }
        }
    }

    // Подтягиваем фото, если есть
    $mini = '';
    $full = '';
    if (!empty($source['images']['rows'][0])) {
        $img  = $source['images']['rows'][0];
        $mini = $img['miniature']['downloadHref'] ?? '';
        $full = $img['meta']['downloadHref']      ?? '';
    }

    // Поля из checkProductAttributes
    $photoLink   = $checkAttrs['photoLink'];
    $minOrderQty = $checkAttrs['minOrderQty'];

    // Дополнительные атрибуты (тип, вес, объём) при необходимости
    $tip = '';
    $mass= '';
    $vol = '';
    if (!empty($source['attributes']) && is_array($source['attributes'])) {
        foreach ($source['attributes'] as $attr) {
            $an = $attr['name']  ?? '';
            $av = $attr['value'] ?? '';
            if ($an === 'Тип') {
                $tip = is_array($av) ? ($av['name'] ?? '') : $av;
            } elseif ($an==='Вес тарного места') {
                $mass = $av;
            } elseif ($an==='Объём тарного места') {
                $vol  = $av;
            }
        }
    }

    return [
        'description'  => $description,
        'articul'      => $articul,
        'name'         => $name,
        'uom'          => $uom,
        'supplier'     => $supplier,
        'tip'          => $tip,
        'mass'         => $mass,
        'volumeWeight' => $vol,
        'photoUrl'     => $photoLink,
        'price'        => $price,
        'stock_store1' => 0,
        'stock_store2' => 0,
        'stock_store3' => 0,
        'stock_store4' => 0,
        'photoMini'    => $mini,
        'photoFull'    => $full,
        'group'        => $checkAttrs['group'],
        'pathName'     => $pathName,
        'href'         => $href,
        'min_order_qty'=> $minOrderQty,
    ];
}

/**
 * 8. Обработка полученных данных по каждому складу:
 *    - Если "variant" (модификация), добавляем остаток к родительскому товару (если он подходит).
 *    - Если обычный товар, создаём или обновляем запись в итоговом массиве $combined.
 */
function processItemsFromStore($items, $storeKey, &$combined, $login, $password) {
    foreach ($items as $item) {
        $type   = $item['meta']['type'] ?? '';
        $itemId = $item['id'] ?? '';
        $stock  = $item['quantity'] ?? 0; // остаток на текущем складе

        // Если это модификация (variant)
        if ($type === 'variant') {
            // Узнаём ID родителя
            $prodId = $item['product']['id'] ?? '';
            if (!$prodId) {
                continue; // не можем найти родителя — пропускаем
            }
            $groupKey = $prodId;

            // Если родитель ещё не загружен и не в $combined, догружаем и проверяем
            if (!isset($combined[$groupKey])) {
                $productHref = $item['product']['meta']['href'] ?? '';
                if (!$productHref) {
                    continue;
                }
                $parentData = moysklad_request($productHref . '?expand=country,attributes,images', $login, $password);
                if (!empty($parentData['error'])) {
                    continue; 
                }
                // Проверяем "Группа для счетов = Прайс"
                $check = checkProductAttributes($parentData);
                if (!$check['include']) {
                    continue; // родитель не подходит
                }
                // Создаём запись для родителя
                $combined[$groupKey] = createCombinedEntry($parentData, $check);
            }

            // Добавляем остаток модификации к родительской записи
            if (isset($combined[$groupKey])) {
                $fieldName = 'stock_'.$storeKey;
                $combined[$groupKey][$fieldName] += $stock;
            }
        }
        // Если обычный товар
        else {
            $groupKey = $itemId;
            // Если ещё нет в итоговом массиве — проверяем, подходит ли
            if (!isset($combined[$groupKey])) {
                $check = checkProductAttributes($item);
                if (!$check['include']) {
                    continue; 
                }
                // Создаём запись (сам товар)
                $combined[$groupKey] = createCombinedEntry($item, $check);
            }
            // Добавляем остаток в запись
            $fieldName = 'stock_'.$storeKey;
            $combined[$groupKey][$fieldName] += $stock;
        }
    }
}

/**
 * 9. Обходим все указанные склады и складываем остатки в $combinedItems
 */
foreach ($storeIds as $key => $uuid) {
    $params = [
        'filter' => 'stockStore='.$base_url.'entity/store/'.$uuid.';quantityMode=positiveOnly;stockMode=all;',
        'expand' => 'country,images,product'
    ];
    $items = fetchAllAssortment($login, $password, $base_url, $params);
    processItemsFromStore($items, $key, $combinedItems, $login, $password);
}

/**
 * 10. Формируем финальный массив для вывода
 */
$rows = [];
foreach ($combinedItems as $uniqueId => $d) {
    $totalStock = $d['stock_store1'] 
                + $d['stock_store2']
                + $d['stock_store3']
                + $d['stock_store4'];

    // (При необходимости можно фильтровать по цене или нулевому остатку)

    $rows[] = [
        'description'  => $d['description'],
        'articul'      => $d['articul'],
        'name'         => $d['name'],
        'uom'          => $d['uom'],
        'tip'          => $d['tip'],
        'supplier'     => $d['supplier'],
        'mass'         => $d['mass'],
        'price'        => $d['price'],
        'stock'        => $totalStock,
        'stock_store1' => $d['stock_store1'],
        'stock_store2' => $d['stock_store2'],
        'stock_store3' => $d['stock_store3'],
        'stock_store4' => $d['stock_store4'],
        'volumeWeight' => $d['volumeWeight'],
        'photoMini'    => $d['photoMini'],
        'photoFull'    => $d['photoFull'],
        'group'        => $d['group'],
        'pathName'     => $d['pathName'],
        'href'         => $d['href'],
        'min_order_qty'=> $d['min_order_qty'],
        'photoUrl'     => $d['photoUrl'],
    ];
}

/**
 * 11. Выводим результат в JSON
 */
echo json_encode(['rows'=>$rows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
error_log("=== Завершение скрипта data.php ===");
