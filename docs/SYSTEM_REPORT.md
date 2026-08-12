# Системный отчет — Учебный отдел ВШПМ СПбГУПТД

## Общая информация

Веб-приложение для сбора и поиска данных учебного отдела ВШПМ СПбГУПТД.  
Автоматизирует работу с преподавателями, аудиториями, расписанием и программным обеспечением.

**Репозиторий:** https://github.com/amper24/HSPM-App  
**Стек:** PHP 7.2, MySQL 5.7, Vue 2 (CDN), Docker Compose  
**Дата финальной сборки:** 11.08.2026  
**Документация:** [USER_GUIDE.md](USER_GUIDE.md) — инструкция пользователя и администратора

---

## 1. Запуск и доступ

```bash
docker compose up -d
# Ждать ~15 секунд — entrypoint инициализирует БД и выполнит миграции
```

| Параметр | Значение |
|----------|----------|
| URL | http://localhost:8080 |
| Логин | admin |
| Пароль | admin123 |
| MySQL | localhost:3307, БД `vshpm_edu` |

---

## 2. Структура проекта

```
Практика/
├── api/                    # PHP backend (REST API)
│   ├── index.php           # Роутер API
│   ├── config.php          # Подключение к БД, хелперы (getDB, jsonSuccess и т.д.)
│   ├── auth.php            # Аутентификация (login/logout/me), password_hash bcrypt
│   ├── teachers.php        # CRUD преподавателей + фильтры
│   ├── classrooms.php      # CRUD аудиторий + search + free
│   ├── schedule.php        # CRUD расписания + bulk-delete + truncate + groups
│   ├── software.php        # CRUD ПО
│   ├── users.php           # CRUD пользователей (только admin)
│   ├── import.php          # **Импорт из Excel** (главный файл, ~750 строк)
│   └── export.php          # Экспорт в Excel
├── js/
│   ├── app.js              # **Фронтенд SPA** (Vue 2 CDN, единый компонент)
│   └── api.js              # Старый API-клиент (не используется)
├── css/
│   └── style.css           # Стили (светлая + темная тема)
├── database/
│   └── schema.sql          # Схема БД (создается при первом запуске)
├── index.html              # Точка входа SPA (минимальный скелет)
├── Dockerfile              # PHP 7.2.34 + Apache + GD/mbstring/zip/pdo + Composer 2.2
├── docker-compose.yml      # app + db (MySQL 5.7.21), порты 8080:80 и 3307:3306
├── docker-entrypoint.sh    # Init-скрипт: ждет MySQL, создает БД, выполняет миграции, генерит config.php
├── .env.example            # Пример переменных окружения
├── .htaccess               # Apache rewrite на api/index.php
├── hspm-admin              # **CLI-утилита администрирования**
└── tests/
    ├── test_db.php          # 23 теста БД
    ├── test_api.php         # 20 тестов API
    ├── debug.sh             # Диагностика Docker/MySQL/PHP (запуск с хоста)
    └── run_tests.sh         # Запуск всех тестов (--db, --api, --debug)
```

---

## 3. База данных (MySQL 5.7, utf8)

### Таблицы:

| Таблица | Назначение | Уникальный ключ |
|---------|-----------|----------------|
| `users` | Пользователи (admin/user) | `username` |
| `classrooms` | Аудитории | `(room_number, building)` |
| `teachers` | Преподаватели | `(last_name, first_name, middle_name)` |
| `schedule` | Расписание | `(classroom_id, date, pair_number, time_start)` |
| `software` | ПО | `(room_number, building, name)` |

### Внешние ключи:
- `schedule.classroom_id` → `classrooms.id` (CASCADE)
- `schedule.teacher_id` → `teachers.id` (SET NULL)
- `software.classroom_id` → `classrooms.id` (SET NULL)

### Важно:
- `schedule.classroom_id`, `schedule.teacher_id`, `schedule.date` — **NULLABLE** (разрешены для RichText-формата и ДО)
- **Кодировка:** `utf8` (не utf8mb4!) — MySQL 5.7.21
- `schema.sql` начинается с `/*!40101 SET NAMES utf8 */;` (критично для кириллицы)
- Entrypoint выполняет автоматические миграции: добавляет недостающие колонки и уникальные ключи

---

## 4. API endpoints

Все эндпоинты требуют авторизацию (сессия PHP). `/api/...`

| Метод | Путь | Доступ | Описание |
|-------|------|--------|----------|
| POST | /auth/login | all | Вход (`username`, `password`) |
| POST | /auth/logout | all | Выход |
| GET | /auth/me | all | Текущий пользователь |
| GET/POST/PUT/DELETE | /teachers | all | CRUD преподавателей |
| GET | /teachers?search=&department=&employment_type= | all | Поиск/фильтр |
| GET | /teachers/departments | all | Список кафедр |
| GET | /teachers/degrees | all | Список степеней |
| GET | /teachers/titles | all | Список званий |
| GET | /teachers/employment-types | all | Список форм занятости |
| GET/POST/PUT/DELETE | /classrooms | all | CRUD аудиторий |
| GET | /classrooms/search?q= | all | Поиск аудиторий |
| GET | /classrooms/free?date=&pair= | all | Свободные аудитории |
| GET | /classrooms/room-types | all | Список типов аудиторий |
| GET/POST/PUT/DELETE | /schedule | all | CRUD расписания |
| POST | /schedule/bulk-delete | admin | Массовое удаление (по IDs или диапазону дат) |
| POST | /schedule/truncate | admin | Очистить всю таблицу |
| GET | /schedule/groups | all | Список уникальных групп |
| GET/POST/PUT/DELETE | /software | all | CRUD ПО |
| GET | /software/buildings | all | Список корпусов |
| GET/POST/PUT/DELETE | /users | admin | CRUD пользователей |
| POST | /import | admin | Импорт Excel (multipart: file + type) |
| GET | /export | admin | Экспорт в Excel |

---

## 5. Форматы импорта (`api/import.php`)

### 5.1 Преподаватели (`type=teachers`)
**Файл:** `Список преподавателей ВШПМ СПбГУПТД 2025-26.xlsx`  
**Лист 1:** основные данные (row 3+): `B = ФИО`, `C = должность`, `D = степень`, `E = звание`, `F = кафедра / форма занятости`  
**Лист 2:** контакты (row 4+): `B = ФИО`, `C = почта`, `D = телефон` — сопоставляется по ФИО

**Фильтры:**
- Пропуск строк-разделителей кафедр (ФИО содержит «кафедра» + нет должности)
- Нормализация формы занятости: штат. → штатный, внеш. совм. → внешний совместитель, ГПХ → ГПХ
- Кафедра и форма разделены через `/`
- `ON DUPLICATE KEY UPDATE` — повторный импорт не дублирует

### 5.2 Аудитории (`type=classrooms`)
Два формата, автоопределение:
- **Формат 1 — «Тех хар-ка»:** лист с именем «Тех хар-ка», колонки A-G (фиксированные)
- **Формат 2 — Универсальный:** строка 1 = заголовки с ключевыми словами (`№ ауд`, `корпус`, `тип`, `пк`, `проектор`, `колонк`, `мест`), автоопределение колонок

### 5.3 ПО (`type=software`)
**Файл:** `Тех_хар_ка_ПО_ауд.xlsx`, лист «ПО»  
Заголовки колонок (row 3) вида «331 аудитория», «202 ВЗН ауд» → извлекается номер и корпус  
Каждая упомянутая аудитория создается в `classrooms` через `INSERT IGNORE`

### 5.4 Расписание (`type=schedule`) — ЧЕТЫРЕ формата

#### Формат 1 — Универсальный (по заголовкам) ⭐ Новое

Активируется **всегда первым** в `importSchedule()`. Функция `importScheduleByHeaders()` сканирует первые 5 строк в поисках заголовков по ключевым словам:

| Заголовок | Поле БД | Regex |
|-----------|---------|-------|
| `Дата`, `date` | `date` | `^(дат\|date)` |
| `Время`, `time` | `time_start`, `time_end` | `^(врем\|time)` |
| `Группа`, `group`, `шифр` | `group_code` | `^(групп\|group\|шифр)` |
| `Дисциплина`, `discipline` | `discipline` | `^(дисц\|discipl)` |
| `Вид`, `тип`, `экз` | `exam_type` | `^(вид\|тип\|экз)` |
| `Экзаменатор`, `преподаватель` | `examiner` | `^(экзамен\|examiner\|препод)` |
| `Аудитория`, `room`, `classroom` | `classroom` (→ `classroom_id`) | `^(ауд\|room\|classroom\|каб)` |
| `Перенос`, `transfer`, `отмена` | `transfer_cancel` | `^(перенос\|transfer\|отмен)` |
| `Кафедра группы` | `group_department` | `^(каф.*груп\|group.*dep)` |
| `Кафедра преп.` | `teacher_department` | `^(каф.*преп\|teacher.*dep)` |
| `Должность преп.` | `teacher_position` | `^(долж.*преп\|teacher.*pos)` |
| `Сроки сессии (начало)`, `сессия с` | `session_start` | `^(сроки.*начало\|сессия.*с)` |
| `Сроки сессии (окончание)`, `сессия по` | `session_end` | `^(сроки.*окончание\|сессия.*по)` |

При нахождении ≥3 совпадений строка считается заголовком, данные читаются со следующей строки.  
Если заголовки не распознаны — возвращается 0 и происходит фолбэк на фиксированные форматы.

**Особенности парсинга:**
- Мульти-даты в ячейке (например `"22.12.2025 12.01.2026"`) — берется первая через `parseExcelDate()`
- `session_start` / `session_end` обрабатываются отдельно перед общим else-блоком
- Аудитория ищется через `findClassroomByRoom()` → `classroom_id`
- Экзаменатор ищется через `findTeacherByShortName()` → `teacher_id`
- Тип нормализуется: `конс.` → `консультация`, `экз.` → `экзамен`
- Проверка дубликатов: `(date, time_start, discipline, examiner, group_code)`

#### Формат 2 — Сессионный фиксированный (`.xls`)
**Признак:** A1 содержит «экзамен». 12 фиксированных колонок A-L, данные с 5-й строки.
- A=group_department, B=group_code, C=session_start, D=session_end, E=discipline, F=teacher_department, G=teacher_position, H=examiner, I=exam_type, J=date, K=time, L=classroom

#### Формат 3 — Обычный простой (`.xlsx`)
**Признак:** <4 RichText-ячеек в первых 10 строках. E=discipline, G=lesson_type, J=date, K=time, аудитория ищется сканированием всех колонок.

#### Формат 4 — RichText (`.xlsx`)
**Признак:** >3 RichText-ячеек в первых 10 строках. 159 колонок, B=время, C+=RichText с дисциплинами, аудиториями, преподавателями. Используется `getPlainText()`.

### Ключевые функции парсинга:
- `findTeacherByShortName($pdo, 'Воронова О.Е.')` — поиск преподавателя по ФИО (фамилия + инициалы)
- `findTeacherInText($pdo, $text)` — поиск преподавателя по regex `Фамилия И.О.`
- `findClassroomByRoom($pdo, 'В237')` — поиск аудитории по номеру и корпусу
- `findClassroomInText($pdo, $text)` — поиск аудитории в тексте
- `parseExcelDate($value)` — Excel serial number / DateTime / `dd.mm.yyyy` / `yyyy-mm-dd` → `Y-m-d`
- `parseExcelTime($value)` — Excel serial (доля суток) / `HH:MM-HH:MM` / `HH:MM` → `[time_start, time_end]`
- `isAdminRow($text)` — фильтр служебных строк (директор, начальник учебного отдела и т.д.)
- `colLetter($index)` — конвертация индекса колонки в букву (A, B, ..., Z, AA, ...)
- `extractLessonType($text)` — определение типа занятия (лекция/практика/лабораторная)

---

## 6. CLI-утилита `hspm-admin`

```bash
docker exec vshpm-app php /var/www/html/hspm-admin [команда]
```

| Команда | Назначение |
|---------|-----------|
| `info` | Системная информация + статистика |
| `stats` | Статистика БД |
| `db-check` | Проверка БД и индексов |
| `reset-password` | Сброс пароля (авто или вручную) |
| `create-user` | Создание пользователя |
| `list-users` | Список пользователей |
| `truncate TABLE` | Очистка таблицы |
| `import TYPE FILE` | Импорт файла через CLI |
| `backup` | Бекап всей БД → `/var/www/html/backups/` |
| `restore [file]` | Восстановление из бекапа |
| `help` | Справка |

---

## 7. Фронтенд (`js/app.js` + `index.html` + `css/style.css`)

**Архитектура:** SPA на Vue 2 (CDN `vue@2.7`, без Node-сборки — совместимо с PHP 7.2).  
**Хранение темы:** localStorage (`theme` = `'dark'` / `'light'`).

### Компоненты:
- `app.init()` — проверка авторизации, рендер приложения или логина
- `app.api(method, url, body)` — обертка над fetch с обработкой ошибок
- `app.renderCrudPage(main, config)` — универсальный движок CRUD-таблиц с конфигурацией колонок, фильтров и formFields
- `app.renderTable(config, items)` — рендер таблицы с нумерацией и кнопками Ред/Уд
- `app.renderPager(config, total)` — пагинация (15/50/100 записей на страницу)
- `app.openForm(id, item)` / `app.closeForm(id)` — инлайн-форма редактирования
- `app.saveForm(id)` — сохранение через API
- `app.debouncedLoad(id)` — поиск с задержкой 300ms
- `app.truncateTable(id)` — очистка таблицы (админ, с двойным подтверждением)

### Страницы:
| Раздел | ID | Особенности |
|--------|-----|------------|
| Главная | `dashboard` | Часы (обновление каждую секунду), выбор даты и группы, статистика (4 карточки) |
| Преподаватели | `teachers` | 8 колонок, ФИО склеен, фильтры по поиску/кафедре/степени/званию/занятости/переносу-отмене |
| Аудитории | `classrooms` | 7 колонок + поиск свободной аудитории (дата/пара/корпус/проектор/колонки/места) |
| Расписание | `schedule` | 8 колонок, badge для переносов, поиск |
| ПО | `software` | 3 колонки, поиск по названию, фильтр по корпусу |
| Пользователи | `users` | 4 колонки, только для admin |
| Импорт | `import` | Загрузка .xlsx/.xls с выбором типа и индикатором прогресса |

### Темная тема:
- Кнопка в правом нижнем углу (☀/☾)
- Сохраняется в localStorage
- Все компоненты стилизованы под `.dark-theme`

---

## 8. Контейнеризация

### Dockerfile:
- Базовый образ: `php:7.2.34-apache` (Debian Buster)
- Локаль: `ru_RU.UTF-8`
- PHP-расширения: pdo, pdo_mysql, mbstring, zip, gd
- Apache: mod_rewrite, AddDefaultCharset UTF-8
- Composer: 2.2, пакет `phpoffice/phpspreadsheet 1.25.2`
- Слои оптимизированы: composer.json → composer install → COPY . .
- Тома: uploads, exports, backups

### docker-compose.yml:
- MySQL 5.7.21: порт 3307, healthcheck
- Приложение: порт 8080, зависит от healthcheck БД
- Переменные окружения: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
- Хранение данных в Docker-томах: `mysql_data`, `uploads`, `exports`, `backups`

### docker-entrypoint.sh:
- Ожидание MySQL (до 60 сек)
- Создание БД: `CREATE DATABASE IF NOT EXISTS ... DEFAULT CHARACTER SET utf8`
- Импорт schema.sql (только при первом запуске)
- **Автоматические миграции**: проверка и добавление недостающих колонок (`discipline`, `group_department`, `group_code`, `teacher_department`, `teacher_position`, `examiner`, `exam_type`, `session_start`, `session_end`) и уникальных ключей (`schedule.idx_unique_schedule`, `software.idx_unique_software`)
- Генерация `/var/www/html/api/config.php` из переменных окружения

---

## 9. Известные проблемы и их решения

### Проблема: «кракозябры» (ÐÐ´Ð¼Ð¸Ð½Ð¸ÑÑÑÐ°ÑÐ¾Ñ)
**Причина:** schema.sql импортировался без `SET NAMES utf8`, байты UTF-8 интерпретировались как Latin-1 (двойная перекодировка).

**Решение:** 
1. `/*!40101 SET NAMES utf8 */;` в начале schema.sql
2. `--default-character-set=utf8` во всех mysql-командах в entrypoint.sh
3. `SET NAMES utf8` в PDO (config.php)
4. После исправления HEX имени админа должен быть: `D090D0B4D0BCD0B8D0BDD0B8D181D182D180D0B0D182D0BED18020D181D0B8D181D182D0B5D0BCD18B`

### Проблема: двойная нумерация в таблицах
**Причина:** `formatCell` передавал `idx` в `render`, а `render` сам вычислял `(page-1)*50+i+1` — получался сдвиг.

**Решение:** `render: (_, idx) => idx`

### Проблема: строки-разделители кафедр импортировались как преподаватели
**Решение:** проверка в `import.php`: если ФИО содержит «кафедра» и должность пуста → skip

### Проблема: неверный хеш пароля
**Причина:** хеш из старого PHP генерировался с другим salt'ом и не проходил проверку в PHP 7.2.

**Решение:** сгенерировать хеш прямо в контейнере:
```bash
docker exec vshpm-app php -r "echo password_hash('admin123', PASSWORD_BCRYPT) . PHP_EOL;"
```
Результат записать в `schema.sql`.

### Проблема: старый index.html со встроенным JS
**Причина:** index.html содержал весь JS-код внутри `<script>` (600 строк), игнорируя `js/app.js`.

**Решение:** заменить на минимальный скелет с подключением внешнего `js/app.js` и CDN axios.

### Проблема: импорт расписания с пользовательскими заголовками
**Причина:** `importSchedule()` жестко определяла формат по A1 и фиксированным колонкам. Файлы с заголовками «Дата», «Время», «Группа» и т.д. импортировались пустыми.

**Решение:** добавлена функция `importScheduleByHeaders()` с авто-определением колонок по ключевым словам в заголовках. `importSchedule()` всегда пробует её первой.

---

## 10. Тесты

**БД (25/25 ✅):** `tests/test_db.php` — подключение, таблицы, структура, индексы, FK, каскадное удаление.
Запуск: `docker exec vshpm-app php /var/www/html/tests/test_db.php`

**API (20/20 ✅):** `tests/test_api.php` — аутентификация, CRUD, права доступа, поиск.  
Запуск: `docker exec vshpm-app php /var/www/html/tests/test_api.php http://localhost`

**Диагностика:** `tests/debug.sh` — проверка Docker, контейнеров, MySQL, PHP, API, файлов (запуск с хоста).

**Все тесты:** `tests/run_tests.sh` — запуск всей батареи (`--db`, `--api`, `--debug`).

---

## 11. Исходные файлы задания

Расположены в `Прил_Уч_отдел - задание/`:

| Файл | Назначение | Тип импорта |
|------|-----------|-------------|
| `Список преподавателей ВШПМ СПбГУПТД 2025-26.xlsx` | Преподаватели | `teachers` |
| `Тех_хар_ка_ПО_ауд.xlsx` | Аудитории + ПО | `classrooms` + `software` |
| `Расписание/1 семестр/*.xls(x)` | Расписание (сессия + обычное) | `schedule` |
| `Расписание/2 семестр/*.xls(x)` | Расписание (сессия + обычное) | `schedule` |
| `ЖУРНАЛ учета контроля и переноса занятий 2025-2026.xls` | Журнал переноса (не импортируется) | — |

---

## 12. Порядок импорта (важно!)

Для корректных связей (schedule → teachers, schedule → classrooms):

1. **Преподаватели** (`type=teachers`) — `Список преподавателей...`
2. **Аудитории** (`type=classrooms`) — `Тех_хар_ка_ПО_ауд.xlsx`
3. **ПО** (`type=software`) — `Тех_хар_ка_ПО_ауд.xlsx` (дополнит аудитории)
4. **Расписание** (`type=schedule`) — файлы из папок `Расписание/`

---

## 13. Автоматические миграции БД

При каждом запуске `docker-entrypoint.sh` проверяет и добавляет недостающие элементы схемы:

**Колонки** (добавляются в `schedule`, если отсутствуют):
- `discipline` VARCHAR(255) — дисциплина
- `group_department` VARCHAR(100) — кафедра группы
- `group_code` VARCHAR(50) — шифр группы
- `teacher_department` VARCHAR(100) — кафедра преподавателя
- `teacher_position` VARCHAR(100) — должность преподавателя
- `examiner` VARCHAR(255) — экзаменатор
- `exam_type` VARCHAR(20) — экзамен/консультация
- `session_start` DATE — начало сессии
- `session_end` DATE — конец сессии

**Уникальные ключи:**
- `schedule.idx_unique_schedule` — `(classroom_id, date, pair_number, time_start)`
- `software.idx_unique_software` — `(room_number, building, name)`

Перед добавлением уникальных ключей удаляются дубликаты (оставляется запись с минимальным `id`).