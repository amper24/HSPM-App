/**
 * Чистые функции форматирования данных для представления.
 *
 * Возвращают текст (а не HTML), поэтому значения из БД не могут внедрить разметку
 * в интерфейс: Vue экранирует текст при интерполяции.
 */

/** Собирает ФИО из трёх полей, пропуская пустые части. */
export const fullName = item => [item.last_name, item.first_name, item.middle_name].filter(Boolean).join(' ');

/** Строка аудиторий занятия: список связей, либо сырая строка, либо «корпус+номер». */
export const scheduleRooms = item => item.classrooms || item.classrooms_raw || (item.room_number ? `${item.building || ''}${item.room_number}` : '—');

/** Время занятия HH:MM–HH:MM; при отсутствии — «Пара N», иначе «—». */
export const lessonTime = item => item.time_start ? [item.time_start.slice(0, 5), item.time_end?.slice(0, 5)].filter(Boolean).join('–') : item.pair_number ? `Пара ${item.pair_number}` : '—';

/** Сегодняшняя дата в формате YYYY-MM-DD (через локаль sv-SE). */
export const localDate = () => new Date().toLocaleDateString('sv-SE');

/**
 * Текст ячейки таблицы с учётом типа колонки.
 *
 * @param {object} item   Запись.
 * @param {object} column Описание колонки ({field, type, render}).
 * @returns {string} Отображаемый текст.
 */
export const cellText = (item, column) => column.render ? column.render(item) : column.type === 'bool' ? (Number(item[column.field]) ? 'Да' : 'Нет') : (item[column.field] ?? '—');
