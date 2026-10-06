<?php

namespace App\Http\Resources;

use App\Models\ScheduleEntry;

/** Представление расписания без привязки браузера к структуре Eloquent-связей. */
class ScheduleData
{
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
