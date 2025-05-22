
 <?php
 /**
  * Скрипт для получения товаров из МоегоСклада и сохранения только нужных групп в JSON
  */
 
 // Конфигурация

 $login = 'domnika.n@jfk.in';
 $password = 'Zima9091';
 
 // Папка для сохранения JSON-файла
 $outputDir = __DIR__ . '/output';
 if (!is_dir($outputDir)) {
     mkdir($outputDir, 0777, true);
 }
 
 // Файл, куда будем сохранять отфильтрованные товары
 $filteredFile = $outputDir . '/filtered_products.json';
 
 // Список интересующих нас путей (pathName)
 $allowedPrefixes = [
     'ОПТ JFK',
     'Товары Трайд',
 ];
 
 /**
  * Функция для выполнения GET-запроса к API МоегоСклада
  */
 function getApiData($url, $login, $password) {
     $ch = curl_init();
 
     $headers = [
         'Authorization: Basic ' . base64_encode("$login:$password"),
         'Accept-Encoding: gzip',
         'Content-Type: application/json',
         'Accept: application/json;charset=utf-8',
     ];
 
     curl_setopt($ch, CURLOPT_URL, $url);
     curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
     curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
     curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
 
     $response = curl_exec($ch);
 
     if (curl_errno($ch)) {
         echo 'Ошибка cURL: ' . curl_error($ch) . "\n";
         curl_close($ch);
         return false;
     }
 
     $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
     if ($httpCode !== 200) {
         echo "Ошибка API. HTTP Код: $httpCode\nОтвет: $response\n";
         curl_close($ch);
         return false;
     }
 
     curl_close($ch);
 
     $data = json_decode($response, true);
     if (json_last_error() !== JSON_ERROR_NONE) {
         echo "Ошибка декодирования JSON: " . json_last_error_msg() . "\n";
         return false;
     }
 
     return $data;
 }
 
 /**
  * Функция для получения всех товаров с учётом постраничной навигации
  */
 function getAllProducts($login, $password) {
     $allProducts = [];
     $baseUrl = 'https://api.moysklad.ru/api/remap/1.2/entity/product';
     $url = $baseUrl;
 
     while ($url) {
         $response = getApiData($url, $login, $password);
         if (!$response) {
             // Ошибка при запросе или некорректный ответ
             break;
         }
 
         if (isset($response['rows'])) {
             $allProducts = array_merge($allProducts, $response['rows']);
         } else {
             // Нет ключа 'rows' - вероятно, неверный ответ
             break;
         }
 
         // Проверяем наличие ссылки на следующую страницу
         if (isset($response['meta']['nextHref'])) {
             $url = $response['meta']['nextHref'];
         } else {
             $url = null;
         }
     }
 
     return $allProducts;
 }
 
 // 1. Получаем все товары
 echo "Получаем товары из МоегоСклада...\n";
 $allProducts = getAllProducts($login, $password);
 echo "Всего товаров получено: " . count($allProducts) . "\n";
 
 // 2. Фильтруем товары по нужным pathName
 echo "Фильтруем товары по pathName...\n";
 $filteredProducts = [];
 foreach ($allProducts as $product) {
    // Сразу проверим, есть ли pathName
    if (!isset($product['pathName'])) {
        continue;
    }

    // Для отладки — покажем, что реально в pathName
    $rawPath = $product['pathName'];

    // Попробуем убрать пробелы по краям (вдруг там что-то мешает)
    $cleanPath = trim($rawPath);

    // Флаг — подходит ли товар
    $matched = false;

    // Перебираем все "префиксы"
    foreach ($allowedPrefixes as $prefix) {
        $cleanPrefix = trim($prefix);

        // Проверим starts-with
        // Можно str_starts_with($cleanPath, $cleanPrefix) на PHP >= 8.0
        if (strpos($cleanPath, $cleanPrefix) === 0) {
            // Совпало
            $matched = true;

            echo "[DEBUG] --> '{$product['name']}' прошёл фильтр. pathName = '$rawPath'\n";
            
            // Сформируем итоговый массив
            $filteredItem = [
                'meta'     => $product['meta']     ?? null,
                'name'     => $product['name']     ?? null,
                'pathName' => $product['pathName'] ?? null,
            ];
            if (isset($product['packs'])) {
                $filteredItem['packs'] = $product['packs'];
            }

            $filteredProducts[] = $filteredItem;
            break; // чтобы не добавлять один и тот же товар несколько раз
        }
    }

    // Если хотите видеть, почему не подошёл
    if (!$matched) {
        // echo "[DEBUG NO MATCH] '$cleanPath'\n";
    }
}


// А теперь записываем
file_put_contents($filteredFile, json_encode($filteredProducts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo "Сохранено " . count($filteredProducts) . " товаров в файл: {$filteredFile}\n";
echo "Готово!\n";
 