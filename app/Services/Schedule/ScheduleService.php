<?php

namespace App\Services\Schedule;

use App\Models\Classroom;
use App\Models\ScheduleEntry;
use Illuminate\Support\Facades\DB;

/** Запись занятия и всех его аудиторий выполняется одной транзакцией. */
class ScheduleService
{
    public function save(array $data, ?int $id = null): ScheduleEntry
    {
        return DB::transaction(function () use ($data, $id) {
            $entry = $id ? ScheduleEntry::findOrFail($id) : new ScheduleEntry;
            $roomsChanged = array_key_exists('classrooms', $data) || array_key_exists('classrooms_raw', $data);
            if ($roomsChanged) {
                $data['classrooms_raw'] = $data['classrooms'] ?? $data['classrooms_raw'] ?? '';
                $rooms = RoomParser::parse($data['classrooms_raw']);
                $roomIds = [];
                foreach ($rooms as $room) {
                    $roomIds[] = Classroom::firstOrCreate($room, ['room_type' => ''])->id;
                }
                $data['classroom_id'] = $roomIds[0] ?? null;
            } elseif (array_key_exists('classroom_id', $data)) {
                $roomIds = array_filter([$data['classroom_id']]);
                $data['classrooms_raw'] = null;
            }
            unset($data['classrooms']);
            $entry->fill($data);
            $entry->dedup_key = ScheduleKey::make($entry->getAttributes());
            $entry->save();
            if (isset($roomIds)) {
                $entry->rooms()->sync($roomIds);
            }

            return $entry->load(['teacher', 'classroom', 'rooms']);
        });
    }
}
