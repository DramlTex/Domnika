# data.php

Файл `Price/data.php` обеспечивает API для веб-интерфейса прайс‑листа. Он собирает остатки товаров по нескольким складам в "МойСклад" и возвращает их в формате JSON.

## Основные шаги

1. **Логирование и настройки PHP** – включается вывод ошибок в файл `php-error.log` и фиксируется начало/конец работы скрипта.
2. **Заголовки ответа** – разрешены CORS‑запросы и установлен тип `application/json`.
3. **Авторизация** – логин и пароль берутся из `config.php`, формируется базовый URL API `https://api.moysklad.ru/api/remap/1.2/`.
4. **Функция `moysklad_request()`** – отправляет HTTP запрос к API с авторизацией, проверяет код ответа и декодирует JSON. При ошибках пишет в лог.
5. **Функция `fetchAllAssortment()`** – постранично запрашивает ассортимент,
   объединяя результаты в единый массив. Используется параметр `expand` для
   загрузки связанных сущностей (страна, картинки, родительский товар).

### Подробная логика `fetchAllAssortment()`

1. Устанавливает параметр `limit=100`, чтобы запрашивать данные частями.
2. Формирует исходный URL `entity/assortment` с переданными фильтрами и
   параметром `expand`.
3. Выполняет запрос через `moysklad_request()`, получая первую страницу
   ассортимента.
4. Сохраняет элементы из поля `rows` в итоговый массив `$result` и проверяет
   наличие `nextHref` в метаданных ответа.
5. Если `nextHref` присутствует, цикл повторяется: запрашивается следующая
   страница, и полученные позиции добавляются в `$result`.
6. Перед переходом к новой странице функция следит, чтобы параметр `expand`
   присутствовал в URL, иначе дополняет его вручную.
7. После обхода всех страниц возвращает полный список товаров и модификаций.
6. **Массив `$storeIds`** – список идентификаторов складов. По каждому складу скрипт выполняет отдельный запрос, чтобы получить остатки.
7. **Функция `checkProductAttributes()`** – из атрибутов товара вытягивает «Группу для счетов», «Группу», минимальное количество, ссылку на фото и «Стандарт». Товар включается в результат только если «Группа для счетов» равна «Прайс».
8. **Функция `createCombinedEntry()`** – формирует единую запись товара: название, артикул, единицу измерения, страну, тип, вес, фото, цену, ссылки и т.п. Для модификаций используются данные родителя.
9. **Функция `processItemsFromStore()`** – проходит по всем позициям со склада. Для модификации при необходимости загружает родительский товар, затем суммирует остаток по складам. Для обычного товара сразу создаёт либо обновляет запись в массиве `$combined`.

### Обработка модификаций и товаров

1. Цикл проходит по каждому элементу списка, полученного со склада.
2. Если тип элемента `variant`, скрипт определяет идентификатор родителя и
   при его отсутствии пропускает запись.
3. Когда родитель ещё не загружен, выполняется запрос на `href` родителя с
   `expand=country,attributes,images`. Полученные данные проверяются функцией
   `checkProductAttributes()`.
4. Если родитель удовлетворяет условию «Группа для счетов = Прайс», создаётся
   запись с базовыми полями через `createCombinedEntry()`.
5. Остаток модификации прибавляется к соответствующему полю склада в записи
   родителя.
6. Для обычных товаров выполняются те же проверки атрибутов. Если товар
   подходит, создаётся (или обновляется) его запись и увеличивается остаток по
   текущему складу.
10. **Основной цикл** – обходит все склады из `$storeIds`, вызывает `fetchAllAssortment()` и `processItemsFromStore()`.
11. **Формирование итогового массива `$rows`** – для каждого уникального товара подсчитывается общий остаток и выбираются нужные поля.
12. **Ответ JSON** – в конце скрипт выводит массив `rows` с детальной информацией о каждом товаре и завершает логирование.

Скрипт используется фронтендом прайс‑листа для загрузки актуальных данных по всем складам. Аналогичная, но упрощённая версия расположена в папке `Price/evt`.

### Последующий патч

При выгрузке остатков может оказаться, что модификация имеет количество на складе,
но её родительский товар не был получен из API и потому отсутствует в таблице.
Чтобы такая позиция не пропадала, необходимо догрузить основной товар вручную:

1. В функции `processItemsFromStore()` после определения типа `variant` проверьте,
   существует ли запись родителя в массиве `$combined`.
2. Если записи нет, выполните запрос на `$base_url.'entity/product/'.$prodId.'?expand=country,attributes,images'`.
3. Проверьте ответ с помощью `checkProductAttributes()` и при успешном результате
   создайте запись `createCombinedEntry()` для родителя.
4. Затем добавьте остаток модификации к полям склада родителя, как обычно.

Так модификация с остатком будет корректно связана с основным товаром и
отобразится в таблице.
