# data.php

Файл `Price/data.php` обеспечивает API для веб-интерфейса прайс‑листа. Он собирает остатки товаров по нескольким складам в "МойСклад" и возвращает их в формате JSON.

## Основные шаги

1. **Логирование и настройки PHP** – перед запуском проверяется размер файла
   `php-error.log`: если он превышает 10&nbsp;МБ, файл создаётся заново. Затем
   включается вывод ошибок в этот файл и фиксируется начало/конец работы
   скрипта. В процессе выполнения в лог дописываются сведения о каждом
   запрошенном складе, модификациях и родительских товарах.
2. **Заголовки ответа** – разрешены CORS‑запросы и установлен тип `application/json`.
3. **Авторизация** – логин и пароль берутся из `config.php`, формируется базовый URL API `https://api.moysklad.ru/api/remap/1.2/`.
4. **Функция `moysklad_request()`** – отправляет HTTP запрос к API с авторизацией, проверяет код ответа и декодирует JSON. При ошибках пишет в лог. Внутри одного запуска повторные обращения к тому же URL берутся из кеша.
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

### Дополнительное улучшение

Чтобы не выполнять повторные запросы к одному и тому же родительскому товару,
скрипт хранит массив `$checkedParents`. В него заносится результат проверки
каждого родителя. Если модификация встречается повторно, а родитель уже
помечен как неподходящий, API снова не вызывается.
Функция `moysklad_request()` также кеширует ответы по URL и при повторном
обращении в рамках одного запуска возвращает сохранённые данные без сетевого
запроса.

Дополнительно скрипт пишет в журнал количество загруженных товаров из локальной
базы и число строк в отчёте `stock/bystore/current?stockType=quantity&include=zeroLines`. При
этом остаток приходит в поле `quantity` вместо `stock`. При
пропуске элемента из-за отсутствия данных или неподходящей группы в лог
заносится соответствующее сообщение. С июня 2025 отчёт может возвращаться как
массив без поля `rows`. Функция `fetchStockReport()` определяет формат ответа и
обрабатывает оба варианта. Это помогает отладить причину пустых таблиц.

Начиная с версии от 2025‑11‑03 в файл `Price/data.php` добавлена константа
`DEBUG_LOG`. При значении `true` она включает расширенное логирование: в журнал
попадают сведения о каждой обрабатываемой строке отчёта, результат проверки
атрибутов товара и итоговые суммирования остатков.

В версии от 2025‑11‑08 логирование приведено к единому формату. В скрипте
используется функция `log_event()` с типами сообщений `INFO`, `ERROR` и `SKIP`.
Это позволяет увидеть причину исключения товара и этап, на котором
оно произошло.


Начиная с версии от 2025‑11‑09 в лог добавляется сводная статистика:

* сколько уникальных записей создано из товаров и модификаций;
* сколько строк попало в итоговый массив по группам;
* общее число товаров и модификаций в финальной выборке.

В версии от 2025‑11‑12 модификации с положительным остатком суммируются с родительским товаром, даже если его собственный остаток равен нулю.

В версии от 2025‑11‑13 при исключении товара из-за нулевого остатка в журнал выводится его группа.
В версии от 2025‑11‑14 предварительный список формируется из локальной базы; товары без строки отчёта показываются с нулевым остатком (кроме группы «Классические чаи»).


