<?php

namespace App\Services\Import;

use App\Models\Classroom;
use App\Models\ScheduleEntry;
use App\Models\Software;
use App\Models\Teacher;
use App\Services\Schedule\RoomParser;
use App\Services\Schedule\ScheduleKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Пакетная запись. SQL-запросы растут по числу пакетов, а не ячеек Excel. */
class ImportWriter
{
    public function save(string $type, array $rows, bool $createMissing, bool $replace, array $softwareRooms = []): array
    {
        return DB::transaction(function () use ($type, $rows, $createMissing, $replace, $softwareRooms) {
            if ($type === 'teachers') {
                foreach ($rows as &$row) {
                    $row['middle_name'] = $row['middle_name'] ?? '';
                }
                unset($row);
                $this->upsert(Teacher::class, $rows, ['last_name', 'first_name', 'middle_name']);
            } elseif ($type === 'classrooms') {
                $this->upsert(Classroom::class, $rows, ['room_number', 'building']);
            } elseif ($type === 'software') {
                $roomMap = $this->ensureRooms($softwareRooms);
                // Заменяем только аудитории из файла, включая колонки без программ.
                foreach (array_chunk($softwareRooms, 200) as $chunk) {
                    Software::where(function ($query) use ($chunk) {
                        foreach ($chunk as $room) {
                            $query->orWhere(fn ($q) => $q->where('building', $room['building'])->where('room_number', $room['room_number']));
                        }
                    })->delete();
                }
                foreach ($rows as &$row) {
                    $row['classroom_id'] = $roomMap[$row['building'].'|'.$row['room_number']] ?? null;
                }
                unset($row);
                $this->upsert(Software::class, $rows, ['room_number', 'building', 'name']);
            } else {
                return $this->schedule($rows, $createMissing, $replace);
            }

            return ['imported' => count($rows), 'created_teachers' => 0, 'deleted' => 0];
        });
    }

    private function schedule(array $rows, bool $createMissing, bool $replace): array
    {
        $rooms = [];
        foreach ($rows as $row) {
            array_push($rooms, ...RoomParser::parse($row['classrooms_raw'] ?? ''));
        }
        $roomMap = $this->ensureRooms($rooms);
        $teachers = Teacher::all(['id', 'last_name', 'first_name', 'middle_name'])->groupBy(fn ($teacher) => mb_strtolower($teacher->last_name));
        $createdTeachers = 0;
        $relations = [];
        $records = [];
        $groups = [];
        $dates = [];
        foreach ($rows as $row) {
            $roomIds = [];
            foreach (RoomParser::parse($row['classrooms_raw'] ?? '') as $room) {
                $roomIds[] = $roomMap[$room['building'].'|'.$room['room_number']];
            }
            $row['classroom_id'] = $roomIds[0] ?? null;
            $row['teacher_id'] = $this->teacher($row, $teachers, $createMissing, $createdTeachers);
            $row['dedup_key'] = ScheduleKey::make($row);
            // Дубликаты внутри файла сворачиваются до обращения к базе.
            $records[$row['dedup_key']] = $row;
            $relations[$row['dedup_key']] = $roomIds;
            if (! empty($row['group_code'])) {
                $groups[] = $row['group_code'];
            }
            if (! empty($row['date'])) {
                $dates[] = $row['date'];
            }
        }
        if ($replace && (count($dates) !== count($rows) || count($groups) !== count($rows))) {
            throw ValidationException::withMessages(['file' => 'Для замены каждая строка должна содержать дату и группу. Недельную сетку можно только добавлять/обновлять.']);
        }
        $this->upsert(ScheduleEntry::class, array_values($records), ['dedup_key']);
        $idsByKey = [];
        foreach (array_chunk(array_keys($records), 200) as $keys) {
            $idsByKey += ScheduleEntry::whereIn('dedup_key', $keys)->pluck('id', 'dedup_key')->all();
        }
        $links = [];
        foreach ($idsByKey as $key => $id) {
            foreach ($relations[$key] as $roomId) {
                $links[] = ['schedule_id' => $id, 'classroom_id' => $roomId];
            }
        }
        foreach (array_chunk(array_values($idsByKey), 200) as $ids) {
            DB::table('schedule_classrooms')->whereIn('schedule_id', $ids)->delete();
        }
        foreach (array_chunk($links, 200) as $chunk) {
            DB::table('schedule_classrooms')->insertOrIgnore($chunk);
        }
        $deleted = 0;
        if ($replace) {
            // Чужие группы не затрагиваются. Ключи сравниваются в памяти, SQL DELETE идёт пакетами.
            $known = array_fill_keys(array_keys($records), true);
            ScheduleEntry::whereIn('group_code', array_unique($groups))->whereBetween('date', [min($dates), max($dates)])
                ->select(['id', 'dedup_key'])->chunkById(200, function ($existing) use ($known, &$deleted) {
                    $staleIds = $existing->reject(fn ($entry) => isset($known[$entry->dedup_key]))->pluck('id');
                    $deleted += ScheduleEntry::whereIn('id', $staleIds)->delete();
                });
        }

        return ['imported' => count($records), 'created_teachers' => $createdTeachers, 'deleted' => $deleted];
    }

    private function teacher(array $row, $teachers, bool $create, int &$created): ?int
    {
        $name = trim($row['examiner'] ?? '');
        if ($name === '') {
            return null;
        }
        $parts = preg_split('/[\s.]+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
        $surname = mb_strtolower($parts[0]);
        $matches = ($teachers[$surname] ?? collect())->filter(function ($teacher) use ($parts) {
            return (! isset($parts[1]) || str_starts_with(mb_strtolower($teacher->first_name), mb_strtolower($parts[1])))
                && (! isset($parts[2]) || str_starts_with(mb_strtolower($teacher->middle_name ?? ''), mb_strtolower($parts[2])));
        });
        if ($matches->count() === 1) {
            return $matches->first()->id;
        }
        // Несколько преподавателей в одной ячейке остаются текстом examiner.
        if (! $create || $matches->count() > 1 || count($parts) > 3) {
            return null;
        }
        $teacher = Teacher::create(['last_name' => $parts[0], 'first_name' => $parts[1] ?? '—', 'middle_name' => $parts[2] ?? '', 'department' => $row['teacher_department'] ?? null, 'position' => $row['teacher_position'] ?? null]);
        $teachers[$surname] = ($teachers[$surname] ?? collect())->push($teacher);
        $created++;

        return $teacher->id;
    }

    private function ensureRooms(array $rooms): array
    {
        $unique = [];
        foreach ($rooms as $room) {
            $unique[$room['building'].'|'.$room['room_number']] = $room + ['room_type' => ''];
        }
        foreach (array_chunk(array_values($unique), 200) as $chunk) {
            Classroom::insertOrIgnore($chunk);
        }

        return Classroom::all(['id', 'building', 'room_number'])->mapWithKeys(fn ($room) => [$room->building.'|'.$room->room_number => $room->id])->all();
    }

    private function upsert(string $model, array $rows, array $unique): void
    {
        if (! $rows) {
            return;
        }
        $fields = array_keys($rows[0]);
        // Все строки пакета имеют один набор полей; Laravel сортирует их перед upsert.
        foreach ($rows as $row) {
            $fields = array_unique(array_merge($fields, array_keys($row)));
        }
        $fillable = (new $model)->getFillable();
        $fields = array_values(array_intersect($fields, $fillable));
        $defaults = array_fill_keys($fields, null);
        foreach (['is_occupied' => 1, 'is_nonstandard_time' => 0, 'transfer_cancel' => 'нет'] as $field => $default) {
            if (in_array($field, $fields)) {
                $defaults[$field] = $default;
            }
        }
        $normalized = array_map(fn ($row) => array_replace($defaults, array_intersect_key($row, $defaults)), $rows);
        foreach (array_chunk($normalized, 200) as $chunk) {
            $model::upsert($chunk, $unique, array_values(array_diff($fields, $unique)));
        }
    }
}
