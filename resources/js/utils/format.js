/** Чистые функции форматирования. Возвращают текст, а не HTML. */
export const fullName = item => [item.last_name, item.first_name, item.middle_name].filter(Boolean).join(' ');
export const scheduleRooms = item => item.classrooms || item.classrooms_raw || (item.room_number ? `${item.building || ''}${item.room_number}` : '—');
export const lessonTime = item => item.time_start ? [item.time_start.slice(0, 5), item.time_end?.slice(0, 5)].filter(Boolean).join('–') : item.pair_number ? `Пара ${item.pair_number}` : '—';
export const localDate = () => new Date().toLocaleDateString('sv-SE');
export const cellText = (item, column) => column.render ? column.render(item) : column.type === 'bool' ? (Number(item[column.field]) ? 'Да' : 'Нет') : (item[column.field] ?? '—');
