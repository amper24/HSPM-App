# Справочник модулей

Описание ключевых модулей исходного кода, их ответственности и связей. Подробный
разбор поведения — в [ARCHITECTURE.md](ARCHITECTURE.md) и комментариях в коде.

## Сервер (PHP)

### HTTP-слой — `app/Http`

- **Controllers** — тонкие обработчики запросов. Базовый `Controller` задаёт формат
  ответа `{success, data, message}`. `ResourceController` — общая пагинация/чтение/
  удаление; дочерние (`TeacherController`, `ClassroomController`, `SoftwareController`,
  `ScheduleController`, `UserController`) — создание/обновление.
- **Requests** — `authorize()` (роль admin) и `rules()` (валидация полей). На PUT/PATCH
  применяется `sometimes` для частичного обновления.
- **Middleware/RequireAdmin** — проверка роли `admin` из БД (alias `admin`).
- **Resources/ScheduleData** — плоское представление занятия (teacher_name, rooms).

### Модели — `app/Models`

| Модель | Таблица | Связи |
| --- | --- | --- |
| `User` | `users` | аутентификация (`Authenticatable`) |
| `Teacher` | `teachers` | `lessons()` (hasMany schedule) |
| `Classroom` | `classrooms` | `primaryLessons()` (hasMany), `lessons()` (belongsToMany) |
| `ScheduleEntry` | `schedule` | `teacher()`, `classroom()`, `rooms()` |
| `Software` | `software` | привязка по `classroom_id` или `room_number+building` |

### Запросы — `app/Queries`

- **EducationQuery::for(resource, filters)** — единственное место с разрешёнными
  фильтрами/сортировкой. Защищает от подмены колонок.
- **OccupancyReport::rows()** — агрегация занятости по аудиториям одним SQL (UNION).

### Импорт — `app/Services/Import`

- **ImportService** — загрузка книги, выбор парсера, вызов записи.
- **ImportWriter** — транзакционная пакетная запись (чанки по 200), сопоставление
  преподавателей, дедупликация, замены.
- **TeacherParser / ClassroomParser / ScheduleParser / WeeklyScheduleParser /
  SoftwareParser** — чтение исходных форматов Excel, без SQL.
- **ExcelValue** — чтение ячеек/дат/времени; **ValueNormalizer** — нормализация
  справочников (кафедры, занятость, ФИО).

### Экспорт — `app/Services/Export`

- **ExportService** — выборка данных + сохранение файла вне `public/`.
- ***Workbook** — оформление Excel по типам; **WorkbookStyle** — стили заголовков.

### Расписание — `app/Services/Schedule`

- **ScheduleService::save()** — транзакционное сохранение занятия и аудиторий.
- **RoomParser** — разбор строки аудиторий «Д234, В105».
- **ScheduleKey** — детерминированный dedup-ключ.

### Пользователи — `app/Services/UserService`

Защита последнего администратора и запрет удаления собственной записи (транзакция +
`lockForUpdate`).

### Команды — `app/Console/Commands`

- `hspm:admin` — создать администратора/сменить пароль.
- `hspm:adopt-legacy` — принять существующую базу старой версии.
- `hspm:import` — импорт из CLI.

## Клиент (JS) — `resources/js`

- **App.vue** — оболочка SPA, меню, тема.
- **views/** — `DashboardView` (главная), `RecordsView` (списки), `ImportView`.
- **components/** — `RecordForm`, `ListPagination`, `LoginForm`, `FreeClassrooms`.
- **store/** — Vuex-модули: `auth` (сессия), `records` (списки), `dashboard` (главная).
- **services/http.js** — общий HTTP-клиент (CSRF, ошибки, повтор 419).
- **services/education.js** — предметные методы API.
- **config/resources.js** — декларативное описание колонок/фильтров/форм.
- **utils/format.js** — чистые функции форматирования.

## Маршруты и БД

- **routes/web.php** — эндпоинты и middleware; **routes/console.php** — команда очистки
  выгрузок и её расписание.
- **database/migrations/** — схема БД и её расширение/индексы.

## Полный перечень файлов

### app/Http/Controllers

- `Controller` — базовый контроллер, единый формат ответа `success()`.
- `ResourceController` — пагинация, чтение, удаление, очистка, `present()`.
- `TeacherController`, `ClassroomController`, `SoftwareController` — CRUD через Eloquent.
- `ScheduleController` — CRUD через `ScheduleService` + `bulkDelete()`.
- `UserController` — CRUD через `UserService`.
- `AuthController` — вход, текущий пользователь, выход.
- `DashboardController` — счётчики справочников (один SQL).
- `ExportController` — формирование и выдача выгрузок.
- `FreeClassroomController` — поиск свободных аудиторий.
- `ImportController` — приём Excel и запуск импорта.
- `LookupController` — значения фильтров (`options`) и поиск (`search`).

### app/Http/Requests

- `ClassroomRequest`, `SoftwareRequest`, `TeacherRequest`, `ScheduleRequest`,
  `UserRequest` — правила валидации и авторизация `authorize()`.

### app/Http/Middleware

- `RequireAdmin` — проверка роли `admin` (alias `admin`).

### app/Http/Resources

- `ScheduleData` — плоское представление занятия (teacher_name, rooms).

### app/Models

- `User`, `Teacher`, `Classroom`, `ScheduleEntry`, `Software` — Eloquent-модели,
  касты типов и связи.

### app/Queries

- `EducationQuery` — фабрика запросов с разрешёнными фильтрами и сортировкой.
- `OccupancyReport` — отчёт по занятости одним SQL.

### app/Services/Import

- `ImportService` — загрузка книги, выбор парсера, запись.
- `ImportWriter` — транзакционная пакетная запись (чанки по 200).
- `TeacherParser` — чтение списка преподавателей (два листа).
- `ClassroomParser` — чтение аудиторий («Тех хар-ка» или таблица по заголовкам).
- `SoftwareParser` — чтение транспонированного листа «ПО».
- `ScheduleParser` — табличные формы расписания.
- `WeeklyScheduleParser` — недельные сетки.
- `ExcelValue` — чтение ячеек, дат и времени.
- `ValueNormalizer` — нормализация справочников (кафедры, занятость, ФИО).

### app/Services/Export

- `ExportService` — выборка данных и сохранение файла вне `public/`.
- `TeachersWorkbook`, `ClassroomsWorkbook`, `ScheduleWorkbook`, `SoftwareWorkbook`,
  `ReportWorkbook` — оформление книг Excel.
- `WorkbookStyle` — стили заголовков.

### app/Services/Schedule

- `ScheduleService` — транзакционное сохранение занятия и аудиторий.
- `RoomParser` — разбор строки аудиторий.
- `ScheduleKey` — детерминированный ключ дедупликации.

### app/Services

- `UserService` — защита последнего администратора.

### app/Console/Commands

- `AdminCommand` — `hspm:admin`.
- `AdoptLegacyDatabase` — `hspm:adopt-legacy`.
- `ImportCommand` — `hspm:import`.

### resources/js

- `main.js` — точка входа Vue.
- `App.vue` — оболочка SPA, меню, тема.
- `views/DashboardView.vue` — главная страница.
- `views/RecordsView.vue` — списки справочников.
- `views/ImportView.vue` — импорт.
- `components/RecordForm.vue` — форма создания и редактирования.
- `components/ListPagination.vue` — пагинация.
- `components/LoginForm.vue` — вход.
- `components/FreeClassrooms.vue` — поиск свободных аудиторий.
- `store/auth.js` — сессия.
- `store/records.js` — состояние списков.
- `store/dashboard.js` — состояние главной.
- `store/index.js` — корневое хранилище Vuex.
- `services/http.js` — HTTP-клиент (CSRF, ошибки, повтор 419).
- `services/education.js` — предметные методы API.
- `config/resources.js` — описание колонок, фильтров и форм.
- `utils/format.js` — чистые функции форматирования.

## Точки расширения

- Новый тип справочника: модель + миграция + FormRequest + контроллер + `resources.js`
  + запись в `EducationQuery` и маршруты.
- Новый тип импорта: парсер в `Services/Import` + ветка в `ImportService`/`ImportWriter`.
- Новый тип экспорта: `*Workbook` + ветка в `ExportService`.

---

См. также: [README](../README.md), [ARCHITECTURE.md](ARCHITECTURE.md), [API.md](API.md), [DEVELOPMENT.md](DEVELOPMENT.md).
