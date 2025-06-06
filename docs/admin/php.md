# Admin Panel (`admin.php`)

Этот документ описывает работу файла `Price/admin.php`.

## Назначение

`admin.php` предоставляет веб-интерфейс для управления пользователями. Списки контрагентов и групп товаров загружаются из сервиса «МойСклад» при открытии страницы. Страница доступна только после авторизации под пользователем с ролью `admin`.
Вверху панели выводится имя текущего пользователя и ссылка «Выйти», позволяющая завершить сессию и войти под другим аккаунтом.

## Основные разделы кода

1. **Сессии и проверка доступа** – скрипт включает сохранение сессий в каталоге `sessions`, запускает `session_start()` и проверяет, что текущий пользователь авторизован и имеет роль `admin`. В противном случае происходит перенаправление.
2. **Загрузка настроек** – данные для подключения к API «МойСклад» берутся из `config.php`.
3. **Работа с пользователями** – функции `loadUsers()` и `saveUsers()` читают и сохраняют файл `users.json`. Отдельный блок кода обрабатывает изменения в таблице пользователей и добавление нового пользователя.
4. **Интеграция с «МойСклад»** – функции `getCounterpartiesMS()` и `getProductFoldersMS()` выполняют постраничные запросы к API, обрабатывают возможные ошибки и возвращают массивы контрагентов и групп товаров.
5. **Локальные JSON‑файлы** – для кэширования могут использоваться файлы `counterparties.json` и `productfolders.json`.
6. **Обработка POST‑действий** – скрипт распознаёт несколько вариантов запросов: сохранение изменений существующих пользователей и добавление нового пользователя.
7. **HTML‑форма** – в нижней части файла расположен вывод таблицы пользователей и форма для создания нового пользователя. Здесь же подключается внешний файл стилей `styles.css` и задаются небольшие встроенные стили.

## Структура данных пользователя

Каждая запись в `users.json` содержит поля:

```json
{
  "login": "...",
  "password_hash": "...",
  "role": "user"|"admin",
  "discount": 0,
  "counterparty": {"href": "", "name": ""},
  "productfolders": [{"href": "", "name": ""}, ...]
}
```

Порядок стран и типов для сортировки товаров хранится в `row_sort_rules.json`.
В админ‑панели предусмотрены два блока для редактирования этих списков.
Каждый блок позволяет перетаскивать значения и сохраняет их в файл `row_sort_rules.json`.

Начиная с версии 2025‑08‑02 добавлен третий раздел для правки файла
`column_rules.json`. Он позволяет изменить заголовок столбца, выключить его из
отображения и переставить порядок колонок с помощью перетаскивания.

## Дополнительная информация

В каталоге `Price/evt` находится упрощённая версия админ‑панели для демонстраций. Её функциональность аналогична, но код может отличаться по оформлению.
