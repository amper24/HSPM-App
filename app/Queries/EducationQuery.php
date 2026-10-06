<?php

namespace App\Queries;

use App\Models\Classroom;
use App\Models\ScheduleEntry;
use App\Models\Software;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Фильтры списков. Имена полей берутся только из разрешённых списков. */
class EducationQuery
{
    public function for(string $resource, array $filters = []): Builder
    {
        $model = match ($resource) {
            'teachers' => Teacher::class, 'classrooms' => Classroom::class,
            'schedule' => ScheduleEntry::class, 'software' => Software::class, 'users' => User::class,
        };
        $query = $model::query();
        $exactFields = match ($resource) {
            'teachers' => ['department', 'employment_type', 'degree', 'title'],
            'classrooms' => ['building', 'room_type', 'has_projector', 'has_speakers'],
            'schedule' => ['date', 'teacher_id', 'lesson_type', 'transfer_cancel', 'is_occupied', 'numerator_denominator', 'group_code', 'pair_number'],
            'software' => ['building', 'classroom_id'],
            default => [],
        };
        foreach ($exactFields as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        $search = trim((string) ($filters['search'] ?? $filters['fio'] ?? ''));
        $searchFields = match ($resource) {
            'teachers' => ['last_name', 'first_name', 'middle_name'],
            'classrooms' => ['room_number', 'room_type', 'software_installed'],
            'schedule' => ['discipline', 'group_code', 'examiner'],
            'software' => ['name', 'room_number'],
            default => ['username', 'full_name'],
        };
        // Несколько слов ФИО могут находиться в разных колонках одной записи.
        foreach (preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $query->where(function (Builder $group) use ($word, $searchFields, $resource) {
                foreach ($searchFields as $field) {
                    $group->orWhere($field, 'like', '%'.$word.'%');
                }
                if ($resource === 'schedule') {
                    $group->orWhereHas('teacher', fn ($teacher) => $teacher->where('last_name', 'like', '%'.$word.'%'));
                }
            });
        }
        foreach (['room_number', 'name', 'discipline', 'software_installed'] as $field) {
            if (in_array($field, $searchFields) && ! empty($filters[$field])) {
                $query->where($field, 'like', '%'.$filters[$field].'%');
            }
        }
        if ($resource === 'teachers' && ! empty($filters['transfer_cancel'])) {
            $query->whereHas('lessons', fn ($lessons) => $lessons->where('transfer_cancel', $filters['transfer_cancel']));
        }
        if ($resource === 'classrooms') {
            foreach (['seats', 'computers_count'] as $field) {
                if (isset($filters[$field.'_min']) && $filters[$field.'_min'] !== '') {
                    $query->where($field, '>=', $filters[$field.'_min']);
                }
            }
            if (in_array($filters['sort_seats'] ?? '', ['asc', 'desc'])) {
                $query->orderBy('seats', $filters['sort_seats']);
            }
        }
        if ($resource === 'schedule') {
            // Число запросов не зависит от числа строк: три связи загружаются пакетами.
            $query->with(['teacher', 'classroom', 'rooms']);
            if (! empty($filters['date_from'])) {
                $query->where('date', '>=', $filters['date_from']);
            }
            if (! empty($filters['date_to'])) {
                $query->where('date', '<=', $filters['date_to']);
            }
            if (! empty($filters['classroom_id'])) {
                $query->where(fn ($q) => $q->where('classroom_id', $filters['classroom_id'])
                    ->orWhereHas('rooms', fn ($rooms) => $rooms->where('classrooms.id', $filters['classroom_id'])));
            }
            if (! empty($filters['building'])) {
                $query->where(fn ($q) => $q->whereHas('classroom', fn ($room) => $room->where('building', $filters['building']))
                    ->orWhereHas('rooms', fn ($rooms) => $rooms->where('building', $filters['building'])));
            }
        }
        $order = match ($resource) {
            'teachers' => ['last_name', 'first_name'], 'classrooms', 'software' => ['building', 'room_number'],
            'schedule' => ['date', 'pair_number', 'time_start'], default => [],
        };
        foreach ($order as $field) {
            $query->orderBy($field);
        }

        return $query->orderBy('id');
    }
}
