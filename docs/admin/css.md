# Стили для `admin.php`

В админ‑панели применяются базовые стили из файла [`Price/styles.css`](../../Price/styles.css) и дополнительный файл [`Price/styles/admin.css`](../../Price/styles/admin.css). Ранее в `admin.php` был встроенный блок `<style>`, который теперь удалён.

```html
<link rel="stylesheet" type="text/css" href="styles.css">
<link rel="stylesheet" type="text/css" href="styles/admin.css">
```

Файл `styles.css` задаёт общую типографику и оформление форм, а `admin.css` содержит правила для элементов админ‑панели: списков чекбоксов, кнопок `.btn-msk`, полей ввода `.ms-form-control` и т.д.
Также добавлен класс `.logout-link` для кнопки выхода в правом верхнем углу. Он повторяет оформление с основной страницы и задаёт красный фон с белым текстом.

Дополнительно подключены стили для плагина Select2 и кнопок `.btn-msk`. Цвета кнопки `.btn-msk.btn-success` инвертируются при наведении.
