<?php

namespace App\Http\Resources;

use App\Models\ScheduleEntry;

/**
 * Плоское представление записи расписания для клиента.
 *
 * Скрывает структуру Eloquent-связей: вместо вложенных объектов преподавателя и
 * аудиторий отдаёт готовые строки (teacher_name, room_number, building, classrooms).
 * Это делает контракт API стабильным и не зависящим от внутренней реализации ORM.
 */
class ScheduleData
{
    /**
     * Преобразует модель занятия в массив для JSON-ответа.
     *
     * @param  ScheduleEntry  $entry Занятие с загруженными связями teacher, classroom, rooms.
     * @return array<string, mixed> Плоский массив атрибутов + вычисленные поля
     *                              room_number, building, teacher_name, classrooms.
     */
    public static function from(ScheduleEntry $entry): array
    {
        $data = $entry->attributesToArray();
        $data['room_number'] = $entry->classroom?->room_number;
        $data['building'] = $entry->classroom?->building;
        $data['teacher_name'] = $entry->teacher
            ? trim(implode(' ', [$entry->teacher->last_name, $entry->teacher->first_name, $entry->teacher->middle_name])) : null;
        $data['classrooms'] = $entry->rooms->sortBy('room_number')->map(fn ($room) => $room->building.$room->room_number)->implode(', ');

        return $data;
    }
}
