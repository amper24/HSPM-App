# Конфигурация и переменные окружения

Приложение конфигурируется через файл `.env` (см. [.env.example](../.env.example)) и
файлы `config/*.php`. Значения с секретами (пароли БД, `APP_KEY`) в репозиторий не
попадают: `.env` добавлен в `.gitignore`.

## Переменные окружения

| Переменная | По умолчанию | Назначение |
| --- | --- | --- |
| `APP_NAME` | `Учебный отдел ВШПМ` | Имя приложения |
| `APP_ENV` | `production` | Окружение: `local`, `production`, `testing` |
| `APP_KEY` | — | Ключ шифрования Laravel (`php artisan key:generate`) |
| `APP_DEBUG` | `false` | Режим отладки; в production `false` |
| `APP_URL` | `http://localhost:8080` | Базовый URL приложения |
| `APP_LOCALE` | `ru` | Локаль приложения |
| `DB_CONNECTION` | `sqlite` (в config) | Драйвер: `mysql` или `sqlite` |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `3306` | Адрес MySQL |
| `DB_DATABASE` | — | Имя базы (в Docker — `vshpm_edu`) |
| `DB_USERNAME` / `DB_PASSWORD` | — | Учётные данные БД |
| `SESSION_DRIVER` | `file` | Хранилище сессий |
| `SESSION_SECURE_COOKIE` | `false` | `true` при HTTPS |
| `CACHE_STORE` | `file` | Хранилище кэша |
| `QUEUE_CONNECTION` | `sync` | Обработка очередей (синхронно) |
| `LOG_CHANNEL` | `stack` | Канал логирования |
| `ADMIN_USERNAME` | `admin` | Логин первого администратора |
| `ADMIN_PASSWORD` | — | Пароль администратора (мин. 12 символов); после первого запуска можно удалить |
| `MYSQL_ROOT_PASSWORD` | — | Root-пароль MySQL (Docker) |
| `APP_PORT` | `8080` | Порт веб-интерфейса (Docker) |

## Где используются параметры

- **`ADMIN_USERNAME` / `ADMIN_PASSWORD`** — читаются командой `php artisan hspm:admin`
  (через `getenv('ADMIN_PASSWORD')`) и entrypoint контейнера (`docker-entrypoint.sh`).
- **`SESSION_SECURE_COOKIE`** — `config/session.php` (`'secure' => env(...)`).
- **`DB_*`** — `config/database.php`; тесты переопределяют соединение на SQLite/MySQL
  через `tests/TestCase.php` и `phpunit*.xml`.

## Лимиты (Docker)

Значения заданы в `docker/php.ini` и синхронизированы с валидацией загрузки:

- `upload_max_filesize=20M`, `post_max_size=22M` — соответствуют лимиту импорта
  `max:20480` (КБ) в `ImportController`.
- `max_execution_time=180`, `memory_limit=512M`.

## Безопасность

- Не храните реальные пароли/токены в `.env.example` — только фиктивные значения.
- `APP_KEY` постоянный для установки; не заменяйте при обновлении (иначе сессии/шифрование ломаются).
- Экспорты лежат вне `public/` и доступны только владельцу; чистятся по расписанию.

---

См. также: [README](../README.md), [ARCHITECTURE.md](ARCHITECTURE.md), [DEVELOPMENT.md](DEVELOPMENT.md), [DEPLOYMENT.md](DEPLOYMENT.md).
