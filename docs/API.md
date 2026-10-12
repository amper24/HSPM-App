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

## Поля расписания

Основные поля создаваемой записи (см. `ScheduleRequest`): `classroom_id`, `teacher_id`,
`classrooms` (строка аудиторий), `date`, `day_of_week`, `time_start`, `time_end`,
`pair_number`, `discipline`, `group_code`, `group_department`, `examiner`, `exam_type`,
`lesson_type`, `transfer_cancel` (`нет|перенос|отмена`), `is_occupied`,
`is_nonstandard_time`, `numerator_denominator`, `session_start`, `session_end`.

---

См. также: [README](../README.md), [ARCHITECTURE.md](ARCHITECTURE.md), [MODULES.md](MODULES.md), [CONFIGURATION.md](CONFIGURATION.md).
