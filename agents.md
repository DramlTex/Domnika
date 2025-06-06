# AGENTS.md

Этот документ описывает правила работы с репозиторием и минимальные инструкции для Codex. Всё лишнее из предыдущих версий удалено.

## Основные задачи Codex

1. **Установка зависимостей** – запускай `scripts/install.sh` (требуется Composer).
2. **Запуск локального сервера** – `scripts/start.sh` поднимает PHP‑сервер на `localhost:8000`.
3. **Проверка кода и тесты** – перед коммитом выполняй `php -l <изменённый файл.php>` и запускай `scripts/test.sh`.
4. **Документация** – основные сведения находятся в `DOCUMENTATION.md` и каталоге `docs/`. Обновляй их вместе с кодом.

## Структура проекта

```
/Price       – веб‑интерфейс прайс‑листа
/output      – JSON‑файлы и скрипты обновления
scripts/     – утилиты для установки, тестирования и запуска
product.php  – CLI‑инструмент для выгрузки товаров
composer.json – зависимости
```

Проект написан на PHP 8 и использует библиотеку `phpoffice/phpspreadsheet`.

## Рабочий процесс

1. Склонируй репозиторий и запусти `scripts/install.sh`.
2. Отредактируй `Price/config.php`, указав логин и пароль для "МойСклад".
3. Для локальной разработки используй `scripts/start.sh`.
4. Перед коммитом выполняй синтаксическую проверку PHP и запускай тесты.
5. Любые существенные изменения описывай в `DOCUMENTATION.md` и добавляй краткую
   запись в раздел "История изменений" ниже. Каждая строчка должна содержать
   дату, время и имя изменённого файла.
6. Для CSS и JavaScript веди отдельные журналы изменений в `docs/css_index.md`
   и `docs/js_index.md`. Там фиксируй конкретные функции и действия, чтобы
   подробно отслеживать эволюцию кода.

## История изменений

- Изначальная версия – создан документ `AGENTS.md` с базовыми правилами.
- Добавлены скрипты установки и запуска, подготовлена документация.
- Создан каталог `docs/` для дополнительных материалов.
- **Текущая версия** – файл полностью переработан и упрощён.
- 2025-05-22 15:40 agents.md – добавлен стандарт документации для CSS/HTML/JS.
- 2025-05-22 16:50 agents.md – уточнены правила ведения истории для CSS и JS.

## Рекомендации

- Следовать стандарту PSR‑12.
- Для CSS, HTML и JavaScript вести документацию в каталоге `docs/`.
- Использовать понятные комментарии: описывать назначение блоков и функций.
- Для JavaScript применять JSDoc, для CSS помечать начало и конец крупных
  разделов.
- В будущем настроить автоматические тесты и CI.
