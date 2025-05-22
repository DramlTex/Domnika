<?php

$config = include __DIR__ . '/config.php';
$login = $config['login'];
$password = $config['password'];

// Из GET-параметров берем URL, по которому находится картинка
if (!isset($_GET['url'])) {
    header("HTTP/1.1 400 Bad Request");
    echo "Parameter 'url' is required.";
    exit;
}
$moyskladImageUrl = $_GET['url'];

// С помощью cURL идём в МойСклад за картинкой
$ch = curl_init($moyskladImageUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, $login . ':' . $password);
curl_setopt($ch, CURLOPT_ENCODING, 'gzip');

// Включаем автопереход по редиректам:
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

// Если нужно отключить SSL проверку (только на тестах):
// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
// curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$imageData  = curl_exec($ch);
$httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType= curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

if (curl_errno($ch)) {
    // При ошибке возвращаем 500
    header("HTTP/1.1 500 Internal Server Error");
    echo "cURL error: " . curl_error($ch);
    curl_close($ch);
    exit;
}

curl_close($ch);

// Если МойСклад вернул 200, пробуем отдать картинку
if ($httpCode == 200 && $imageData) {
    // Устанавливаем правильный Content-Type, чтобы браузер знал, что это изображение
    header("Cache-Control: public, max-age=604800");
    header("Expires: ".gmdate('D, d M Y H:i:s', time() + 604800)." GMT");
    header("Content-Type: $contentType");
    echo $imageData;
} else {
    // Если не 200, отдаем 404 или другую ошибку
    header("HTTP/1.1 404 Not Found");
    echo "Failed to load image from MoySklad. HTTP code: $httpCode";
}
