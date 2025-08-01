# Руководство администратора

В этом документе собраны базовые сведения о работе с административной панелью проекта. Здесь описаны процедуры аутентификации, управления товарами и настройки параметров.

## Загрузка данных из «МоегоСклада»

Данные контрагентов и групп товаров берутся из «МоегоСклада» только по нажатию кнопки **«Получить данные из Моего Склада»**. Полученные списки сохраняются в файлы `casa/counterparties.json` и `casa/productfolders.json`, а также в сессию. При следующем открытии административной панели, если сессионные данные отсутствуют, используются значения из этих JSON‑файлов, поэтому группы товаров видны сразу.

## Вкладки для правил отображения

Над формой настройки появился переключатель вкладок. Каждая вкладка соответствует разделу прайс‑листа («Классические чаи», «Ароматизированный чай», «Травы и добавки», «Приправы»). Изменения порядка стран, типов, товаров и колонок внутри выбранной вкладки влияют только на соответствующий раздел прайса.

## Добавление и редактирование пользователей

В верхней части админ‑панели рядом с кнопкой **«Получить данные из Моего Склада»** расположена кнопка **«Редактировать пользователей»**. При нажатии она открывает модальное окно со списком существующих учётных записей. Здесь можно изменять контрагента, скидку, пароль и набор товарных групп, а также удалить пользователя. В этом же окне доступна кнопка **«Добавить пользователя»**, которая открывает форму создания нового логина.

Модальное окно пользователей занимает всю ширину экрана и больше не ограничивается размерами админ-панели, что удобно при работе с широкими таблицами.

В форме добавления пользователя селектор контрагента и список групп товаров теперь растягиваются на всю ширину окна, что упрощает выбор нужных значений.
Кроме того, список групп товаров в этом окне теперь отображается с плавно скруглёнными краями, что делает интерфейс более аккуратным.
