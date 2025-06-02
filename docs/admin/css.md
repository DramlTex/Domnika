# Стили для `admin.php`

В админ‑панели применяются базовые стили из файла [`Price/styles.css`](../../Price/styles.css). Дополнительно в самом файле `admin.php` присутствует блок `<style>` с небольшими правилами для таблицы и списка чекбоксов.

```html
<link rel="stylesheet" type="text/css" href="styles.css">
<style>
    table, th, td { border:1px solid #ccc; padding:8px; }
    th { background:#eee; }
    .error { padding:10px; background:#ffcfcf; color:#900; font-weight:bold; }
    .checkbox-list { width:300px; max-height:150px; overflow-y:auto; border:1px solid #ddd; padding:5px; }
</style>
```

Файл `styles.css` задаёт общую типографику и оформление форм. Встроенные стили используются для наглядности и могут быть перенесены в отдельный CSS‑файл.

Дополнительно подключены стили для плагина Select2 и кнопок `.btn-msk`. Цвета кнопки `.btn-msk.btn-success` инвертируются при наведении.
