# API справочник

Внутренний JSON API под префиксом `/api`. Все запросы (кроме входа и `csrf`) требуют
авторизованной cookie-сессии Laravel; изменяющие операции — роль `admin`.

## Формат ответов

Успех:

```json
{ "success": true, "data": {}, "message": "OK" }
```

Ошибка (см. подробности в [ARCHITECTURE.md](ARCHITECTURE.md)):

```json
{ "success": false, "error": "текст", "errors": { "поле": ["сообщение"] } }
```

`errors` присутствует только при ошибках валидации (422).

## Аутентификация

| Метод | Путь | Доступ | Описание |
| --- | --- | --- | --- |
| GET | `/api/csrf` | гость | Текущий CSRF-токен |
| GET | `/api/auth/me` | гость | Текущий пользователь или `null` |
| POST | `/api/auth/login` | гость (throttle 10/мин) | Вход: `{username, password}` |
| POST | `/api/auth/logout` | auth | Выход |

## Прочее

| Метод | Путь | Доступ | Описание |
| --- | --- | --- | --- |
| GET | `/api/dashboard/stats` | auth | Счётчики `{teachers, classrooms, schedule, software}` |
| GET | `/api/classrooms/free` | auth | Свободные аудитории; параметры: `date`, `pair_number`, `building`, `room_type`, `has_projector`, `has_speakers`, `seats_min` |
| GET | `/api/{resource}/{field}` | auth | Уникальные значения фильтров (`departments`, `degrees`, `titles`, `employment-types`, `room-types`, `buildings`, `groups`) |
| GET | `/api/{resource}/search` | auth | Поиск `teachers`/`classrooms` (до 500) |
| GET | `/api/export/{type}` | auth | Создать выгрузку (`teachers|classrooms|schedule|software|report`); возвращает `{file, url}` |
| GET | `/api/exports/{filename}` | auth (владелец) | Скачать выгрузку |
| POST | `/api/import` | admin | Импорт Excel (`multipart`): `file`, `type`, `replace` |

## Ресурсы (CRUD)

Для `teachers`, `classrooms`, `software`, `schedule`, `users`.

| Метод | Путь | Доступ | Описание |
| --- | --- | --- | --- |
| GET | `/api/{resource}` | auth | Список (пагинация `per_page=1..100`); фильтры — см. ниже |
| GET | `/api/{resource}/{id}` | auth | Одна запись |
| POST | `/api/{resource}` | admin | Создать |
| PUT | `/api/{resource}/{id}` | admin | Обновить (частичное) |
| DELETE | `/api/{resource}/{id}` | admin | Удалить |
| POST | `/api/{resource}/truncate` | admin | Очистить таблицу (кроме `users`) |

Для `users` список доступен только администратору. Удаление собственной записи и
последнего администратора запрещено (422).

Дополнительно для расписания:

| Метод | Путь | Доступ | Описание |
| --- | --- | --- | --- |
| POST | `/api/schedule/bulk-delete` | admin | Удалить по `{ids:[...]}` либо `{date_from, date_to}` |

## Фильтры списков

Точное соответствие:

- `teachers`: `department`, `employment_type`, `degree`, `title`
- `classrooms`: `building`, `room_type`, `has_projector`, `has_speakers`
- `schedule`: `date`, `teacher_id`, `lesson_type`, `transfer_cancel`, `is_occupied`, `numerator_denominator`, `group_code`, `pair_number`, `date_from`, `date_to`
- `software`: `building`, `classroom_id`

Полнотекстовый поиск: `search` (для преподавателей также `fio`). Для аудиторий —
`seats_min`, `computers_count_min`, `sort_seats=asc|desc`.

## Поля ресурсов

### teachers

| Поле | Тип | Правила валидации |
| --- | --- | --- |
| `last_name` | string | required, max:100 |
| `first_name` | string | required, max:100 |
| `middle_name` | string | nullable, max:100 |
| `position` | string | nullable, max:255 |
| `degree` | string | nullable, max:100 |
| `title` | string | nullable, max:100 |
| `department` | string | nullable, max:100 |
| `employment_type` | string | nullable, max:50 |
| `email` | string | nullable, email, max:255 |
| `phone` | string | nullable, max:50 |
| `notes` | string | nullable, max:10000 |

### classrooms

| Поле | Тип | Правила валидации |
| --- | --- | --- |
| `room_number` | string | required, max:20 |
| `building` | string | required, max:5 (`Д`, `В`, `БМ`) |
| `room_type` | string | required, max:50 |
| `software_installed` | string | nullable, max:10000 |
| `seats` | integer | nullable, min:0 |
| `computers_count` | integer | nullable, min:0 |
| `has_projector` | boolean | sometimes |
| `has_speakers` | boolean | sometimes |

### software

| Поле | Тип | Правила валидации |
| --- | --- | --- |
| `name` | string | required, max:255 |
| `room_number` | string | nullable, max:20 |
| `building` | string | nullable, max:5 |
| `classroom_id` | integer | nullable, exists:classrooms,id |
| `notes` | string | nullable, max:10000 |

### schedule

| Поле | Тип | Правила валидации |
| --- | --- | --- |
| `classroom_id` | integer | nullable, exists:classrooms,id |
| `teacher_id` | integer | nullable, exists:teachers,id |
| `classrooms` | string | nullable, max:255 — строка аудиторий «Д234, В105» |
| `classrooms_raw` | string | nullable, max:255 |
| `date` | string | nullable, date_format:Y-m-d |
| `time_start` | string | nullable, H:i или H:i:s |
| `time_end` | string | nullable, H:i или H:i:s |
| `session_start` | string | nullable, date_format:Y-m-d |
| `session_end` | string | nullable, date_format:Y-m-d |
| `pair_number` | integer | nullable, min:1, max:20 |
| `is_occupied` | boolean | sometimes |
| `is_nonstandard_time` | boolean | sometimes |
| `transfer_cancel` | string | nullable, in:нет,перенос,отмена |
| `discipline` | string | nullable, max:10000 |
| `examiner` | string | nullable, max:10000 |
| `group_code` | string | nullable, max:50 |
| `group_department` | string | nullable, max:100 |
| `teacher_department` | string | nullable, max:100 |
| `teacher_position` | string | nullable, max:100 |
| `numerator_denominator` | string | nullable, max:20 |
| `day_of_week` | string | nullable, max:20 |
| `lesson_type` | string | nullable, max:20 |
| `exam_type` | string | nullable, max:20 |

### users

| Поле | Тип | Правила валидации |
| --- | --- | --- |
| `username` | string | required, max:50, уникальный |
| `password` | string | required при создании, min:8, max:255; пустое при обновлении — «не менять пароль» |
| `role` | string | required, in:user,admin |
| `full_name` | string | nullable, max:255 |

## Структура списка (пагинация)

Ответ `GET /api/{resource}`:

```json
{
  "success": true,
  "data": {
    "items": [ { "id": 1, "...": "..." } ],
    "pagination": { "page": 1, "per_page": 50, "total": 120, "pages": 3 }
  },
  "message": "OK"
}
```

`per_page` принимает значения от 1 до 100 (по умолчанию 50).

## Примеры запросов

Вход:

```bash
curl -c cookies.txt -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"..."}' \
  http://127.0.0.1:8000/api/auth/login
```

Список преподавателей с поиском (с сессией):

```bash
curl -b cookies.txt -H 'X-Requested-With: XMLHttpRequest' \
  'http://127.0.0.1:8000/api/teachers?search=%D0%98%D0%B2%D0%B0%D0%BD%D0%BE%D0%B2&per_page=20'
```

Свободные аудитории:

```bash
curl -b cookies.txt -H 'X-Requested-With: XMLHttpRequest' \
  'http://127.0.0.1:8000/api/classrooms/free?date=2026-09-30&pair_number=2&building=%D0%94'
```

---

См. также: [README](../README.md), [ARCHITECTURE.md](ARCHITECTURE.md), [MODULES.md](MODULES.md), [CONFIGURATION.md](CONFIGURATION.md).
