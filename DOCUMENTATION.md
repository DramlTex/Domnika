# DOCUMENTATION

## Краткое введение

Проект представляет собой набор PHP-скриптов для интеграции с сервисом "МойСклад" и веб-интерфейс для отображения прайс‑листа. Основная логика расположена в каталоге `Price`, также присутствуют вспомогательные скрипты в корне репозитория. Расширенная документация по коду хранится в каталоге `docs`.

## Архитектура приложения

- **`product.php`** – CLI‑скрипт для выгрузки товаров из API и сохранения отфильтрованного списка в `output/filtered_products.json`.
- **`output/update.php`** – обновляет товары в "МойСклад" добавляя недостающие упаковки.
- **Каталог `Price`** – веб‑интерфейс прайс‑листа и система авторизации.
  - `login.php`, `admin.php`, `index.php` – основные страницы.
  - `data.php` – загрузка данных из API с учётом складов.
  - `export.php` – экспорт текущего прайса в Excel с использованием `phpoffice/phpspreadsheet`.
  - `config.php` – хранит учётные данные для API.

## Структура каталога

```
/Price          – веб‑интерфейс и вспомогательные файлы
/output         – JSON‑файлы и скрипты обновления
scripts/        – утилиты для установки, тестирования и запуска
product.php     – утилита загрузки товаров
composer.json   – зависимости проекта
docs/           – дополнительная документация (PHP, JS, HTML, CSS)
```

## Установка

Требуется PHP 8 и Composer. Запустите скрипт:

```bash
scripts/install.sh
```

Он установит PHP‑зависимости, определённые в `composer.json`.

## Настройка окружения

1. Укажите логин и пароль для "МойСклад" в файле `Price/config.php`.
2. При необходимости скорректируйте пути в `product.php` и `output/update.php`.

## Быстрый старт

1. Установите зависимости через `scripts/install.sh`.
2. Заполните параметры подключения в `Price/config.php`.
3. Выполните `product.php` или `output/init_db.php` для загрузки товара.
4. Запустите веб‑сервер командой `scripts/start.sh` и откройте `http://localhost:8000`.
5. Для проверки синтаксиса и тестов используйте `scripts/test.sh`.

## Запуск приложения

Локальный веб‑сервер можно запустить командой:

```bash
scripts/start.sh
```

После запуска интерфейс будет доступен на `http://localhost:8000/`.

## Запуск и тестирование

Для запуска тестов (если они появятся) используйте:

```bash
scripts/test.sh
```

По умолчанию скрипт выводит сообщение о том, что тесты не настроены.

## Правила фиксации изменений

- Перед коммитом проверяйте изменённые PHP‑файлы командой `php -l <file>`.
- Описывайте в сообщениях коммитов найденные и исправленные ошибки.

## История изменений
- 2025-05-22 16:35 Price/index.php – вынесен JavaScript в отдельные файлы

- Изначальная версия документа.
- 2025-05-22 15:51 Price/index.php, docs/css_index.md – добавлена документация по CSS.
- 2025-05-22 16:29 Price/index.php, docs/js_index.md – добавлена документация по JavaScript.
- 2025-05-22 16:50 agents.md, docs/css_index.md, docs/js_index.md – добавлены журналы изменений для CSS и JS.
- 2025-05-26 12:00 docs/css_index.md – добавлено описание разбиения стилей на файлы.
- 2025-05-22 16:58 Price/index.php, Price/js/table.js – стили вынесены в каталог `css`.
- 2025-05-22 17:10 Price/js/table.js, Price/css/table.css – добавлена сортировка по странам и стиль заголовков; создан файл docs/table.md.
- 2025-05-22 17:28 Price/js/table.js, Price/css/table.css – добавлено разделение по типам с классом `.type-row`.
- 2025-05-27 10:00 Price/index.php, Price/js/table.js, Price/css/table.css – ячейки колонок "Тип" и "Страна" получили классы `.type-cell` и `.country-cell`.
- 2025-05-22 18:22 docs/export_excel.md – описан процесс экспорта прайса в Excel.
- 2025-05-27 12:40 Price/index.php, Price/css/table.css – расширена колонка названия, добавлены классы `.name-cell` и `.num-cell`.
- 2025-05-22 21:40 Price/index.php, Price/css/table.css – добавлен контейнер `.table-scroll` и возвращено фиксирование шапки.
- 2025-05-23 06:45 Price/css/table.css – закреплена шапка таблицы через `position: sticky`.
- 2025-05-23 06:50 docs/php/overview.md – добавлено описание PHP-скриптов.
- 2025-05-23 07:05 docs/php/data.md – подробная документация по Price/data.php.
- 2025-05-30 09:30 docs/php/data.md – расширено описание функций получения товаров.
- 2025-05-23 07:08 docs/php/data.md – добавлено руководство по патчу для модификаций без родителя.
- 2025-05-23 07:45 docs/admin/php.md – создана документация по admin.php
- 2025-05-30 11:10 Price/evt/data.php – добавлен дозагрузчик родительского товара для модификаций
- 2025-05-23 08:42 Price/index.php, Price/js/cart.js – добавлена клиентская корзина
- 2025-05-23 09:30 Price/js/cart.js, Price/js/modals.js – поле количества без спиннеров и ограничено остатком
- 2025-05-30 12:30 docs/cart.md – создан файл документации по клиентской корзине
- 2025-05-30 13:10 Price/index.php, Price/js/cart.js, Price/css/cart.css – добавлена кнопка удаления из корзины и убран крестик закрытия.
- 2025-06-01 10:30 Price/index.php, Price/js/cart.js, Price/css/cart.css – кнопка удаления с иконкой корзины и фиксированный размер кнопки "Корзина".
- 2025-06-01 12:00 Price/css/cart.css – исправлено отображение счётчика корзины, перенесено количество на отдельную строку.
- 2025-05-23 12:45 Price/index.php, Price/js/cart.js, Price/js/modals.js – выбор склада при добавлении в корзину.

- 2025-05-23 16:01 docs/cart.md – обновлены рекомендации по дальнейшему развитию корзины.
- 2025-06-10 09:00 docs/cart.md – переработаны планы развития корзины.
- 2025-06-10 11:00 Price/index.php, Price/js/cart.js, Price/css/cart.css – доба
влена панель `cart-footer` с полями оформления заказа.
- 2025-06-10 11:30 docs/cart.md – расширен пункт об адаптивной верстке корзины.
- 2025-06-10 12:00 Price/init.php, docs/php/overview.md – обновлено описание инициализации администратора.

- 2025-06-20 09:30 Price/data.php, Price/js/table.js – удалены дроби в колонке
  "Наличие в кг" и скрыты товары с нулевым остатком.

- 2025-06-20 12:00 Price/export.php, docs/export_excel.md – добавлено форматирование целых чисел и опциональная колонка "Мин. заказ".

- 2025-06-21 10:30 Price/export.php – ширина столбца "Тип" вычисляется по самому длинному слову.
- 2025-06-21 11:00 Price/js/filters.js, Price/js/tabs.js, Price/js/data-loader.js, docs/js_index.md – правка фильтрации по вкладкам.
- 2025-06-22 docs/columns/README.md – добавлено описание процесса вывода столбцов.
- 2025-06-23 Price/js/rules-loader.js, docs/columns/README.md – правила сортировки вынесены в JSON.
- 2025-06-24 Price/admin.php, Price/login.php, Price/index.php, Price/js/rules-loader.js – индивидуальный файл правил для пользователей.
- 2025-06-25 Price/column_rules.json, Price/row_sort_rules.json, Price/js/rules-loader.js, Price/js/table.js – расширены настройки правил сортировки и колонок.
- 2025-06-26 Price/admin.php, docs/columns/README.md – редактирование порядка стран перенесено в admin.php.
- 2025-06-30 Price/admin.php – форма порядка стран использует select2 и загружает список стран из «МойСклад».
- 2025-07-20 Price/admin.php – страны можно сортировать перетаскиванием через jQuery UI.
- 2025-07-25 Price/admin.php – добавлено управление порядком типов чая.
- 2025-06-02 Price/admin.php – добавлены классы `.btn-msk` и `.ms-form-control`.
- 2025-07-30 Price/admin.php – удалены кнопки "Добавить" и "Удалить" в форме columnForm.
- 2025-06-02 19:15 Price/admin.php, docs/admin/php.md – контрагенты и группы товаров загружаются при загрузке страницы, кнопки обновления удалены.
- 2025-06-03 07:09 Price/admin.php, Price/styles.css, docs/admin/php.md, docs/admin/css.md – добавлена ссылка выхода из учётной записи.
- 2025-08-15 Price/admin.php, docs/admin/php.md – объединены формы правил и единая кнопка сохранения.


- 2025-08-20 Price/admin.php, Price/css/admin.css, docs/admin/css.md – стили вынесены из файла и панель получила заголовки.
- 2025-08-26 10:00 Price/data.php, Price/js/table.js – товары из вкладок «Ароматизированный чай» и «Приправы» отображаются без остатка.
- 2025-08-26 10:20 Price/js/filters.js – фильтр по складу не скрывает такие товары.

- 2025-06-09 Price/admin.php, docs/admin/php.md – данные "МойСклад" кешируются в сессии и не загружаются при сохранении.
- 2025-06-26 Price/data.php – расширено логирование запросов к «МойСклад».
- 2025-06-26 11:27 Price/data.php, docs/php/data.md – кеширование неподходящих родителей, чтобы не повторять запросы.
- 2025-06-26 15:00 Price/data.php, docs/php/data.md – повторные обращения к одному URL теперь возвращаются из кеша.
- 2025-09-05 12:00 webhook.php, Price/data.php, docs/php/webhook.md – добавлен обработчик вебхуков и использование локальной базы товаров.
- 2025-06-27 10:16 webhook.php – вывод текста ответа при ошибке запросов к «МойСклад».

- 2025-06-27 10:20 webhook.php – исправлен заголовок Accept в запросах.
- 2025-11-01 output/init_db.php, docs/php/init_db.md – скрипт начальной загрузки базы товаров и модификаций.
- 2025-11-02 Price/data.php, docs/php/data.md – расширено логирование работы скрипта.
- 2025-11-03 Price/data.php, docs/php/data.md – введена константа DEBUG_LOG для детального логирования.
- 2025-06-27 11:16 Price/data.php – расширен список ALWAYS_SHOW_GROUPS.
- 2025-06-27 11:20 Price/data.php – подробное логирование причин исключения товаров.
- 2025-06-27 12:24 Price/data.php – разбор строк отчёта без поля `assortment`.

- 2025-06-27 16:17 Price/index.php, Price/js/modals.js – клиентская корзина снова включена.
- 2025-06-27 13:29 Price/index.php, Price/js/cart.js, Price/css/cart.css, docs/cart.md – удалена клиентская корзина.
- 2025-06-27 14:23 Price/js/main.js – удалён вызов `setupCart`, вызывавший ошибку.
- 2025-06-30 08:12 Price/data.php, docs/php/data.md – использование отчёта stock/bystore/current.
- 2025-11-05 Price/data.php, docs/php/data.md – поддержка формата отчёта без поля `rows`.
- 2025-11-05 Price/js/table.js – обработка кликов по строкам через делегирование, панель товара открывается корректно.

- 2025-11-06 Price/js/table.js – обработчик кликов подключается через `addEventListener` с очисткой предыдущего.
- 2025-11-06 Price/js/modals.js – проверка наличия элементов перед заполнением панели товара.
- 2025-11-07 Price/data.php, Price/js/table.js – удалена группа «Травы и добавки» из ALWAYS_SHOW_GROUPS.
- 2025-11-08 Price/data.php, docs/php/data.md – единый формат сообщений логирования.

- 2025-11-09 Price/data.php, docs/php/data.md – сводные данные по группам в логе.
- 2025-11-10 12:15 Price/data.php, Price/js/table.js – возвращена группа «Травы и добавки» в ALWAYS_SHOW_GROUPS.

- 2025-11-10 12:30 Price/data.php, docs/php/data.md – пересоздание лог-файла при превышении 10 МБ.

- 2025-11-12 Price/data.php, docs/php/data.md – учёт остатков модификаций при нулевом остатке родителя.

- 2025-11-13 Price/data.php, docs/php/data.md – логирование группы при исключении товара с нулевым остатком.
- 2025-11-14 Price/data.php, docs/php/data.md – сначала берём данные из локальной базы, затем сравниваем с отчётом.
- 2025-11-15 Price/data.php, docs/php/data.md – отчёт stock/bystore с stockType=quantity, значение в поле quantity.
- 2025-11-16 Price/js/table.js, Price/js/filters.js – типы сортируются внутри каждой страны без учёта регистра.
- 2025-11-17 Price/row_sort_rules.json – установлен пользовательский порядок типов.

- 2025-11-18 Price/admin.php – добавлен выбор параметра typeSort при сохранении правил.
- 2025-11-19 Price/admin.php, Price/data.php, docs/admin/php.md – добавлен блок "Фикс Sea0011" и чтение файла fix.json.
- 2025-07-01 12:33 Price/index.php, Price/js/data-loader.js, Price/styles_main.css, Price/css/banner.css – заменены абсолютные URL на относительные.
- 2025-07-01 13:05 Price/index.php, Price/js/data-loader.js, Price/styles_main.css, Price/css/banner.css – пути к файлам указаны без префикса /price.
- 2025-07-01 13:44 README.md, DOCUMENTATION.md – добавлен раздел "Быстрый старт" и обновлена инструкция по запуску.
- 2025-07-02 Price/data.php, Price/js/table.js – учёт остатков для группы «Травы и добавки».
- 2025-11-20 Price/export.php – экспорт упорядочивает вкладки и страны как на сайте.
- 2025-07-02 13:27 Price/logo_big.png, Price/export.php, Price/index.php, docs/export_excel.md – файл jfkxlsx.png переименован в logo_big.png.

- 2025-11-21 Price/admin.php, Price/search_product.php – добавлен раздел сортировки товаров.
- 2025-11-22 Price/search_product.php – поиск товаров через API с проверкой сессии.
- 2025-11-25 Price/admin.php, Price/js/rules-loader.js – product_sort_rules.json хранит id и имя товара.
- 2025-07-22 15:30 Price/search_product.php – добавлено логирование.
## Рекомендации по улучшению

- Добавить автоматические тесты и линтеры.
- Настроить CI для проверки и деплоя.


- 2025-07-30 11:58 Price/login.php – форма авторизации центрирована через flex.
- 2025-11-26 Price/login.php – форма помещена в контейнер.
- 2025-07-30 13:15 Price/login.php – добавлен фон tea.jpg с прозрачностью 0.6.
- 2025-07-30 14:22 Price/login.php, Price/styles.css – минимальная ширина контейнера входа и новые стили полей.
- 2025-07-31 10:00 Price/styles.css – улучшено оформление контейнера авторизации с размытием и тенью.
- 2025-07-31 11:00 Price/login.php, Price/styles.css – добавлен id `loginForm` и новые стили полей входа.

- 2025-07-31 12:00 Price/index.php, Price/admin.php – каталог `css` переименован в `styles`.
