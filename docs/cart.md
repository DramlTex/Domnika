# Клиентская корзина

Этот документ описывает текущую реализацию корзины товаров во фронтенде прайс-листа.

## HTML-разметка

Корзина состоит из кнопки и модального окна. Элементы находятся в `Price/index.php`:

```html
<button class="btn" id="openCartButton">Корзина<br><span id="cartBadge"></span></button>
<div id="cartModal" class="modal">
  <div class="modal-content">
    <h2>Корзина</h2>
    <div id="cartItems"></div>
  </div>
</div>
```

`#openCartButton` фиксирован по ширине, а количество выводится на второй строке в `<span id="cartBadge">`. Модальное окно `#cartModal` содержит список позиций `#cartItems`.

## JavaScript-логика

Функции корзины описаны в файле `Price/js/cart.js`. Основные из них:

- `cartLoad()` / `cartSave()` – загрузка и сохранение объекта корзины в `localStorage`.
- `cartGetQty(id, store)` и `cartSetQty(item, qty, store)` – получение и установка
  количества по каждому складу.
- `cartChange(item, delta, store)` – изменение количества на ±1 для указанного склада.
- `updateCartBadge()` – обновляет счётчик на кнопке.
- `renderCartItems()` – формирует содержимое `#cartItems` с кнопками плюс/минус, полем ввода и кнопкой удаления позиции.
- `openCartModal()` и `closeCartModal()` – показывают и скрывают окно.
- `setupCart()` – инициализация при загрузке страницы (`DOMContentLoaded`).

Количество по каждому складу ограничено соответствующим полем `stock_storeN`. Все изменения сразу отображаются в модальном окне товара (`Price/js/modals.js`) и сохраняются в `localStorage`.

## CSS-оформление

Внешний вид кнопки и окна определяется в `Price/css/cart.css`. Здесь задаются стиль бейджа, позиционирование модального окна и оформление элементов управления количеством. Ширина поля ввода установлена в 26px — как у кнопок `+` и `−`.

## Как работает процесс

1. При загрузке страницы функция `setupCart()` считывает содержимое `localStorage` и привязывает обработчики к кнопкам.
2. При открытии карточки товара пользователь может изменить количество кнопками `+` и `−` или вручную ввести число. Значение не превышает остаток на складе.
3. Нажатие на кнопку «Корзина» открывает модальное окно со списком выбранных товаров. Внутри можно редактировать количество или удалить позицию кнопкой с иконкой корзины.
4. Закрытие окна не очищает данные — они сохраняются в `localStorage` и восстанавливаются при следующем посещении.
   Корзина закрывается по клику вне её области, поэтому кнопка с крестиком не используется.

## Планы развития корзины

В следующей версии корзины предполагается реализовать единый процесс оформления
заказа прямо из модальных окон. Основные шаги:

1. **Карточка товара** должна содержать форму выбора склада и количества.
   - Склад выбирается из выпадающего списка, что избавит от четырёх отдельных
     кнопок.
   - Поле количества представляет собой счётчик с кнопками `+` и `−`. Шаг равен
     объёму минимальной упаковки, чтобы заказ был кратен этому числу.
   - Кнопка «Добавить в корзину» переносит выбранное количество в хранилище.
2. **Окно корзины** уже содержит нижнюю панель оформления заказа (`cart-footer`).
   В ней находятся поля:
   - комментарий покупателя;
   - ИНН организации;
   - дата доставки (`input type="date"`);
   - итоговая сумма и кнопка подтверждения.
   При нажатии на кнопку введённые данные сохраняются в `localStorage`, а
   содержимое корзины очищается.
3. Валидация должна проверять кратность объёму упаковки и заполнение всех
   обязательных полей. При ошибке элементы подсвечиваются цветом и заказ не
   отправляется.
4. **Адаптивная верстка** – реализована корректная работа корзины на мобильных
   устройствах.
    - модальные окна растягиваются на всю ширину экрана при ширине до 600px;
    - панель `cart-footer` фиксируется внизу через `display: flex` и
      `position: fixed`;
    - при изменении размеров окна скрипт пересчитывает высоту содержимого,
      чтобы панель не перекрывала товары.

После подтверждения заказа данные корзины очищаются, но введённые реквизиты
сохраняются в `localStorage` для повторного использования.
