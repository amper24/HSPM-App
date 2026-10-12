# Руководство по разработке

Настройка окружения, запуск, тестирование и правила внесения изменений в HSPM-App.

## Требования

| Инструмент | Версия | Назначение |
| --- | --- | --- |
| PHP | 8.4 | сервер; расширения `pdo_mysql`/`pdo_sqlite`, `mbstring`, `intl`, `gd`, `zip`, `xml`, `openssl` |
| Composer | 2 | PHP-зависимости |
| Node.js | 22 | сборка фронтенда |
| npm | 10+ | JS-зависимости |
| MySQL | 8.4 (продакшн) / SQLite (локально и в тестах) | БД |
| Docker Compose | v2 (опционально) | контейнеризация |

На Windows PHP в `PATH` обязателен. Если `php artisan serve` некорректно работает с
кириллицей в пути, перенесите проект в каталог с латинским именем.

## Подготовка окружения

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
```

Для локальной проверки можно использовать SQLite: задайте `DB_CONNECTION=sqlite`,
создайте пустой `database/database.sqlite` (`New-Item database/database.sqlite -ItemType File`
на Windows) и укажите его **абсолютный путь** в `DB_DATABASE`. Для MySQL заполните `DB_*`.

## Запуск

```bash
php artisan migrate
php artisan hspm:admin admin --create   # запросит пароль скрытым вводом
php artisan serve                       # сервер; фронтенд — npm run dev во втором терминале
npm run build                           # production-сборка фронтенда
```

Docker-вариант описан в [README](../README.md) и [DEPLOYMENT.md](DEPLOYMENT.md).

## Запуск без Docker (подробно)

### 1. PHP и расширения

Убедитесь, что PHP 8.4 установлен и включены расширения: `pdo_mysql` **или**
`pdo_sqlite`, `mbstring`, `intl`, `gd`, `zip`, `xml`, `openssl`. На Ubuntu:

```bash
sudo apt install -y php8.4-cli php8.4-mysql php8.4-sqlite3 \
  php8.4-mbstring php8.4-intl php8.4-gd php8.4-zip php8.4-xml
```

### 2. База данных: SQLite или MySQL

**SQLite** (для локальной проверки) — создайте пустой файл и укажите абсолютный путь:

```bash
touch database/database.sqlite            # Linux/macOS
New-Item database/database.sqlite -ItemType File   # Windows PowerShell
```

В `.env`:

```
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/database/database.sqlite
```

**MySQL 8.4** (для промышленной установки) — создайте БД и укажите параметры в `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vshpm_edu
DB_USERNAME=vshpm
DB_PASSWORD=...
```

### 3. Миграции, администратор, запуск

```bash
php artisan migrate
php artisan hspm:admin admin --create
php artisan serve          # http://127.0.0.1:8000
```

### 4. Промышленная установка (веб-сервер)

Веб-сервер публикует **только `public/`**; остальные каталоги закрыты. Apache — включите
`mod_rewrite` и задайте `DocumentRoot` на `public/`; Nginx — `root` на `public/` с
`try_files $uri $uri/ /index.php?$query_string;`. Затем:

```bash
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Для очистки временных выгрузок настройте cron-планировщик (`php artisan schedule:run`
раз в минуту) либо выполняйте `php artisan hspm:exports-clean` вручную.

## Тестирование

```bash
php vendor/bin/phpunit                 # Feature-тесты (SQLite :memory:)
npm test                               # Vitest (frontend)
npm run build                          # сборка фронтенда
php vendor/bin/pint --test             # проверка стиля PHP (без записи)
```

Прогон на MySQL: создайте пустую базу `hspm_test` и выполните
`php vendor/bin/phpunit -c phpunit.mysql.xml`. **Не направляйте тесты в рабочую базу** —
`RefreshDatabase` создаёт и очищает таблицы.

Проверка реальных Excel-файлов без рабочей БД:

```bash
php tests/check_source_archive.php /path/to/archive /path/to/report.json
```

## CI/CD

- `.github/workflows/tests.yml` — сборка, Vitest, PHPUnit на SQLite и MySQL.
- `.github/workflows/docker-publish.yml` — сборка и публикация образа в GHCR.

## Стиль кода

- PHP форматируется Pint (`laravel/pint`), конфигурация по умолчанию Laravel.
- JS не имеет отдельного линтера; придерживайтесь существующего стиля (2 пробела,
  одинарные кавычки, точки с запятой).
- Комментарии — на русском, в формате PHPDoc/JSDoc. Каждая функция и класс должны
  иметь документацию (назначение, параметры, возврат, побочные эффекты).

## Как добавить новую функциональность

1. **Новое поле справочника:** миграция → `$fillable` модели → правило в FormRequest →
   конфигурация формы/колонок в `resources/js/config/resources.js` → импорт/экспорт (при необходимости).
2. **Новая бизнес-логика:** размещайте в `app/Services`, а не в контроллере или Vue-компоненте.
3. **Новый эндпоинт:** маршрут в `routes/web.php` + контроллер (наследуйте `Controller`).
4. **Фильтры списков:** только в `EducationQuery::for()` — не добавляйте произвольные
   условия в контроллерах (защита от подмены колонок).

## Типичные ошибки окружения

- **419 CSRF mismatch** — очистите cookie/сессию или пересоздайте токен; клиент сам
  повторяет запрос после обновления CSRF.
- **`hspm:admin` не создаёт пользователя** — пароль должен быть не короче 12 символов;
  при `--no-interaction` передайте пароль через переменную окружения `ADMIN_PASSWORD`.
- **Миграции не применяются к старой базе** — используйте `hspm:adopt-legacy --backup-confirmed`
  вместо `migrate:fresh` (см. [DEPLOYMENT.md](DEPLOYMENT.md)).

---

См. также: [README](../README.md), [ARCHITECTURE.md](ARCHITECTURE.md), [MODULES.md](MODULES.md), [API.md](API.md), [CONFIGURATION.md](CONFIGURATION.md).
