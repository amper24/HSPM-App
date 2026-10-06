import { fullName, scheduleRooms, lessonTime } from '../utils/format';

/** Описание колонок, фильтров и полей редактора. Здесь нет запросов и состояния. */
export const resources = {
  teachers: {
          type: 'teachers', title: 'Преподаватели', itemName: 'Преподаватель', showReport: false,
          columns: [
            { label: 'ФИО', render: item => fullName(item) },
            { label: 'Кафедра', field: 'department' }, { label: 'Должность', field: 'position' },
            { label: 'Степень', field: 'degree' }, { label: 'Звание', field: 'title' },
            { label: 'Занятость', field: 'employment_type' }, { label: 'Email', field: 'email' },
            { label: 'Телефон', field: 'phone' }
          ],
          filters: [
            { type: 'text', field: 'search', placeholder: 'Поиск по ФИО...' },
            { type: 'select', field: 'department', placeholder: 'Все кафедры' },
            { type: 'select', field: 'degree', placeholder: 'Все степени' },
            { type: 'select', field: 'title', placeholder: 'Все звания' },
            { type: 'select', field: 'employment_type', placeholder: 'Все формы' },
            { type: 'select', field: 'transfer_cancel', placeholder: 'Перенос/отмена', options: [{ value: 'перенос', label: 'Переносы' }, { value: 'отмена', label: 'Отмены' }] }
          ],
          formFields: [
            { field: 'last_name', label: 'Фамилия' }, { field: 'first_name', label: 'Имя' }, { field: 'middle_name', label: 'Отчество' },
            { field: 'position', label: 'Должность' }, { field: 'degree', label: 'Степень' }, { field: 'title', label: 'Звание' },
            { field: 'department', label: 'Кафедра' }, { field: 'employment_type', label: 'Форма занятости' },
            { field: 'email', label: 'Email' }, { field: 'phone', label: 'Телефон' }
          ]
        },
  classrooms: {
          type: 'classrooms', title: 'Аудитории', itemName: 'Аудиторию', showReport: true,
          columns: [
            { label: 'Аудитория', field: 'room_number' }, { label: 'Корпус', field: 'building' },
            { label: 'Тип', field: 'room_type' }, { label: 'ПК', field: 'computers_count' },
            { label: 'Проектор', field: 'has_projector', type: 'bool' },
            { label: 'Колонки', field: 'has_speakers', type: 'bool' }, { label: 'Мест', field: 'seats' }
          ],
          filters: [
            { type: 'text', field: 'search', placeholder: 'Поиск по номеру, типу...' },
            { type: 'select', field: 'building', placeholder: 'Все корпуса', options: [{ value: 'Д', label: 'Джамбула' }, { value: 'В', label: 'Вознесенский' }, { value: 'БМ', label: 'Большая Морская' }] },
            { type: 'select', field: 'room_type', placeholder: 'Все типы' },
            { type: 'select', field: 'has_projector', placeholder: 'Проектор', options: [{ value: '1', label: 'Есть' }, { value: '0', label: 'Нет' }] },
            { type: 'select', field: 'has_speakers', placeholder: 'Колонки', options: [{ value: '1', label: 'Есть' }, { value: '0', label: 'Нет' }] },
            { type: 'select', field: 'sort_seats', placeholder: 'Сортировка по местам', options: [{ value: 'asc', label: 'По местам ↑' }, { value: 'desc', label: 'По местам ↓' }] }
          ],
          formFields: [
            { field: 'room_number', label: '№ аудитории' }, { field: 'building', label: 'Корпус' },
            { field: 'room_type', label: 'Тип' }, { field: 'computers_count', label: 'Количество ПК', type: 'number' },
            { field: 'has_projector', label: 'Проектор', type: 'checkbox' }, { field: 'has_speakers', label: 'Колонки', type: 'checkbox' },
            { field: 'seats', label: 'Посадочных мест', type: 'number' }
          ]
        },
  schedule: {
          type: 'schedule', title: 'Расписание', itemName: 'Запись расписания', showReport: false,
          columns: [
            { label: 'Дата', field: 'date' },
            { label: 'День недели', field: 'day_of_week' },
            { label: 'Время', render: item => lessonTime(item) },
            { label: 'Группа', field: 'group_code' },
            { label: 'Дисциплина', field: 'discipline' },
            { label: 'Вид', field: 'exam_type' },
            { label: 'Экзаменатор', field: 'examiner' },
            { label: 'Аудитория', render: item => scheduleRooms(item) },
            { label: 'Перенос/отмена', render: item => item.transfer_cancel || 'нет' }
          ],
          filters: [
            { type: 'text', field: 'search', placeholder: 'Поиск по дисциплине, группе, преподавателю...' },
            { type: 'select', field: 'transfer_cancel', placeholder: 'Все', options: [{ value: 'перенос', label: 'Переносы' }, { value: 'отмена', label: 'Отмены' }, { value: 'нет', label: 'Без переносов/отмен' }] }
          ],
          formFields: [
            { field: 'date', label: 'Дата', type: 'date' },
            { field: 'day_of_week', label: 'День недели' },
            { field: 'numerator_denominator', label: 'Числитель/знаменатель' },
            { field: 'pair_number', label: 'Номер пары', type: 'number' },
            { field: 'is_occupied', label: 'Занята', type: 'checkbox' },
            { field: 'is_nonstandard_time', label: 'Нестандартное время', type: 'checkbox' }, { field: 'time_start', label: 'Время начала', type: 'time' }, { field: 'time_end', label: 'Время окончания', type: 'time' },
            { field: 'group_code', label: 'Группа' }, { field: 'discipline', label: 'Дисциплина' },
            { field: 'exam_type', label: 'Вид' }, { field: 'examiner', label: 'Экзаменатор' },
            { field: 'classrooms', label: 'Аудитории' }, { field: 'group_department', label: 'Кафедра группы' },
            { field: 'teacher_department', label: 'Кафедра преп.' }, { field: 'teacher_position', label: 'Должность преп.' },
            { field: 'session_start', label: 'Сессия с', type: 'date' }, { field: 'session_end', label: 'Сессия по', type: 'date' },
            { field: 'lesson_type', label: 'Тип занятия' }, { field: 'transfer_cancel', label: 'Перенос/отмена' }
          ]
        },
  software: {
          type: 'software', title: 'Программное обеспечение', itemName: 'ПО', showReport: false,
          columns: [
            { label: 'Название', field: 'name' }, { label: 'Аудитория', field: 'room_number' }, { label: 'Корпус', field: 'building' }
          ],
          filters: [
            { type: 'text', field: 'search', placeholder: 'Поиск по названию...' },
            { type: 'select', field: 'building', placeholder: 'Все корпуса' }
          ],
          formFields: [
            { field: 'name', label: 'Название ПО' }, { field: 'room_number', label: 'Аудитория' }, { field: 'building', label: 'Корпус' }
          ]
        },
  users: {
          type: 'users', title: 'Пользователи', itemName: 'Пользователя', showReport: false,
          columns: [
            { label: 'Логин', field: 'username' }, { label: 'Роль', field: 'role' },
            { label: 'ФИО', field: 'full_name' }, { label: 'Создан', field: 'created_at' }
          ],
          filters: [],
          formFields: [
            { field: 'username', label: 'Логин' }, { field: 'password', label: 'Пароль', type: 'password' },
            { field: 'role', label: 'Роль', type: 'select', options: [{ value: 'user', label: 'Пользователь' }, { value: 'admin', label: 'Администратор' }] },
            { field: 'full_name', label: 'ФИО' }
          ]
        }
};
