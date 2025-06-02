# Отображение столбцов прайс-листа

Этот документ объясняет, как формируются столбцы таблицы товаров на странице `Price/index.php`. Рассматривается цепочка от получения данных на сервере до рендеринга в браузере.

## Поток данных

1. **`Price/data.php`** обращается к API «МойСклад» и формирует JSON со списком товаров. В ответе присутствуют поля `articul`, `name`, `uom`, `tip`, `supplier`, `mass`, `price`, `stock`, `volumeWeight`, ссылки на фото и другие параметры.
2. **`Price/js/data-loader.js`** выполняет `fetch('.../price/data.php')`, сохраняет массив в `window.__productsData` и передает его в функцию `fillTable` из `table.js`.
3. **`Price/js/tabs.js`** разбивает товары по группам через `groupProductsByCategory` и создаёт кнопки вкладок. При переключении вкладки вызывается `fillTable` с нужным набором строк.

## Разметка таблицы

Заголовок и порядок колонок заданы непосредственно в HTML (`index.php`):

```html
<table id="priceList">
  <thead>
    <tr>
      <th class="num-col">№</th>
      <th>Артикул</th>
      <th>Фото</th>
      <th class="name-col">Название</th>
      <th>Стандарт</th>
      <th class="type-col">Тип</th>
      <th class="country-col">Страна</th>
      <th>Вес тарного места</th>
      <th>Цена</th>
      <th>Наличие в кг</th>
      <th>Объемный вес</th>
    </tr>
  </thead>
  <tbody></tbody>
</table>
```

Количество столбцов фиксировано. При добавлении новых полей нужно изменить и разметку, и шаблон строки в JavaScript.

## Функции вывода

Основная логика отображения содержится в `Price/js/table.js`:

- `sortByCountry()` — сортирует массив товаров по заданному порядку стран и названию.
- `fillTable(data)` — рендерит строки таблицы небольшими порциями. Для каждой записи формируются ячейки в том же порядке, что и заголовки. Заголовок страны и типа вставляется при смене значения.
- `fillFilters(data)` — наполняет выпадающие списки уникальными странами и типами, используя функции `orderCountriesList` и `orderTypesList`.

Фрагмент шаблона строки:

```javascript
<tr>
  <td class="num-cell">${rowNumber}</td>
  <td>${item.articul}</td>
  <td class="photo-cell">${photoCell}</td>
  <td class="name-cell">${item.name}</td>
  <td>${item.uom}</td>
  <td class="type-cell">${item.tip}</td>
  <td class="country-cell">${item.supplier}</td>
  <td>${formatNumber(item.mass)}</td>
  <td>${formatNumber(item.price)}</td>
  <td>${formatStock(stockVal)}</td>
  <td>${formatNumber(item.volumeWeight)}</td>
</tr>
```

Значения форматируются функциями `formatNumber` и `formatStock`, которые приводят числа к удобному виду и отбрасывают лишние нули.

## Сортировка и фильтрация

Начиная с версии 2025‑06‑23 порядок стран и набор столбцов загружаются из
JSON‑файлов `row_sort_rules.json` и `column_rules.json`. Скрипт `rules-loader.js`
считывает их при загрузке страницы и сохраняет в глобальных переменных
`COUNTRY_ORDER`, `TYPE_ORDER` и `COLUMN_RULES`. `fillTable` и `fillFilters`
используют эти данные для сортировки и построения таблицы. Отсутствующие в
правилах значения сортируются по алфавиту.
Начиная с версии 2025‑06‑24 путь к файлу `row_sort_rules.json` можно задать
для каждого пользователя через поле `rules_file` в `users.json`.

## JSON-файлы правил

`Price/column_rules.json` описывает набор отображаемых колонок. Каждая запись
содержит поля `id`, `title`, `class` и `enabled`. Значение `class` соответствует
CSS‑классам в `table.css` (например `name-col`, `type-col`, `country-col`). Если
`enabled` установлено в `false`, колонка скрывается.

`Price/row_sort_rules.json` задаёт порядок стран и типов. Поле `countryOrder`
указывает приоритет стран, `typeOrder` — список типов в пользовательском
порядке. Свойство `typeSort` определяет способ сортировки типов: `alphabetical`
или `order`. При значении `alphabetical` списки типов сортируются по алфавиту.

## История изменений

- 2025-06-22 docs/columns/README.md – первая версия описания процесса вывода столбцов.
- 2025-06-23 docs/columns/README.md – правила сортировки и столбцов вынесены в JSON.
- 2025-06-25 Price/column_rules.json, Price/row_sort_rules.json, docs/columns/README.md – описаны новые поля `enabled` и `typeSort`.
